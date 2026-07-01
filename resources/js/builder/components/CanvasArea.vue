<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { getQuestionType } from '../registry/questionTypes';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';
import type { BuilderEndpoints, CascadeNode, Condition, SurveyElement, SurveyOption, SurveyOptionAction, SurveyPage } from '../types/schema';
import SurveyRichEditor from './SurveyRichEditor.vue';
import RightPanel from './RightPanel.vue';
import { elementSupportsJump, elementSupportsLogic, hasActiveJumpLogic, isContentBlockType, typeCategory } from '../utils/builderHelpers';

const props = defineProps<{
  endpoints: BuilderEndpoints;
  csrfToken: string;
}>();

const store = useSurveyBuilderStore();

// ── Preview state ──────────────────────────────────────────────────────────
const previewSelections = ref<Record<string, string | Set<string>>>({});
const previewTextValues = ref<Record<string, string>>({});
const previewConstantSumValues = ref<Record<string, Record<string, string>>>({});
const previewMatrixSelections = ref<Record<string, Record<string, string | string[]>>>({});
const previewCascade = ref<Record<string, string[]>>({});
const previewAddressValues = ref<Record<string, Record<string, string>>>({});
const previewRankingOrders = ref<Record<string, string[]>>({});
const previewFileNames = ref<Record<string, string>>({});
const previewFileDragOver = ref<Record<string, boolean>>({});
const previewSignatures = ref<Record<string, boolean>>({});
const previewPageHistory = ref<string[]>([]);
const previewEnded = ref(false);
const previewRatings = ref<Record<string, number | null>>({});
const previewRatingHover = ref<Record<string, number>>({});
const previewRatingPop = ref<Record<string, number>>({});
const previewNps = ref<Record<string, number | null>>({});
const previewTermsAccepted = ref(false);

watch(() => store.isPreviewMode, (entering) => {
  if (entering) {
    previewSelections.value = {};
    previewTextValues.value = {};
    previewConstantSumValues.value = {};
    previewEnded.value = false;
    previewMatrixSelections.value = {};
    previewCascade.value = {};
    previewAddressValues.value = {};
    previewRankingOrders.value = {};
    previewFileNames.value = {};
    previewFileDragOver.value = {};
    previewSignatures.value = {};
    previewRatings.value = {};
    previewRatingHover.value = {};
    previewNps.value = {};
    previewTermsAccepted.value = false;
    previewPageHistory.value = [];
    // 每次進入預覽重新取種子，讓題組／選項隨機的效果可被觀察（再次進入會換順序）。
    previewSeed.value = Math.floor(Math.random() * 0xffffffff);
  }
});

// ── 預覽用隨機（對應公開填答端 SurveyField::arrangeForDisplay / displayOptions）──
const previewSeed = ref(Math.floor(Math.random() * 0xffffffff));

function hashString(str: string): number {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    hash = (hash * 31 + str.charCodeAt(i)) >>> 0;
  }
  return hash;
}

function seededShuffle<T>(items: T[], seed: number): T[] {
  if (items.length <= 1) return items.slice();
  const arr = items.slice();
  let state = (seed >>> 0) || 1;
  const rand = (): number => {
    state ^= state << 13; state >>>= 0;
    state ^= state >> 17;
    state ^= state << 5; state >>>= 0;
    return state / 0xffffffff;
  };
  for (let i = arr.length - 1; i > 0; i--) {
    const j = Math.floor(rand() * (i + 1));
    [arr[i], arr[j]] = [arr[j], arr[i]];
  }
  return arr;
}

/** 依題組設定重排同題組題目（與後端 arrangeForDisplay 同語意：成員只在原本佔據的位置間洗牌）。 */
function arrangeElementsForPreview(elements: SurveyElement[], seed: number): SurveyElement[] {
  const positionsByGroup: Record<string, number[]> = {};
  const randomizedGroups: Record<string, boolean> = {};

  elements.forEach((element, index) => {
    const group = (element.settings as Record<string, unknown> | undefined)?.group;
    if (typeof group !== 'string' || group === '') return;
    (positionsByGroup[group] ||= []).push(index);
    if ((element.settings as Record<string, unknown>)?.randomize_in_group) randomizedGroups[group] = true;
  });

  const result = elements.slice();
  Object.entries(positionsByGroup).forEach(([group, positions]) => {
    if (positions.length <= 1 || !randomizedGroups[group]) return;
    const members = positions.map((position) => elements[position]);
    const shuffled = seededShuffle(members, (seed ^ hashString(group)) >>> 0);
    positions.forEach((position, k) => { result[position] = shuffled[k]; });
  });
  return result;
}

const previewPageElements = computed<SurveyElement[]>(() => {
  const page = store.selectedPage;
  if (!page || page.kind === 'welcome' || page.kind === 'thank_you') return [];
  const elements = page.elements ?? [];
  if (!store.isPreviewMode) return elements;
  return arrangeElementsForPreview(elements, previewSeed.value);
});

function previewOptions(element: SurveyElement): SurveyOption[] {
  if (store.isPreviewMode && (element.settings as Record<string, unknown> | undefined)?.randomize_options) {
    return seededShuffle(element.options, (previewSeed.value ^ hashString(element.field_key || element.id)) >>> 0);
  }
  return element.options;
}

function selectionBasedSourceElement(element: SurveyElement): SurveyElement | null {
  const sourceFieldKey = (element.settings as Record<string, unknown> | undefined)?.source_field_key;
  if (typeof sourceFieldKey !== 'string' || sourceFieldKey === '') return null;

  return store.allElements.find((candidate) => candidate.field_key === sourceFieldKey) ?? null;
}

function selectionBasedSourceOptions(element: SurveyElement): SurveyOption[] {
  const source = selectionBasedSourceElement(element);
  if (!source || !['single_choice', 'multiple_choice', 'select'].includes(source.type)) return [];

  return previewOptions(source);
}

function selectionBasedSourceLabel(element: SurveyElement): string {
  return selectionBasedSourceElement(element)?.label || '未命名題目';
}

function previewSelectionBasedOptions(element: SurveyElement): SurveyOption[] {
  const source = selectionBasedSourceElement(element);
  if (!source) return [];

  const sourceOptions = selectionBasedSourceOptions(element);
  const selected = previewSelections.value[source.id];

  if (selected instanceof Set) {
    return sourceOptions.filter((option) => selected.has(option.value));
  }

  if (typeof selected === 'string' && selected !== '') {
    return sourceOptions.filter((option) => option.value === selected);
  }

  return [];
}

function ratingShapeIcon(shape: string): string {
  const map: Record<string, string> = { star: '★', heart: '♥', check: '✔', thumb: '👍' };
  return map[shape] ?? '★';
}

function cascadePreviewLevelOptions(el: SurveyElement, levelIndex: number): CascadeNode[] {
  const sels = previewCascade.value[el.id] ?? [];
  let nodes: CascadeNode[] = el.cascade_data ?? [];
  for (let i = 0; i < levelIndex; i++) {
    const chosen = nodes.find((n) => n.id === sels[i]);
    if (!chosen || !chosen.children) return [];
    nodes = chosen.children;
  }
  return nodes;
}

function cascadePreviewSelect(elementId: string, levelIndex: number, nodeId: string) {
  const cur = [...(previewCascade.value[elementId] ?? [])];
  cur[levelIndex] = nodeId;
  cur.splice(levelIndex + 1);
  previewCascade.value = { ...previewCascade.value, [elementId]: cur };
}

// ── Drag state ──────────────────────────────────────────────────────────────
const dragQId = ref<string | null>(null);
const dropTarget = ref<{ type: 'zone'; pageId: string; index: number } | { type: 'tab'; pageId: string } | null>(null);
const dragPageId = ref<string | null>(null);
const pageDropTarget = ref<{ pageId: string; position: 'before' | 'after' } | null>(null);

// ── Validation error parsing ────────────────────────────────────────────────
interface ParsedError {
  raw: string;
  messages: string[];
  pageIndex: number | null;
  elementIndex: number | null;
  questionPageNumber: number | null;
  pageId: string | null;
  elementId: string | null;
  fieldName: string | null;
  pageLabel: string;
  elementNumber: number | null;
  elementLabel: string;
  fieldLabel: string;
  translatedMessages: string[];
}

const FIELD_LABELS: Record<string, string> = {
  type: '題型', label: '標題', field_key: '欄位代碼', required: '必填',
  options: '選項', description: '說明', settings: '設定', placeholder: '提示文字',
  title: '頁面標題', kind: '頁面類型',
  matrix_rows: '矩陣列', matrix_cols: '矩陣欄',
  cascade_levels: '層級設定', cascade_data: '選項資料',
  personalized_key: '對應名單欄位',
  show_if: '顯示條件',
};

const MSG_MAP: Array<[RegExp | string, string]> = [
  [/顯示條件請填寫輸入值/i, '顯示條件請填寫輸入值'],
  [/顯示條件請選擇目標題目/i, '顯示條件請選擇目標題目'],
  [/^選取的.*type.*無效$/i, '此題型目前不受支援，請改用其他題型'],
  [/^The selected.*type is invalid/i, '此題型目前不受支援，請改用其他題型'],
  [/field key is required/i, '欄位代碼不得為空'],
  [/field key must be unique/i, '欄位代碼不可重複'],
  [/at least one option is required/i, '至少需要一個選項'],
  [/option.*label.*required/i, '選項文字不得為空'],
  [/option.*id.*required/i, '選項代碼不得為空'],
  [/at least one matrix row/i, '矩陣至少需要一列'],
  [/at least one matrix col/i, '矩陣至少需要一欄'],
  [/required/i, '此欄位為必填'],
  [/max.*255/i, '字數超過上限（255 字）'],
  [/invalid jump action/i, '跳題動作設定無效'],
  [/backward jump/i, '不可跳回前面的頁面'],
  [/target page does not exist/i, '跳題目標頁面不存在'],
  [/welcome.*required/i, '歡迎頁不可設定必填題'],
  [/thank.*required/i, '感謝頁不可設定必填題'],
];

function translateMessage(msg: string): string {
  for (const [pattern, replacement] of MSG_MAP) {
    if (typeof pattern === 'string' ? msg.includes(pattern) : pattern.test(msg)) return replacement;
  }
  return msg;
}

function readableFieldLabel(fieldName: string | null): string {
  if (!fieldName) return '';

  const showIfCondition = fieldName.match(/^show_if\.conditions\.(\d+)\.(field_key|value)$/);
  if (showIfCondition) {
    const conditionNumber = Number(showIfCondition[1]) + 1;
    const target = showIfCondition[2] === 'field_key' ? '目標題目' : '輸入值';

    return `顯示條件 ${conditionNumber} 的${target}`;
  }

  return FIELD_LABELS[fieldName.split('.')[0]] ?? fieldName;
}

function parseErrorKey(key: string, messages: string[]): ParsedError {
  const translatedMessages = messages.map(translateMessage);
  const base: ParsedError = {
    raw: key, messages, translatedMessages,
    pageIndex: null, elementIndex: null, questionPageNumber: null,
    pageId: null, elementId: null, fieldName: null,
    pageLabel: '', elementNumber: null, elementLabel: '', fieldLabel: '',
  };

  const m = key.match(/^pages\.(\d+)(?:\.elements\.(\d+))?(?:\.(.+))?$/);
  if (!m) return base;

  const pageIndex = parseInt(m[1]);
  const elementIndex = m[2] !== undefined ? parseInt(m[2]) : null;
  const fieldName = m[3] ?? null;

  const pages = store.schema?.pages ?? [];
  const page = pages[pageIndex];
  const element = elementIndex !== null ? page?.elements[elementIndex] : null;
  const pageId = page?.id ?? null;
  const elementId = element?.id ?? null;

  const questionPageNumber = (() => {
    let n = 0;
    for (let i = 0; i <= pageIndex && i < pages.length; i++) {
      if ((pages[i]?.kind ?? 'question') === 'question') n++;
    }
    return (page?.kind ?? 'question') === 'question' ? n : null;
  })();

  const pageKind = page?.kind ?? 'question';
  let pageLabel = '';
  if (pageKind === 'welcome') pageLabel = '歡迎頁';
  else if (pageKind === 'thank_you') pageLabel = '感謝頁';
  else if (questionPageNumber !== null) pageLabel = `第 ${questionPageNumber} 頁`;
  else pageLabel = `頁面 ${pageIndex + 1}`;

  const elementNumber = elementId ? questionNumberMap.value[elementId] ?? null : null;
  const elementLabel = element?.label ? `「${element.label}」` : elementNumber !== null ? `第 ${elementNumber} 題` : '';
  const fLabel = readableFieldLabel(fieldName);

  return { raw: key, messages, translatedMessages, pageIndex, elementIndex, questionPageNumber, pageId, elementId, fieldName, pageLabel, elementNumber, elementLabel, fieldLabel: fLabel };
}

const parsedErrors = computed<ParsedError[]>(() =>
  Object.entries(store.validationErrors).map(([key, msgs]) => parseErrorKey(key, msgs)),
);
const validationErrorTitle = computed(() => {
  const action = store.publishError ? '發布' : '儲存';
  return `${action}失敗，請修正以下 ${parsedErrors.value.length} 個問題`;
});

const errorElementIds = computed<Set<string>>(() => {
  const ids = new Set<string>();
  for (const e of parsedErrors.value) { if (e.elementId) ids.add(e.elementId); }
  return ids;
});

const errorPageIds = computed<Set<string>>(() => {
  const ids = new Set<string>();
  for (const e of parsedErrors.value) { if (e.pageId) ids.add(e.pageId); }
  return ids;
});

const questionPagesList = computed({
  get: () => store.questionPages,
  set: (pages: SurveyPage[]) => {
    if (!store.schema) return;
    store.schema.pages = [
      ...(store.welcomePage ? [store.welcomePage] : []),
      ...pages.map((p) => ({ ...p, kind: 'question' as const })),
      ...(store.thankYouPage ? [store.thankYouPage] : []),
    ];
  },
});

// ── Preview logic ───────────────────────────────────────────────────────────
function previewAnswerValue(fieldKey: string): unknown {
  const element = store.allElements.find((candidate) => candidate.field_key === fieldKey);
  if (!element) return null;

  if (element.type === 'multiple_choice') {
    const selected = previewSelections.value[element.id];
    return selected instanceof Set ? [...selected] : [];
  }
  if (element.type === 'nps') return previewNps.value[element.id] ?? null;
  if (element.type === 'rating') return previewRatings.value[element.id] ?? null;
  if (['short_text', 'long_text', 'date', 'time', 'number', 'linear_scale'].includes(element.type)) {
    return previewTextValues.value[element.id] ?? null;
  }
  if (element.type === 'constant_sum') return previewConstantSumValues.value[element.id] ?? {};
  return previewSelections.value[element.id] ?? null;
}

function previewValueMatches(current: unknown, expected: unknown): boolean {
  if (Array.isArray(current)) return current.includes(expected);
  return String(current ?? '') === String(expected ?? '');
}

function previewConditionPasses(condition: Condition): boolean {
  const current = previewAnswerValue(condition.field_key);
  const expected = condition.value;
  const op = condition.op ?? 'equals';

  if (op === 'not_equals') return !previewValueMatches(current, expected);
  if (op === 'contains') return previewValueMatches(current, expected) || String(current ?? '').includes(String(expected ?? ''));
  if (op === 'not_contains') return !(previewValueMatches(current, expected) || String(current ?? '').includes(String(expected ?? '')));
  if (op === 'greater_than') return Number(current) > Number(expected);
  if (op === 'less_than') return Number(current) < Number(expected);
  if (op === 'between') {
    const range = expected as { min?: unknown; max?: unknown } | unknown[];
    const min = Array.isArray(range) ? range[0] : range?.min;
    const max = Array.isArray(range) ? range[1] : range?.max;
    return Number(current) >= Number(min) && Number(current) <= Number(max);
  }
  if (op === 'is_empty') return current === null || current === '' || (Array.isArray(current) && current.length === 0);
  if (op === 'is_not_empty') return !(current === null || current === '' || (Array.isArray(current) && current.length === 0));
  return previewValueMatches(current, expected);
}

function previewElementVisible(element: SurveyElement): boolean {
  const conditions = element.show_if?.conditions ?? (
    element.show_if_field_key
      ? [{ field_key: element.show_if_field_key, op: 'equals' as const, value: element.show_if_value }]
      : []
  );
  if (conditions.length === 0) return true;
  return (element.show_if?.logic ?? 'and') === 'or'
    ? conditions.some(previewConditionPasses)
    : conditions.every(previewConditionPasses);
}

function clearPreviewAnswersForPage(page: SurveyPage) {
  const selections = { ...previewSelections.value };
  const textValues = { ...previewTextValues.value };
  const constantSumValues = { ...previewConstantSumValues.value };
  const matrixSelections = { ...previewMatrixSelections.value };
  const cascade = { ...previewCascade.value };
  const addressValues = { ...previewAddressValues.value };
  const rankingOrders = { ...previewRankingOrders.value };
  const fileNames = { ...previewFileNames.value };
  const signatures = { ...previewSignatures.value };
  const ratings = { ...previewRatings.value };
  const ratingHover = { ...previewRatingHover.value };
  const nps = { ...previewNps.value };

  for (const element of page.elements) {
    delete selections[element.id];
    delete textValues[element.id];
    delete constantSumValues[element.id];
    delete matrixSelections[element.id];
    delete cascade[element.id];
    delete addressValues[element.id];
    delete rankingOrders[element.id];
    delete fileNames[element.id];
    delete signatures[element.id];
    delete ratings[element.id];
    delete ratingHover[element.id];
    delete nps[element.id];
  }

  previewSelections.value = selections;
  previewTextValues.value = textValues;
  previewConstantSumValues.value = constantSumValues;
  previewMatrixSelections.value = matrixSelections;
  previewCascade.value = cascade;
  previewAddressValues.value = addressValues;
  previewRankingOrders.value = rankingOrders;
  previewFileNames.value = fileNames;
  previewSignatures.value = signatures;
  previewRatings.value = ratings;
  previewRatingHover.value = ratingHover;
  previewNps.value = nps;
}

function clearSkippedPreviewAnswers(currentPageId: string, targetPageId: string) {
  const pages = store.schema?.pages ?? [];
  const currentIndex = pages.findIndex((page) => page.id === currentPageId);
  const targetIndex = pages.findIndex((page) => page.id === targetPageId);
  if (currentIndex < 0 || targetIndex <= currentIndex + 1) return;
  for (const skippedPage of pages.slice(currentIndex + 1, targetIndex)) {
    clearPreviewAnswersForPage(skippedPage);
  }
}

function previewNavigateTo(targetPageId: string) {
  const currentPageId = store.selectedPageId;
  if (currentPageId && currentPageId !== targetPageId) {
    clearSkippedPreviewAnswers(currentPageId, targetPageId);
    previewPageHistory.value = [...previewPageHistory.value, currentPageId];
  }
  store.selectedPageId = targetPageId;
}

function previewSelectedPageAction(): SurveyOptionAction | null {
  for (const element of store.selectedPage?.elements ?? []) {
    if (element.type !== 'single_choice') continue;
    const selected = previewSelections.value[element.id];
    if (typeof selected !== 'string') continue;
    const action = element.options.find((option) => option.value === selected)?.action;
    if (action && action.type !== 'next_page') return action;
  }
  return null;
}

function previewApplyAction(action: SurveyOptionAction): boolean {
  if (action.type === 'end_survey') { previewEnded.value = true; return true; }
  if (action.type === 'go_to_page' && action.target_page_id) {
    previewNavigateTo(action.target_page_id);
    return true;
  }
  return false;
}

function previewSelectOption(el: SurveyElement, val: string) {
  previewSelections.value = { ...previewSelections.value, [el.id]: val };
  const action = el.options.find((o) => o.value === val)?.action;
  if (action) previewApplyAction(action);
}

function previewUpdateTextValue(elementId: string, value: string) {
  previewTextValues.value = { ...previewTextValues.value, [elementId]: value };
}

function previewUpdateConstantSumValue(elementId: string, optionId: string, value: string) {
  previewConstantSumValues.value = {
    ...previewConstantSumValues.value,
    [elementId]: { ...(previewConstantSumValues.value[elementId] ?? {}), [optionId]: value },
  };
}

function constantSumTotal(element: SurveyElement): number | null {
  const total = Number((element.settings as Record<string, unknown>)?.total);

  return Number.isFinite(total) ? total : null;
}

function constantSumValue(elementId: string, optionId: string): string {
  return previewConstantSumValues.value[elementId]?.[optionId] ?? '';
}

function constantSumCurrent(element: SurveyElement): number {
  return previewOptions(element).reduce((sum, option) => {
    const value = Number(constantSumValue(element.id, option.id));

    return Number.isFinite(value) ? sum + value : sum;
  }, 0);
}

function formatSurveyNumber(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/\.?0+$/, '');
}

function constantSumStatus(element: SurveyElement): 'neutral' | 'matched' | 'over' | 'under' {
  const total = constantSumTotal(element);

  if (total === null) return 'neutral';

  const current = constantSumCurrent(element);
  const diff = current - total;

  if (Math.abs(diff) <= 0.00001) return 'matched';

  return diff > 0 ? 'over' : 'under';
}

function constantSumStatusText(element: SurveyElement): string {
  const total = constantSumTotal(element);

  if (total === null) return '尚未設定合計目標';

  const current = constantSumCurrent(element);
  const diff = total - current;

  if (Math.abs(diff) <= 0.00001) return '合計符合目標';

  if (diff > 0) return `剩餘 ${formatSurveyNumber(diff)}`;

  return `超出 ${formatSurveyNumber(Math.abs(diff))}`;
}

function previewLinearScaleValue(element: SurveyElement): string | number {
  return previewTextValues.value[element.id] ?? Number((element.settings as Record<string, unknown>)?.min ?? 1);
}

function linearScaleFillPercent(element: SurveyElement, value: string | number = previewLinearScaleValue(element)): string {
  const settings = element.settings as Record<string, unknown>;
  const min = Number(settings?.min ?? 1);
  const max = Number(settings?.max ?? 5);
  const numericValue = Number(value);
  if (!Number.isFinite(min) || !Number.isFinite(max) || max <= min || !Number.isFinite(numericValue)) return '0%';
  const percent = Math.min(100, Math.max(0, ((numericValue - min) / (max - min)) * 100));
  return `${percent}%`;
}

function defaultLinearScaleValue(element: SurveyElement): string | number {
  const settings = element.settings as Record<string, unknown>;
  return settings?.default_value as string | number | undefined ?? Number(settings?.min ?? 1);
}

function previewSelectRating(elementId: string, score: number) {
  const selected = previewRatings.value[elementId] === score ? null : score;
  previewRatings.value = {
    ...previewRatings.value,
    [elementId]: selected,
  };
  previewRatingHover.value = { ...previewRatingHover.value, [elementId]: 0 };
  if (selected) {
    previewRatingPop.value = { ...previewRatingPop.value, [elementId]: score };
    setTimeout(() => {
      if (previewRatingPop.value[elementId] === score) {
        previewRatingPop.value = { ...previewRatingPop.value, [elementId]: 0 };
      }
    }, 180);
  }
}

function previewRatingDisplayValue(elementId: string): number {
  const hover = previewRatingHover.value[elementId] ?? 0;
  return hover > 0 ? hover : previewRatings.value[elementId] ?? 0;
}

function previewRatingIsHovered(elementId: string, score: number): boolean {
  const hover = previewRatingHover.value[elementId] ?? 0;
  return hover > 0 && score <= hover;
}

function previewRatingIsPopping(elementId: string, score: number): boolean {
  return previewRatingPop.value[elementId] === score;
}

// 題號：只對非內容元件（排除標題/說明文字/分隔線/引言）計數，並跨頁累加，與正式填寫頁 $questionNo 邏輯一致
const questionNumberMap = computed<Record<string, number>>(() => {
  const map: Record<string, number> = {};
  let n = 0;
  for (const page of store.schema?.pages ?? []) {
    if (page.kind === 'welcome' || page.kind === 'thank_you') continue;
    for (const el of page.elements ?? []) {
      if (isContentBlockType(el.type)) continue;
      n += 1;
      map[el.id] = n;
    }
  }
  return map;
});

const previewIsLastPage = computed(() => {
  const pages = store.schema?.pages ?? [];
  const idx = pages.findIndex((p) => p.id === store.selectedPageId);
  if (idx === -1) return false;
  const current = pages[idx];
  if (current.kind === 'welcome' || current.kind === 'thank_you') return false;
  const next = pages[idx + 1];
  return !next || next.kind === 'thank_you';
});
const previewQuestionPages = computed(() =>
  (store.schema?.pages ?? []).filter((page) => (page.kind ?? 'question') === 'question'),
);
const previewCurrentQuestionPageIndex = computed(() =>
  previewQuestionPages.value.findIndex((page) => page.id === store.selectedPageId),
);
const previewShowsProgress = computed(() =>
  (store.schema?.settings?.progress?.mode ?? 'bar') !== 'none'
    && previewQuestionPages.value.length > 0
    && previewCurrentQuestionPageIndex.value >= 0,
);
const previewProgressWidth = computed(() =>
  `${((previewCurrentQuestionPageIndex.value + 1) / previewQuestionPages.value.length) * 100}%`,
);
const previewPrimaryColor = computed(() => {
  const overridePrimary = store.schema?.theme_overrides?.primary;
  if (typeof overridePrimary === 'string' && overridePrimary !== '') {
    return overridePrimary;
  }

  const theme = store.themes.find((candidate) => String(candidate.id) === String(store.schema?.theme_id));
  const themePrimary = theme?.tokens?.primary;

  return typeof themePrimary === 'string' && themePrimary !== '' ? themePrimary : '#6366f1';
});
const previewThemeVars = computed(() => {
  const theme = store.themes.find((candidate) => String(candidate.id) === String(store.schema?.theme_id));
  const value = (key: string, fallback: string): string => {
    const override = store.schema?.theme_overrides?.[key];
    if (typeof override === 'string' && override !== '') {
      return override;
    }

    const token = theme?.tokens?.[key];

    return typeof token === 'string' && token !== '' ? token : fallback;
  };

  return {
    '--survey-primary': previewPrimaryColor.value,
    '--survey-accent': value('accent', '#f59e0b'),
    '--survey-background': value('background', '#ffffff'),
    '--survey-surface': value('surface', '#f9fafb'),
    '--survey-text': value('text', '#111827'),
    '--survey-text-muted': value('text_muted', '#6b7280'),
    '--survey-border': value('border', '#e5e7eb'),
    '--survey-font': value('font_family', 'system-ui, sans-serif'),
    '--survey-radius': value('radius', '0.5rem'),
    '--sb-preview-progress-primary': previewPrimaryColor.value,
  };
});
const previewHasTerms = computed(() => !!store.schema?.settings?.terms_text);
const previewSubmitDisabled = computed(() => previewIsLastPage.value && previewHasTerms.value && !previewTermsAccepted.value);

function previewGoNext() {
  if (previewIsLastPage.value && previewSubmitDisabled.value) return;
  const action = previewSelectedPageAction();
  if (action && previewApplyAction(action)) return;
  const pages = store.schema?.pages ?? [];
  const idx = pages.findIndex((p) => p.id === store.selectedPageId);
  if (idx < pages.length - 1) { previewNavigateTo(pages[idx + 1].id); }
  else { previewEnded.value = true; }
}

function previewSelectMatrixSingle(elementId: string, rowId: string, colId: string) {
  previewMatrixSelections.value = {
    ...previewMatrixSelections.value,
    [elementId]: { ...(previewMatrixSelections.value[elementId] ?? {}), [rowId]: colId },
  };
}

function previewToggleMatrixMulti(elementId: string, rowId: string, colId: string) {
  const cur = ((previewMatrixSelections.value[elementId] ?? {})[rowId] as string[]) ?? [];
  const next = cur.includes(colId) ? cur.filter((c) => c !== colId) : [...cur, colId];
  previewMatrixSelections.value = {
    ...previewMatrixSelections.value,
    [elementId]: { ...(previewMatrixSelections.value[elementId] ?? {}), [rowId]: next },
  };
}

function previewMatrixSingleSelected(elementId: string, rowId: string, colId: string) {
  return ((previewMatrixSelections.value[elementId] ?? {})[rowId] as string) === colId;
}

function previewMatrixMultiSelected(elementId: string, rowId: string, colId: string) {
  const val = (previewMatrixSelections.value[elementId] ?? {})[rowId] as string[];
  return Array.isArray(val) && val.includes(colId);
}

function previewSelectNps(elementId: string, score: number) {
  previewNps.value = {
    ...previewNps.value,
    [elementId]: previewNps.value[elementId] === score ? null : score,
  };
}

function previewToggleCheckbox(elId: string, val: string) {
  const cur = previewSelections.value[elId];
  const set = cur instanceof Set ? new Set(cur) : new Set<string>();
  if (set.has(val)) set.delete(val); else set.add(val);
  previewSelections.value = { ...previewSelections.value, [elId]: set };
}

function previewGoPrev() {
  const previousPageId = previewPageHistory.value.at(-1);
  if (previousPageId) {
    previewPageHistory.value = previewPageHistory.value.slice(0, -1);
    store.selectedPageId = previousPageId;
    return;
  }
  const pages = store.schema?.pages ?? [];
  const idx = pages.findIndex((p) => p.id === store.selectedPageId);
  if (idx > 0) { store.selectedPageId = pages[idx - 1].id; }
}

function resetPreview() {
  previewEnded.value = false;
  previewSelections.value = {};
  previewTextValues.value = {};
  previewConstantSumValues.value = {};
  previewMatrixSelections.value = {};
  previewCascade.value = {};
  previewAddressValues.value = {};
  previewRankingOrders.value = {};
  previewFileNames.value = {};
  previewFileDragOver.value = {};
  previewSignatures.value = {};
  previewRatings.value = {};
  previewRatingHover.value = {};
  previewNps.value = {};
  previewPageHistory.value = [];
}

function previewRankingOrder(element: SurveyElement): SurveyOption[] {
  const displayOptions = previewOptions(element);
  const order = previewRankingOrders.value[element.id] ?? displayOptions.map((option) => option.value);
  const optionMap = new Map(element.options.map((option) => [option.value, option]));
  return order.map((value) => optionMap.get(value)).filter((option): option is SurveyOption => option !== undefined);
}

function previewMoveRanking(element: SurveyElement, optionValue: string, direction: -1 | 1) {
  const order = previewRankingOrder(element).map((option) => option.value);
  const index = order.indexOf(optionValue);
  const target = index + direction;
  if (index === -1 || target < 0 || target >= order.length) return;
  [order[index], order[target]] = [order[target], order[index]];
  previewRankingOrders.value = { ...previewRankingOrders.value, [element.id]: order };
}

function previewUpdateAddress(elementId: string, key: string, value: string) {
  previewAddressValues.value = {
    ...previewAddressValues.value,
    [elementId]: { ...(previewAddressValues.value[elementId] ?? {}), [key]: value },
  };
}

function previewFileSelected(elementId: string, event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0];
  previewFileNames.value = { ...previewFileNames.value, [elementId]: file?.name ?? '' };
}

type PreviewFileFormatGroup = {
  label: string;
  extensions: string[];
};

const previewFileFormatGroups: PreviewFileFormatGroup[] = [
  { label: '文件', extensions: ['pdf', 'doc', 'docx', 'txt', 'rtf'] },
  { label: '簡報', extensions: ['ppt', 'pptx'] },
  { label: '試算表', extensions: ['xls', 'xlsx', 'csv'] },
  { label: '圖片', extensions: ['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'] },
  { label: '影片', extensions: ['mpg', 'mpeg', 'mp4', 'mov', 'avi', 'wmv', 'mkv', 'webm'] },
  { label: '音樂', extensions: ['mp3', 'wav', 'aac', 'm4a', 'ogg', 'flac'] },
];

function previewAllowedFileExtensions(element: SurveyElement): string[] {
  const allowed = (element.settings as Record<string, unknown> | undefined)?.allowed_mimes;

  return Array.isArray(allowed)
    ? allowed.map((extension) => String(extension).trim().replace(/^\./, '')).filter(Boolean)
    : [];
}

function previewFileAccept(element: SurveyElement): string | undefined {
  const extensions = previewAllowedFileExtensions(element);

  return extensions.length > 0 ? extensions.map((extension) => `.${extension}`).join(',') : undefined;
}

function previewFileSizeLabel(element: SurveyElement): string {
  const maxSize = Number((element.settings as Record<string, unknown> | undefined)?.max_size_mb ?? 10);

  return maxSize > 0 ? `${maxSize} MB以下` : '未限制大小';
}

function previewFileFormatLabel(element: SurveyElement): string {
  const selected = previewAllowedFileExtensions(element);

  if (selected.length === 0) return '不限';

  const selectedSet = new Set(selected);
  const labels = previewFileFormatGroups
    .filter((group) => group.extensions.some((extension) => selectedSet.has(extension)))
    .map((group) => group.label);

  const known = new Set(
    previewFileFormatGroups
      .filter((group) => group.extensions.some((extension) => selectedSet.has(extension)))
      .flatMap((group) => group.extensions),
  );
  const custom = selected.filter((extension) => !known.has(extension));

  return [...new Set([...labels, ...custom])].join('、');
}

function previewChooseFile(elementId: string) {
  document.querySelector<HTMLInputElement>(`[data-preview-file-input="${elementId}"]`)?.click();
}

function previewFileDropped(elementId: string, event: DragEvent) {
  previewFileDragOver.value = { ...previewFileDragOver.value, [elementId]: false };
  const file = event.dataTransfer?.files?.[0];
  previewFileNames.value = { ...previewFileNames.value, [elementId]: file?.name ?? '' };
}

// ── Options editing ─────────────────────────────────────────────────────────
function addOption(el: SurveyElement) {
  el.options.push({
    id: `opt_${Math.random().toString(36).slice(2, 9)}`,
    label: `選項 ${el.options.length + 1}`,
    value: `option_${Math.random().toString(36).slice(2, 9)}`,
    capacity: null,
    is_hidden: false,
  });
  store.markDirty();
}

function removeOption(el: SurveyElement, i: number) {
  el.options.splice(i, 1);
  store.markDirty();
}

function optionInputType(el: SurveyElement) {
  return el.type === 'multiple_choice' ? 'checkbox' : 'radio';
}

function contentBlockText(element: SurveyElement): string {
  return element.description || '';
}

function updateContentBlockText(element: SurveyElement, value: string) {
  element.description = value;
  element.label = contentBlockLabel(element);
  store.markDirty();
}

function contentBlockLabel(element: SurveyElement): string {
  if (element.type === 'section_title') return '標題';
  if (element.type === 'quote_block') return '引言';
  if (element.type === 'divider') return '分隔線';
  return '說明文字';
}

// ── Element selection ───────────────────────────────────────────────────────
function selectElement(qId: string) {
  store.selectElement(qId);
  if (store.rightPanelTab === 'library') store.rightPanelTab = 'properties';
}

// ── Page management ─────────────────────────────────────────────────────────
function deletePage(pageId: string) {
  if (!store.schema) return;
  const p = store.schema.pages.find((pp) => pp.id === pageId);
  if (!p || p.kind === 'welcome' || p.kind === 'thank_you') return;
  const qPages = store.schema.pages.filter((pp) => (pp.kind ?? 'question') === 'question');
  if (qPages.length <= 1) { alert('至少需要保留一個問題頁'); return; }
  const count = (p.elements ?? []).length;
  if (count > 0 && !confirm(`刪除「${p.title || '未命名頁面'}」？此頁包含 ${count} 道題目，將一併移除。`)) return;
  store.removePage(pageId);
}

function selectPage(pageId: string) {
  store.selectedPageId = pageId;
  store.selectedElementId = null;
}

// ── Drag-and-drop ───────────────────────────────────────────────────────────
function onDragStart(e: DragEvent, qId: string) {
  dragQId.value = qId;
  if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', qId); }
}

function onDragEnd() { dragQId.value = null; dropTarget.value = null; }

function onPageDragStart(e: DragEvent, pageId: string) {
  dragPageId.value = pageId;

  if (e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('application/x-survey-page', pageId);
  }
}

function onPageDragEnd() {
  dragPageId.value = null;
  pageDropTarget.value = null;
}

function onPageDragOver(e: DragEvent, pageId: string) {
  if (!dragPageId.value || dragPageId.value === pageId) return;

  e.preventDefault();
  e.stopPropagation();

  const rect = (e.currentTarget as HTMLElement).getBoundingClientRect();
  const position = e.clientX < rect.left + (rect.width / 2) ? 'before' : 'after';
  pageDropTarget.value = { pageId, position };

  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
}

function onPageDragLeave(e: DragEvent, pageId: string) {
  if ((e.currentTarget as HTMLElement).contains(e.relatedTarget as Node | null)) return;
  if (pageDropTarget.value?.pageId === pageId) pageDropTarget.value = null;
}

function onPageDrop(e: DragEvent, pageId: string) {
  if (!dragPageId.value || !pageDropTarget.value) return;

  e.preventDefault();
  e.stopPropagation();

  store.moveQuestionPage(dragPageId.value, pageId, pageDropTarget.value.position);
  onPageDragEnd();
}

function onDragOverZone(e: DragEvent, pageId: string, index: number) {
  if (!dragQId.value) return;
  e.preventDefault();
  dropTarget.value = { type: 'zone', pageId, index };
}

function onDragOverTab(e: DragEvent, pageId: string) {
  if (!dragQId.value) return;
  e.preventDefault();
  if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
  dropTarget.value = { type: 'tab', pageId };
}

function onDragLeave() { dropTarget.value = null; }

function onDropZone(e: DragEvent, pageId: string, index: number) {
  e.preventDefault(); e.stopPropagation();
  if (!dragQId.value) return;
  moveQuestion(dragQId.value, pageId, index);
  dragQId.value = null; dropTarget.value = null;
}

function onDropTab(e: DragEvent, pageId: string) {
  e.preventDefault();
  if (!dragQId.value || !store.schema) return;
  const tp = store.schema.pages.find((p) => p.id === pageId);
  if (!tp) return;
  moveQuestion(dragQId.value, pageId, tp.elements.length);
  store.selectedPageId = pageId;
  dragQId.value = null; dropTarget.value = null;
}

function onPageTabDragOver(e: DragEvent, pageId: string) {
  if (dragPageId.value) {
    onPageDragOver(e, pageId);

    return;
  }

  onDragOverTab(e, pageId);
}

function onPageTabDragLeave(e: DragEvent, pageId: string) {
  if (dragPageId.value) {
    onPageDragLeave(e, pageId);

    return;
  }

  onDragLeave();
}

function onPageTabDrop(e: DragEvent, pageId: string) {
  if (dragPageId.value) {
    onPageDrop(e, pageId);

    return;
  }

  onDropTab(e, pageId);
}

function moveQuestion(qId: string, targetPageId: string, targetIndex: number) {
  if (!store.schema) return;
  let movingEl: SurveyElement | null = null;
  for (const page of store.schema.pages) {
    const i = page.elements.findIndex((e) => e.id === qId);
    if (i >= 0) { movingEl = { ...page.elements[i] }; page.elements.splice(i, 1); break; }
  }
  if (!movingEl) return;
  const tp = store.schema.pages.find((p) => p.id === targetPageId);
  if (!tp) return;
  tp.elements.splice(Math.max(0, Math.min(targetIndex, tp.elements.length)), 0, movingEl);
  store.markDirty();
}

function isZoneActive(pageId: string, index: number) {
  return dropTarget.value?.type === 'zone' && (dropTarget.value as any).pageId === pageId && (dropTarget.value as any).index === index;
}

function isTabTarget(pageId: string) {
  return dropTarget.value?.type === 'tab' && (dropTarget.value as any).pageId === pageId;
}

function isPageDropTarget(pageId: string, position: 'before' | 'after') {
  return pageDropTarget.value?.pageId === pageId && pageDropTarget.value.position === position;
}

function textInputType(element: SurveyElement) {
  const inputFormat = String((element.settings as any)?.input_format ?? '');
  if (element.type === 'email' || inputFormat === 'email') return 'email';
  if (element.type === 'phone' || inputFormat === 'mobile_tw') return 'tel';
  if (element.type === 'date') return 'date';
  if (element.type === 'time') return 'time';
  return 'text';
}
</script>

<template>
  <!-- ── Validation errors ── -->
  <div v-if="parsedErrors.length > 0" class="sb-errors">
    <div class="sb-errors-header">
      <span class="sb-errors-icon">⚠</span>
      <span class="sb-errors-title">{{ validationErrorTitle }}</span>
    </div>
    <ul class="sb-errors-list">
      <li
        v-for="(err, i) in parsedErrors"
        :key="i"
        class="sb-errors-item"
        :class="{ clickable: err.pageId }"
        @click="err.pageId && (store.selectedPageId = err.pageId, err.elementId && (store.selectedElementId = err.elementId, store.rightPanelTab = 'properties'))"
      >
        <span class="sb-errors-location">
          <span v-if="err.pageLabel" class="sb-errors-badge">{{ err.pageLabel }}</span>
          <span v-if="err.elementLabel" class="sb-errors-element-name">{{ err.elementLabel }}</span>
          <span v-if="err.fieldLabel" class="sb-errors-field">{{ err.fieldLabel }}</span>
        </span>
        <span class="sb-errors-messages">{{ err.translatedMessages.join('；') }}</span>
      </li>
    </ul>
  </div>

  <!-- ── Loading ── -->
  <div v-if="store.isLoading" class="sb-loading">
    載入問卷設計中…
  </div>

  <!-- ── Main ── -->
  <div v-else-if="store.schema" class="sb-body">

    <!-- Left rail -->
    <aside v-if="!store.isPreviewMode" class="sb-rail">
      <button class="sb-rail-btn active" title="編輯題目">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        <span class="sb-rail-tip">編輯題目</span>
      </button>
      <button class="sb-rail-btn" title="問卷設定" @click="store.showSettingsModal = true">
        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
        <span class="sb-rail-tip">問卷設定</span>
      </button>
    </aside>

    <!-- ── Canvas ── -->
    <main class="sb-canvas">
      <div class="sb-canvas-inner">

        <!-- Page tabs (sticky) -->
        <div v-if="!store.isPreviewMode" class="sb-page-tabs-wrap">
          <div class="sb-page-tabs">
            <button
              v-if="store.welcomePage"
              class="sb-page-tab welcome"
              :class="{ active: store.selectedPageId === store.welcomePage.id }"
              @click="selectPage(store.welcomePage.id)"
            >
              <span class="sb-page-tab-num">歡迎</span>
            </button>

            <div
              v-for="(page, i) in store.questionPages"
              :key="page.id"
              class="sb-page-tab"
              :class="{
                active: store.selectedPageId === page.id,
                'drop-target': isTabTarget(page.id),
                'page-drop-before': isPageDropTarget(page.id, 'before'),
                'page-drop-after': isPageDropTarget(page.id, 'after'),
                'is-page-dragging': dragPageId === page.id,
                'has-error': errorPageIds.has(page.id),
              }"
              role="button"
              tabindex="0"
              draggable="true"
              @click="selectPage(page.id)"
              @keydown.enter.prevent="selectPage(page.id)"
              @keydown.space.prevent="selectPage(page.id)"
              @dragstart="onPageDragStart($event, page.id)"
              @dragend="onPageDragEnd"
              @dragover="onPageTabDragOver($event, page.id)"
              @dragleave="onPageTabDragLeave($event, page.id)"
              @drop="onPageTabDrop($event, page.id)"
            >
              <span class="sb-page-tab-num" :class="{ 'error-num': errorPageIds.has(page.id) }">P{{ String(i + 1).padStart(2, '0') }}</span>
              <span>{{ page.title || '未命名頁面' }}</span>
              <span v-if="errorPageIds.has(page.id)" class="sb-page-tab-error-dot" title="此頁有驗證錯誤">!</span>
              <span class="sb-page-tab-count">{{ page.elements.length }}</span>
              <button
                class="sb-page-tab-close"
                type="button"
                @click.stop="deletePage(page.id)"
                title="刪除此頁"
              >×</button>
            </div>

            <button
              v-if="store.thankYouPage"
              class="sb-page-tab thanks"
              :class="{ active: store.selectedPageId === store.thankYouPage.id }"
              @click="selectPage(store.thankYouPage.id)"
            >
              <span class="sb-page-tab-num">感謝</span>
            </button>
          </div>
          <button class="sb-page-tab-add" type="button" @click="store.addPage()">
            + 新增頁面
          </button>
        </div>

        <!-- ── Preview mode ── -->
        <div v-if="store.isPreviewMode" class="sb-preview survey-preview-surface" :class="store.isMobilePreview ? 'mobile' : ''" :style="previewThemeVars">
          <div class="sb-preview-survey-header">
            <h1 class="sb-preview-survey-title">{{ store.surveyTitle }}</h1>
            <p v-if="store.schema?.settings?.description" class="sb-preview-survey-desc">{{ store.schema.settings.description }}</p>
          </div>
          <div v-if="previewEnded" class="sb-preview-end">
            <p class="sb-preview-end-title">問卷已結束</p>
            <p class="sb-preview-end-sub">感謝您的填答</p>
            <button type="button" class="sb-btn" @click="resetPreview()">重置預覽</button>
          </div>
          <template v-else>
            <div
              v-if="previewShowsProgress"
              class="sb-preview-progress"
            >
              <div :style="{ width: previewProgressWidth }" />
            </div>
            <div class="sb-preview-card">
              <!-- Welcome page rich content + CTA -->
              <template v-if="store.selectedPage?.kind === 'welcome'">
                <div
                  v-if="store.selectedPage.welcome_settings?.content"
                  class="sb-preview-rich survey-rich-content"
                  v-html="store.selectedPage.welcome_settings.content"
                ></div>
                <p
                  v-if="store.schema?.settings?.progress?.show_estimated_time !== false && Number(store.selectedPage.welcome_settings?.estimated_time_minutes ?? 0) > 0"
                  class="sb-preview-estimated-time"
                >預估填寫時間：約 {{ store.selectedPage.welcome_settings?.estimated_time_minutes }} 分鐘</p>
                <div class="sb-preview-footer" style="margin-top:24px">
                  <div style="flex:1" />
                  <button type="button" class="sb-btn accent" @click="previewGoNext()">
                    {{ store.selectedPage.welcome_settings?.cta_label || '開始填寫' }}
                  </button>
                </div>
              </template>

              <!-- Thank you page rich content -->
              <template v-else-if="store.selectedPage?.kind === 'thank_you'">
                <div style="text-align:center;padding:24px 0">
                  <div
                    v-if="store.selectedPage.thank_you_settings?.message"
                    class="sb-preview-rich survey-rich-content"
                    v-html="store.selectedPage.thank_you_settings.message"
                  ></div>
                  <p v-else class="sb-preview-q-desc">感謝您的填寫！</p>
                </div>
              </template>

              <template v-else v-for="element in previewPageElements" :key="element.id">
                <template v-if="previewElementVisible(element)">
                  <div v-if="element.is_hidden" class="sb-preview-hidden">🔒 {{ element.label }}</div>
                  <section v-else-if="element.type === 'section_title'" class="survey-field">
                    <h3 class="survey-section-title">{{ contentBlockText(element) }}</h3>
                  </section>
                  <div v-else-if="element.type === 'description_block'" class="survey-field">
                    <div class="survey-description-block survey-rich-content" v-html="contentBlockText(element)"></div>
                  </div>
                  <div v-else-if="element.type === 'quote_block'" class="survey-field survey-quote-block">
                    <blockquote>{{ contentBlockText(element) }}</blockquote>
                  </div>
                  <div v-else-if="element.type === 'divider'" class="survey-field survey-divider"><hr></div>
                  <div v-else class="survey-field survey-field-card">
                    <p class="survey-field-label"><span v-if="store.schema?.settings?.show_question_numbers !== false && questionNumberMap[element.id] !== undefined" class="sb-preview-q-num">{{ questionNumberMap[element.id] }}. </span>{{ element.label }}<span v-if="element.required" class="survey-field-required">*</span></p>
                    <p v-if="element.description" class="survey-field-description">{{ element.description }}</p>
                    <div v-if="element.type === 'single_choice'" class="survey-choices">
                      <label v-for="opt in previewOptions(element)" :key="opt.id"
                        class="survey-choice-label"
                        :class="{ selected: previewSelections[element.id] === opt.value }"
                      >
                        <input class="survey-choice-input" type="radio" :name="element.id" :value="opt.value" :checked="previewSelections[element.id] === opt.value" @change="previewSelectOption(element, opt.value)" />
                        <span>{{ opt.label }}</span>
                      </label>
                    </div>
                    <div v-else-if="element.type === 'multiple_choice'" class="survey-choices">
                      <label v-for="opt in previewOptions(element)" :key="opt.id"
                        class="survey-choice-label"
                        :class="{ selected: previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value) }"
                        @click.prevent="previewToggleCheckbox(element.id, opt.value)"
                      >
                        <input class="survey-choice-input" type="checkbox" :name="element.id" :checked="previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value)" @change.prevent />
                        <span>{{ opt.label }}</span>
                      </label>
                    </div>
                    <select
                      v-else-if="element.type === 'select'"
                      :value="previewSelections[element.id] ?? ''"
                      class="survey-select"
                      @change="previewSelectOption(element, ($event.target as HTMLSelectElement).value)"
                    >
                      <option value="">請選擇</option>
                      <option v-for="opt in previewOptions(element)" :key="opt.id" :value="opt.value">{{ opt.label }}</option>
                    </select>
                    <input
                      v-else-if="element.type === 'short_text' || element.type === 'email' || element.type === 'phone' || element.type === 'date' || element.type === 'time'"
                      :type="textInputType(element)"
                      :inputmode="(element.settings as any)?.input_mode ?? (((element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone') ? 'numeric' : undefined)"
                      :minlength="(element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone' ? 10 : undefined"
                      :maxlength="(element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone' ? 10 : undefined"
                      :pattern="(element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone' ? '09[0-9]{8}' : undefined"
                      :placeholder="element.placeholder ?? ''"
                      :value="previewTextValues[element.id] ?? ''"
                      class="survey-input"
                      @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                    />
                    <textarea
                      v-else-if="element.type === 'long_text'"
                      :placeholder="element.placeholder ?? ''"
                      :value="previewTextValues[element.id] ?? ''"
                      rows="4"
                      class="survey-textarea"
                      @input="previewUpdateTextValue(element.id, ($event.target as HTMLTextAreaElement).value)"
                    />
                    <div v-else-if="element.type === 'rating'" class="survey-rating-stars">
                      <button
                        v-for="n in Number((element.settings as any)?.count ?? 5)"
                        :key="n"
                        type="button"
                        class="survey-rating-star-label"
                        :class="[
                          `shape-${(element.settings as any)?.shape ?? 'star'}`,
                          {
                            filled: n <= previewRatingDisplayValue(element.id),
                            hovered: previewRatingIsHovered(element.id, n),
                            popping: previewRatingIsPopping(element.id, n),
                          },
                        ]"
                        @mouseenter="previewRatingHover = { ...previewRatingHover, [element.id]: n }"
                        @mouseleave="previewRatingHover = { ...previewRatingHover, [element.id]: 0 }"
                        @click="previewSelectRating(element.id, n)"
                      >
                        <span v-if="(element.settings as any)?.show_numbers" class="survey-rating-star-number">{{ n }}</span>
                        <span class="survey-rating-star-icon">{{ ratingShapeIcon((element.settings as any)?.shape ?? 'star') }}</span>
                      </button>
                    </div>
                    <div v-else-if="element.type === 'number'" class="sb-preview-number-row">
                      <input
                        type="number"
                        class="survey-input sb-preview-number-input"
                        :min="(element.settings as any)?.min ?? undefined"
                        :max="(element.settings as any)?.max ?? undefined"
                        :step="(element.settings as any)?.decimal_places ? Math.pow(10, -Number((element.settings as any).decimal_places)) : 1"
                        :value="previewTextValues[element.id] ?? ''"
                        placeholder="請輸入數字"
                        @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                      />
                      <span v-if="(element.settings as any)?.unit" class="sb-preview-number-unit">{{ (element.settings as any).unit }}</span>
                    </div>
                    <div v-else-if="element.type === 'linear_scale'" class="survey-linear-scale">
                      <span class="survey-linear-scale-value">{{ previewLinearScaleValue(element) }}</span>
                      <input
                        type="range"
                        class="survey-linear-scale-input"
                        :min="(element.settings as any)?.min ?? 1"
                        :max="(element.settings as any)?.max ?? 5"
                        :step="(element.settings as any)?.step ?? 1"
                        :value="previewLinearScaleValue(element)"
                        :style="{ '--survey-range-fill': linearScaleFillPercent(element) }"
                        @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                      />
                      <span v-if="(element.settings as any)?.unit" class="sb-preview-number-unit">{{ (element.settings as any).unit }}</span>
                    </div>
                    <div v-else-if="element.type === 'constant_sum'" class="survey-choices survey-constant-sum">
                      <label v-for="opt in previewOptions(element)" :key="opt.id" class="survey-choice-label survey-preview-inline-input survey-constant-sum-row">
                        <span>{{ opt.label }}</span>
                        <span class="survey-constant-sum-input-wrap">
                          <input
                            type="number"
                            class="survey-input"
                            :placeholder="String((element.settings as any)?.unit || '0')"
                            :value="constantSumValue(element.id, opt.id)"
                            @input="previewUpdateConstantSumValue(element.id, opt.id, ($event.target as HTMLInputElement).value)"
                          />
                          <span v-if="(element.settings as any)?.unit" class="survey-constant-sum-unit">{{ (element.settings as any).unit }}</span>
                        </span>
                      </label>
                      <div class="survey-constant-sum-summary" :data-status="constantSumStatus(element)">
                        <span>目前合計 {{ formatSurveyNumber(constantSumCurrent(element)) }}</span>
                        <span v-if="constantSumTotal(element) !== null">目標 {{ formatSurveyNumber(constantSumTotal(element)!) }}</span>
                        <strong>{{ constantSumStatusText(element) }}</strong>
                      </div>
                    </div>
                    <div v-else-if="element.type === 'selection_based'" class="survey-choices">
                      <p v-if="!selectionBasedSourceElement(element)" class="survey-help">請先在右側選擇來源題目。</p>
                      <p v-else-if="previewSelectionBasedOptions(element).length === 0" class="survey-help">請先回答來源題目，這裡會顯示可複選的選項。</p>
                      <template v-else>
                        <label
                          v-for="opt in previewSelectionBasedOptions(element)"
                          :key="opt.id"
                          class="survey-choice-label"
                          :class="{ selected: previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value) }"
                          @click.prevent="previewToggleCheckbox(element.id, opt.value)"
                        >
                          <input class="survey-choice-input" type="checkbox" :name="element.id" :checked="previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value)" @change.prevent />
                          <span>{{ opt.label }}</span>
                        </label>
                      </template>
                    </div>
                    <div v-else-if="element.type === 'ranking'" class="sb-preview-ranking">
                      <div
                        v-for="(opt, index) in previewRankingOrder(element)"
                        :key="opt.id"
                        class="sb-preview-ranking-item"
                      >
                        <span class="sb-preview-ranking-position">{{ index + 1 }}</span>
                        <span class="sb-preview-ranking-label">{{ opt.label }}</span>
                        <button type="button" class="sb-preview-ranking-move" :disabled="index === 0" @click="previewMoveRanking(element, opt.value, -1)">↑</button>
                        <button type="button" class="sb-preview-ranking-move" :disabled="index === element.options.length - 1" @click="previewMoveRanking(element, opt.value, 1)">↓</button>
                      </div>
                    </div>
                    <div v-else-if="element.type === 'file_upload'" class="survey-choices">
                      <input
                        type="file"
                        class="survey-file-input"
                        :data-preview-file-input="element.id"
                        :accept="previewFileAccept(element)"
                        @change="previewFileSelected(element.id, $event)"
                      />
                      <button
                        type="button"
                        class="survey-file-dropzone"
                        :class="{ 'is-dragging': previewFileDragOver[element.id], 'is-uploaded': previewFileNames[element.id] }"
                        @click="previewChooseFile(element.id)"
                        @dragenter.prevent="previewFileDragOver = { ...previewFileDragOver, [element.id]: true }"
                        @dragover.prevent="previewFileDragOver = { ...previewFileDragOver, [element.id]: true }"
                        @dragleave.prevent="previewFileDragOver = { ...previewFileDragOver, [element.id]: false }"
                        @drop.prevent="previewFileDropped(element.id, $event)"
                      >
                        <span class="survey-file-icon" aria-hidden="true">☁</span>
                        <span class="survey-file-title">選擇檔案或將檔案拖曳至此</span>
                        <span class="survey-file-limit">{{ previewFileSizeLabel(element) }}</span>
                        <span class="survey-file-format">檔案格式：{{ previewFileFormatLabel(element) }}</span>
                      </button>
                      <p v-if="previewFileNames[element.id]" class="sb-preview-help">已選擇：{{ previewFileNames[element.id] }}</p>
                    </div>
                    <div v-else-if="element.type === 'signature'" class="survey-choices">
                      <button
                        type="button"
                        class="survey-input sb-preview-signature-pad"
                        :class="{ signed: previewSignatures[element.id] }"
                        @click="previewSignatures = { ...previewSignatures, [element.id]: true }"
                      >
                        {{ previewSignatures[element.id] ? '已簽名' : '點擊模擬簽名' }}
                      </button>
                      <button
                        v-if="previewSignatures[element.id]"
                        type="button"
                        class="sb-preview-signature-clear"
                        @click="previewSignatures = { ...previewSignatures, [element.id]: false }"
                      >清除簽名</button>
                    </div>
                    <div v-else-if="element.type === 'address'" class="sb-preview-address">
                      <input
                        v-for="addressKey in ((element.settings as any)?.fields_enabled ?? ['country', 'city', 'district', 'address', 'postal_code'])"
                        :key="addressKey"
                        type="text"
                        class="survey-input"
                        :placeholder="String(addressKey)"
                        :value="previewAddressValues[element.id]?.[String(addressKey)] ?? ((String(addressKey) === 'country' && (element.settings as any)?.country_locked) ? String((element.settings as any).country_locked) : '')"
                        :disabled="String(addressKey) === 'country' && !!(element.settings as any)?.country_locked"
                        @input="previewUpdateAddress(element.id, String(addressKey), ($event.target as HTMLInputElement).value)"
                      />
                    </div>
                    <div v-else-if="element.type === 'matrix_single' || element.type === 'matrix_multi'" class="survey-preview-matrix-wrap">
                      <div class="survey-preview-matrix-scroll">
                        <table class="survey-matrix">
                          <thead>
                            <tr>
                              <th></th>
                              <th v-for="col in element.matrix_cols" :key="col.id">{{ col.label }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="row in element.matrix_rows" :key="row.id">
                              <td>{{ row.label }}</td>
                              <td
                                v-for="col in element.matrix_cols"
                                :key="col.id"
                                class="survey-preview-matrix-cell"
                                :class="{
                                  selected: element.type === 'matrix_single'
                                    ? previewMatrixSingleSelected(element.id, row.id, col.id)
                                    : previewMatrixMultiSelected(element.id, row.id, col.id)
                                }"
                                @click="element.type === 'matrix_single'
                                  ? previewSelectMatrixSingle(element.id, row.id, col.id)
                                  : previewToggleMatrixMulti(element.id, row.id, col.id)"
                              >
                                <span class="survey-preview-matrix-pip" :class="element.type === 'matrix_multi' ? 'square' : ''"></span>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <div v-else-if="element.type === 'nps'" class="survey-nps-wrap">
                      <div class="survey-nps-row">
                        <span
                          v-for="n in 11" :key="n"
                          class="survey-nps-pip"
                          :class="{
                            selected: previewNps[element.id] === n - 1,
                            red:    (element.settings as any)?.color_bands && n - 1 <= 6,
                            yellow: (element.settings as any)?.color_bands && n - 1 >= 7 && n - 1 <= 8,
                            green:  (element.settings as any)?.color_bands && n - 1 >= 9,
                          }"
                          @click="previewSelectNps(element.id, n - 1)"
                        >{{ n - 1 }}</span>
                      </div>
                      <div class="survey-nps-labels">
                        <span>{{ (element.settings as any)?.low_label || '非常不推薦' }}</span>
                        <span>{{ (element.settings as any)?.high_label || '非常推薦' }}</span>
                      </div>
                    </div>
                    <div v-else-if="element.type === 'cascade_select'" class="survey-cascade-grid">
                      <p v-if="(element.cascade_levels ?? []).length === 0" class="survey-help">請先設定層級與選項資料。</p>
                      <div
                        v-for="(lvl, li) in (element.cascade_levels ?? [])"
                        :key="lvl.id"
                        class="survey-preview-cascade-row"
                      >
                        <select
                          class="survey-select"
                          :disabled="li > 0 && !(previewCascade[element.id]?.[li - 1])"
                          :value="previewCascade[element.id]?.[li] ?? ''"
                          @change="cascadePreviewSelect(element.id, li, ($event.target as HTMLSelectElement).value)"
                        >
                          <option value="">{{ lvl.label || '請選擇' }}</option>
                          <option
                            v-for="opt in cascadePreviewLevelOptions(element, li)"
                            :key="opt.id"
                            :value="opt.id"
                          >{{ opt.label }}</option>
                        </select>
                      </div>
                    </div>
                  </div>
                </template>
              </template>
              <label v-if="previewIsLastPage && previewHasTerms" class="sb-preview-terms">
                <input type="checkbox" v-model="previewTermsAccepted" />
                <span>{{ store.schema.settings?.terms_text }}</span>
              </label>
              <div v-if="store.selectedPage?.kind !== 'welcome' && store.selectedPage?.kind !== 'thank_you'" class="sb-preview-footer">
                <button
                  v-if="store.schema.pages.findIndex(p => p.id === store.selectedPageId) > 0"
                  type="button"
                  class="sb-btn"
                  @click="previewGoPrev()"
                >← 上一頁</button>
                <div style="flex:1" />
                <button type="button" class="sb-btn accent" :disabled="previewSubmitDisabled" @click="previewGoNext()">
                  {{ previewIsLastPage ? '提交' : '下一頁 →' }}
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- ── Edit mode ── -->
        <div v-else class="sb-edit-surface survey-preview-surface" :style="previewThemeVars">
          <!-- Page header (question pages only) -->
          <div v-if="store.selectedPage?.kind !== 'welcome' && store.selectedPage?.kind !== 'thank_you'" class="sb-page-header">
            <span class="sb-page-header-num">
              第 {{ store.schema.pages.slice(0, store.schema.pages.indexOf(store.selectedPage!) + 1).filter(p => (p.kind ?? 'question') === 'question').length }} 頁
            </span>
            <input
              class="sb-page-title-input"
              :value="store.selectedPage?.title"
              placeholder="頁面標題"
              @input="store.selectedPage && store.updatePageTitle(store.selectedPage.id, ($event.target as HTMLInputElement).value)"
            />
          </div>

          <!-- Welcome / Thank You special cards -->
          <template v-if="store.selectedPage?.kind === 'welcome'">
            <div class="sb-special-card">
              <div
                v-if="store.selectedPage.welcome_settings?.content"
                class="sb-special-rich-preview survey-rich-content"
                v-html="store.selectedPage.welcome_settings.content"
              ></div>
              <div class="sb-special-cta-row">
                <input class="sb-special-cta" :value="store.selectedPage.welcome_settings?.cta_label ?? '開始填寫'" @input="store.updatePage(store.selectedPage!.id, { welcome_settings: { ...(store.selectedPage!.welcome_settings ?? {}), cta_label: ($event.target as HTMLInputElement).value } })" />
              </div>
            </div>
          </template>

          <template v-else-if="store.selectedPage?.kind === 'thank_you'">
            <div class="sb-special-card" style="text-align:center">
              <div
                v-if="store.selectedPage.thank_you_settings?.message"
                class="sb-special-rich-preview survey-rich-content"
                v-html="store.selectedPage.thank_you_settings.message"
              ></div>
              <div v-else class="sb-special-title">感謝您的填寫！</div>
            </div>
          </template>

          <!-- Question list with drop zones -->
          <template v-else-if="store.selectedPage">
            <template v-if="store.selectedPage.elements.length === 0">
              <div class="sb-empty-page">
                <div class="sb-empty-page-icon">＋</div>
                <p>從右側選擇題型加入此頁面</p>
                <button class="sb-btn" type="button" @click="store.rightPanelTab = 'library'">瀏覽題型</button>
              </div>
            </template>
            <template v-else>
              <template v-for="(element, i) in store.selectedPage.elements" :key="element.id">
                <!-- Drop zone before each card -->
                <div
                  class="sb-drop-zone"
                  :class="{ over: isZoneActive(store.selectedPage.id, i) }"
                  @dragover="onDragOverZone($event, store.selectedPage.id, i)"
                  @dragleave="onDragLeave"
                  @drop="onDropZone($event, store.selectedPage.id, i)"
                >
                  <span v-if="isZoneActive(store.selectedPage.id, i)">放開以移動至此</span>
                </div>

                <!-- Question card -->
                <article
                  class="sb-card"
                  :class="{
                    selected: store.selectedElementId === element.id,
                    'is-dragging': dragQId === element.id,
                    'is-hidden-field': element.is_hidden,
                    'has-error': errorElementIds.has(element.id),
                  }"
                  @click="selectElement(element.id)"
                >
                  <!-- Drag handle -->
                  <div
                    class="sb-card-handle"
                    draggable="true"
                    @dragstart="onDragStart($event, element.id)"
                    @dragend="onDragEnd"
                    @click.stop
                    title="拖曳調整順序"
                  >
                    <span class="sb-card-handle-dot" /><span class="sb-card-handle-dot" />
                    <span class="sb-card-handle-dot" /><span class="sb-card-handle-dot" />
                    <span class="sb-card-handle-dot" /><span class="sb-card-handle-dot" />
                  </div>

                  <!-- Card head -->
                  <div class="sb-card-head">
                    <span class="sb-type-badge" :class="typeCategory(element.type)">
                      {{ getQuestionType(element.type).icon }}
                    </span>
                    <span v-if="store.schema?.settings?.show_question_numbers !== false && questionNumberMap[element.id] !== undefined" class="sb-card-num">{{ questionNumberMap[element.id] }}</span>
                    <div class="sb-card-title-wrap">
                      <input
                        v-if="element.type === 'section_title'"
                        class="sb-card-title"
                        :class="{ empty: !element.description }"
                        :value="contentBlockText(element)"
                        placeholder="標題…"
                        @input="updateContentBlockText(element, ($event.target as HTMLInputElement).value)"
                        @click.stop
                      >
                      <div v-else-if="element.type === 'description_block'" class="sb-card-rich-editor" @click.stop>
                        <SurveyRichEditor
                          :model-value="contentBlockText(element)"
                          placeholder="說明文字…"
                          :upload-url="props.endpoints.uploadImage"
                          :csrf-token="props.csrfToken"
                          @update:model-value="updateContentBlockText(element, $event)"
                        />
                      </div>
                      <textarea
                        v-else-if="element.type === 'quote_block'"
                        class="sb-card-quote"
                        :value="contentBlockText(element)"
                        rows="2"
                        placeholder="引言內容…"
                        @input="updateContentBlockText(element, ($event.target as HTMLTextAreaElement).value)"
                        @click.stop
                      />
                      <div v-else-if="element.type === 'divider'" class="sb-card-divider" aria-label="分隔線"></div>
                      <input
                        v-else
                        class="sb-card-title"
                        :class="{ empty: !element.label }"
                        v-model="element.label"
                        :placeholder="`未命名${getQuestionType(element.type).label}`"
                        @input="store.markDirty()"
                        @click.stop
                      />
                      <textarea
                        v-if="!['section_title', 'description_block', 'divider', 'quote_block'].includes(element.type)"
                        class="sb-card-desc"
                        v-model="element.description"
                        rows="1"
                        placeholder="新增題目描述（選填）"
                        @input="store.markDirty()"
                        @click.stop
                      />
                    </div>
                    <div class="sb-card-badges">
                      <span v-if="element.required" class="sb-req-tag">必填</span>
                      <span v-if="errorElementIds.has(element.id)" class="sb-badge error" :title="parsedErrors.find(e => e.elementId === element.id)?.messages.join('；')">⚠ 驗證錯誤</span>
                      <span v-if="element.is_hidden" class="sb-badge blue">個性化</span>
                      <span v-if="element.show_if_field_key || (element.show_if?.conditions ?? []).length > 0" class="sb-badge amber sb-badge-btn" @click.stop="selectElement(element.id); store.rightPanelTab = 'logic'">條件</span>
                      <span v-if="hasActiveJumpLogic(element)" class="sb-badge violet sb-badge-btn" @click.stop="selectElement(element.id); store.rightPanelTab = 'logic'; store.jumpLogicOpen = true">跳題</span>
                    </div>
                  </div>

                  <!-- Options preview -->
                  <div v-if="getQuestionType(element.type).supportsOptions && element.type !== 'select' && element.type !== 'constant_sum'" class="sb-card-body survey-choices sb-edit-options">
                    <div v-for="(opt, oi) in element.options" :key="opt.id" class="survey-choice-label sb-opt-row">
                      <span class="sb-opt-letter">{{ String.fromCharCode(97 + oi) }}</span>
                      <span class="survey-choice-input sb-opt-marker" :class="element.type === 'multiple_choice' ? 'square' : ''" />
                      <input
                        class="sb-opt-input"
                        v-model="opt.label"
                        :placeholder="`選項 ${oi + 1}`"
                        @input="opt.value ||= opt.id; store.markDirty()"
                        @click.stop
                      />
                      <button class="sb-opt-act" type="button" @click.stop="removeOption(element, oi)">✕</button>
                    </div>
                    <button class="sb-opt-add" type="button" @click.stop="addOption(element)">
                      + 新增選項
                    </button>
                  </div>

                  <!-- Constant sum design preview -->
                  <div v-else-if="element.type === 'constant_sum'" class="sb-card-body survey-choices survey-constant-sum">
                    <div v-for="(opt, oi) in element.options" :key="opt.id" class="survey-choice-label survey-preview-inline-input survey-constant-sum-row">
                      <span class="sb-constant-sum-option-edit">
                        <span class="sb-opt-letter">{{ String.fromCharCode(97 + oi) }}</span>
                        <input
                          class="sb-opt-input"
                          v-model="opt.label"
                          :placeholder="`選項 ${oi + 1}`"
                          @input="opt.value ||= opt.id; store.markDirty()"
                          @click.stop
                        />
                      </span>
                      <span class="survey-constant-sum-input-wrap">
                        <input class="survey-input" type="number" :placeholder="String((element.settings as any)?.unit || '0')" disabled />
                        <span v-if="(element.settings as any)?.unit" class="survey-constant-sum-unit">{{ (element.settings as any).unit }}</span>
                        <button class="sb-opt-act" type="button" @click.stop="removeOption(element, oi)">✕</button>
                      </span>
                    </div>
                    <div class="survey-constant-sum-summary" data-status="neutral">
                      <span>目前合計 0</span>
                      <span v-if="constantSumTotal(element) !== null">目標 {{ formatSurveyNumber(constantSumTotal(element)!) }}</span>
                      <strong>{{ constantSumTotal(element) === null ? '尚未設定合計目標' : `剩餘 ${formatSurveyNumber(constantSumTotal(element)! || 0)}` }}</strong>
                    </div>
                    <button class="sb-opt-add" type="button" @click.stop="addOption(element)">
                      + 新增選項
                    </button>
                  </div>

                  <!-- Select preview -->
                  <div v-else-if="element.type === 'select'" class="sb-card-body">
                    <select class="survey-select" disabled>
                      <option>請選擇</option>
                      <option v-for="opt in previewOptions(element)" :key="opt.id">{{ opt.label }}</option>
                    </select>
                  </div>

                  <!-- Text preview -->
                  <div v-else-if="element.type === 'short_text' || element.type === 'long_text' || element.type === 'email' || element.type === 'phone' || element.type === 'date' || element.type === 'time'" class="sb-card-body">
                    <input
                      v-if="element.type !== 'long_text'"
                      :type="textInputType(element)"
                      class="survey-input"
                      :placeholder="element.placeholder || (element.type === 'date' ? 'yyyy-mm-dd' : element.type === 'time' ? '--:--' : element.type === 'email' ? 'email@example.com' : element.type === 'phone' ? '0912345678' : '單行文字回應…')"
                      disabled
                    />
                    <div v-else class="survey-textarea sb-fake-input tall">{{ element.placeholder || '多行文字回應…' }}</div>
                  </div>

                  <!-- Number preview -->
                  <div v-else-if="element.type === 'number'" class="sb-card-body">
                    <div class="survey-input sb-fake-input sb-fake-number">
                      <span style="font-family:var(--mono); color:var(--c-ink3)">0</span>
                      <span v-if="element.settings?.unit" class="sb-fake-number-unit">{{ element.settings.unit }}</span>
                    </div>
                  </div>

                  <!-- Linear scale preview -->
                  <div v-else-if="element.type === 'linear_scale'" class="sb-card-body">
                    <div class="survey-linear-scale sb-fake-slider">
                      <div class="survey-linear-scale-value">{{ defaultLinearScaleValue(element) }}</div>
                      <input
                        type="range"
                        class="survey-linear-scale-input"
                        disabled
                        :min="(element.settings as any)?.min ?? 1"
                        :max="(element.settings as any)?.max ?? 5"
                        :step="(element.settings as any)?.step ?? 1"
                        :value="defaultLinearScaleValue(element)"
                        :style="{ '--survey-range-fill': linearScaleFillPercent(element, defaultLinearScaleValue(element)) }"
                      />
                      <div class="sb-fake-slider-labels">
                        <span>{{ (element.settings as any)?.low_label || (element.settings as any)?.min || 1 }}</span>
                        <span>{{ (element.settings as any)?.high_label || (element.settings as any)?.max || 5 }}{{ element.settings?.unit ? ` ${element.settings.unit}` : '' }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Rating preview -->
                  <div v-else-if="element.type === 'rating'" class="sb-card-body">
                    <div class="survey-rating-stars sb-fake-rating">
                      <span
                        v-for="n in Number((element.settings as any)?.count ?? 5)"
                        :key="n"
                        class="survey-rating-star-label sb-fake-rating-icon"
                        :class="`shape-${(element.settings as any)?.shape ?? 'star'}`"
                      >
                        <span v-if="(element.settings as any)?.show_numbers" class="survey-rating-star-number sb-rating-number">{{ n }}</span>
                        <span class="survey-rating-star-icon sb-rating-symbol">{{ ratingShapeIcon((element.settings as any)?.shape ?? 'star') }}</span>
                      </span>
                    </div>
                  </div>

                  <!-- Matrix card preview -->
                  <div v-else-if="element.type === 'matrix_single' || element.type === 'matrix_multi'" class="sb-card-body" @click.stop>
                    <div class="survey-preview-matrix-scroll">
                      <table class="survey-matrix">
                        <thead>
                          <tr>
                            <th></th>
                            <th v-for="col in element.matrix_cols" :key="col.id">{{ col.label }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="row in element.matrix_rows" :key="row.id">
                            <td>{{ row.label }}</td>
                            <td v-for="col in element.matrix_cols" :key="col.id" class="survey-preview-matrix-cell">
                              <span class="survey-preview-matrix-pip" :class="element.type === 'matrix_multi' ? 'square' : ''"></span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- NPS preview -->
                  <div v-else-if="element.type === 'nps'" class="sb-card-body">
                    <div class="survey-nps-row sb-fake-nps">
                      <span
                        v-for="n in 11" :key="n"
                        class="survey-nps-pip"
                        :class="(element.settings as any)?.color_bands ? (n-1 <= 6 ? 'red' : n-1 <= 8 ? 'yellow' : 'green') : ''"
                      >{{ n - 1 }}</span>
                    </div>
                    <div class="survey-nps-labels sb-fake-nps-labels">
                      <span>{{ (element.settings as any)?.low_label || '非常不推薦' }}</span>
                      <span>{{ (element.settings as any)?.high_label || '非常推薦' }}</span>
                    </div>
                  </div>

                  <!-- Cascade select preview -->
                  <div v-else-if="element.type === 'cascade_select'" class="sb-card-body survey-cascade-grid sb-cascade-card-body">
                    <p v-if="(element.cascade_levels ?? []).length === 0" class="survey-help">請先設定層級與選項資料。</p>
                    <div
                      v-for="(lvl, li) in (element.cascade_levels ?? [])"
                      :key="lvl.id"
                      class="survey-preview-cascade-row"
                    >
                      <select class="survey-select" disabled>
                        <option>{{ lvl.label || (li === 0 ? '請選擇' : '請先選擇上一層') }}</option>
                      </select>
                    </div>
                  </div>

                  <!-- Selection-based preview -->
                  <div v-else-if="element.type === 'selection_based'" class="sb-card-body">
                    <div class="survey-selection-source-card">
                      <div class="survey-selection-source-heading">
                        <span aria-hidden="true">☑</span>
                        <span>選項來源清單：根據選項來源，帶入選擇的選項</span>
                      </div>
                      <div v-if="selectionBasedSourceElement(element)" class="survey-selection-source-row">
                        <span class="survey-selection-source-index">1.</span>
                        <span class="survey-selection-source-label">{{ selectionBasedSourceLabel(element) }}</span>
                      </div>
                      <p v-else class="survey-help">請在右側選擇來源題目。</p>
                    </div>
                  </div>

                  <!-- Quick action bar (visible when selected) -->
                  <div class="sb-card-quick">
                    <button
                      v-if="getQuestionType(element.type).supportsRequired"
                      class="sb-quick-btn"
                      :class="{ toggled: element.required }"
                      type="button"
                      @click.stop="store.updateQuestion(element.id, { required: !element.required })"
                    >✱ 必填</button>
                    <button class="sb-quick-btn" type="button" @click.stop="store.duplicateQuestion(element.id)">⊕ 複製</button>
                    <button v-if="elementSupportsLogic(element)" class="sb-quick-btn" type="button" @click.stop="selectElement(element.id); store.rightPanelTab = 'logic'">⟁ 邏輯</button>
                    <div style="flex:1" />
                    <button class="sb-quick-btn danger" type="button" @click.stop="store.removeQuestion(element.id)">✕ 刪除</button>
                  </div>
                </article>
              </template>

              <!-- Drop zone after last card -->
              <div
                class="sb-drop-zone"
                :class="{ over: isZoneActive(store.selectedPage.id, store.selectedPage.elements.length) }"
                @dragover="onDragOverZone($event, store.selectedPage.id, store.selectedPage.elements.length)"
                @dragleave="onDragLeave"
                @drop="onDropZone($event, store.selectedPage.id, store.selectedPage.elements.length)"
              >
                <span v-if="isZoneActive(store.selectedPage.id, store.selectedPage.elements.length)">放開以移動至此</span>
              </div>
            </template>

            <div class="sb-add-q-zone">
              <button class="sb-add-q-btn" type="button" @click="store.rightPanelTab = 'library'">
                + 從右側新增題目
              </button>
            </div>
          </template>
        </div>
      </div>
    </main>

    <!-- ── Right panel ── -->
    <RightPanel v-if="!store.isPreviewMode" />

  </div><!-- /sb-body -->
</template>
