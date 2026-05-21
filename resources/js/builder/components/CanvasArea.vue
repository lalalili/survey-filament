<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { getQuestionType } from '../registry/questionTypes';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';
import type { BuilderEndpoints, CascadeNode, Condition, SurveyElement, SurveyOption, SurveyOptionAction, SurveyPage } from '../types/schema';
import SurveyRichEditor from './SurveyRichEditor.vue';
import RightPanel from './RightPanel.vue';
import { elementSupportsJump, hasActiveJumpLogic, typeCategory } from '../utils/builderHelpers';

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
const previewSignatures = ref<Record<string, boolean>>({});
const previewPageHistory = ref<string[]>([]);
const previewEnded = ref(false);
const previewRatings = ref<Record<string, number | null>>({});
const previewRatingHover = ref<Record<string, number>>({});
const previewNps = ref<Record<string, number | null>>({});

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
    previewSignatures.value = {};
    previewRatings.value = {};
    previewRatingHover.value = {};
    previewNps.value = {};
    previewPageHistory.value = [];
  }
});

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
};

const MSG_MAP: Array<[RegExp | string, string]> = [
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

function parseErrorKey(key: string, messages: string[]): ParsedError {
  const translatedMessages = messages.map(translateMessage);
  const base: ParsedError = {
    raw: key, messages, translatedMessages,
    pageIndex: null, elementIndex: null, questionPageNumber: null,
    pageId: null, elementId: null, fieldName: null,
    pageLabel: '', elementLabel: '', fieldLabel: '',
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

  const elementLabel = element?.label ? `「${element.label}」` : elementIndex !== null ? `第 ${elementIndex + 1} 題` : '';
  const fLabel = fieldName ? (FIELD_LABELS[fieldName.split('.')[0]] ?? fieldName) : '';

  return { raw: key, messages, translatedMessages, pageIndex, elementIndex, questionPageNumber, pageId, elementId, fieldName, pageLabel, elementLabel, fieldLabel: fLabel };
}

const parsedErrors = computed<ParsedError[]>(() =>
  Object.entries(store.validationErrors).map(([key, msgs]) => parseErrorKey(key, msgs)),
);

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
  previewRatings.value = {
    ...previewRatings.value,
    [elementId]: previewRatings.value[elementId] === score ? null : score,
  };
  previewRatingHover.value = { ...previewRatingHover.value, [elementId]: 0 };
}

function previewRatingDisplayValue(elementId: string): number {
  const hover = previewRatingHover.value[elementId] ?? 0;
  return hover > 0 ? hover : previewRatings.value[elementId] ?? 0;
}

function previewRatingIsHovered(elementId: string, score: number): boolean {
  const hover = previewRatingHover.value[elementId] ?? 0;
  return hover > 0 && score <= hover;
}

function previewGoNext() {
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
  previewSignatures.value = {};
  previewRatings.value = {};
  previewRatingHover.value = {};
  previewNps.value = {};
  previewPageHistory.value = [];
}

function previewRankingOrder(element: SurveyElement): SurveyOption[] {
  const order = previewRankingOrders.value[element.id] ?? element.options.map((option) => option.value);
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
  if (element.type === 'section_title') return '區段標題';
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

// ── Drag-and-drop ───────────────────────────────────────────────────────────
function onDragStart(e: DragEvent, qId: string) {
  dragQId.value = qId;
  if (e.dataTransfer) { e.dataTransfer.effectAllowed = 'move'; e.dataTransfer.setData('text/plain', qId); }
}

function onDragEnd() { dragQId.value = null; dropTarget.value = null; }

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
      <span class="sb-errors-title">儲存失敗，請修正以下 {{ parsedErrors.length }} 個問題</span>
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
          <span v-if="err.elementIndex !== null" class="sb-errors-badge element">第 {{ err.elementIndex + 1 }} 題</span>
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
              @click="store.selectedPageId = store.welcomePage.id; store.selectedElementId = null"
            >
              <span class="sb-page-tab-num">歡迎</span>
            </button>

            <button
              v-for="(page, i) in store.questionPages"
              :key="page.id"
              class="sb-page-tab"
              :class="{
                active: store.selectedPageId === page.id,
                'drop-target': isTabTarget(page.id),
                'has-error': errorPageIds.has(page.id),
              }"
              @click="store.selectedPageId = page.id; store.selectedElementId = null"
              @dragover="onDragOverTab($event, page.id)"
              @dragleave="onDragLeave"
              @drop="onDropTab($event, page.id)"
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
            </button>

            <button
              v-if="store.thankYouPage"
              class="sb-page-tab thanks"
              :class="{ active: store.selectedPageId === store.thankYouPage.id }"
              @click="store.selectedPageId = store.thankYouPage.id; store.selectedElementId = null"
            >
              <span class="sb-page-tab-num">感謝</span>
            </button>
          </div>
          <button class="sb-page-tab-add" type="button" @click="store.addPage()">
            + 新增頁面
          </button>
        </div>

        <!-- ── Preview mode ── -->
        <div v-if="store.isPreviewMode" class="sb-preview" :class="store.isMobilePreview ? 'mobile' : ''">
          <div v-if="previewEnded" class="sb-preview-end">
            <p class="sb-preview-end-title">問卷已結束</p>
            <p class="sb-preview-end-sub">感謝您的填答</p>
            <button type="button" class="sb-btn" @click="resetPreview()">重置預覽</button>
          </div>
          <template v-else>
            <div class="sb-preview-progress">
              <div :style="{ width: `${((store.schema.pages.findIndex(p => p.id === store.selectedPageId) + 1) / store.schema.pages.length) * 100}%` }" />
            </div>
            <div class="sb-preview-card">
              <p v-if="store.selectedPage?.kind !== 'welcome' && store.selectedPage?.kind !== 'thank_you'" class="sb-preview-page-meta">第 {{ store.schema.pages.findIndex(p => p.id === store.selectedPageId) + 1 }} 頁 · 共 {{ store.schema.pages.length }} 頁</p>
              <h2 v-if="store.selectedPage?.kind !== 'welcome' && store.selectedPage?.kind !== 'thank_you' && store.selectedPage?.title" class="sb-preview-page-title">{{ store.selectedPage.title }}</h2>

              <!-- Welcome page rich content + CTA -->
              <template v-if="store.selectedPage?.kind === 'welcome'">
                <div
                  v-if="store.selectedPage.welcome_settings?.content"
                  class="sb-preview-rich survey-rich-content"
                  v-html="store.selectedPage.welcome_settings.content"
                ></div>
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

              <template v-else v-for="element in (store.selectedPage?.kind === 'welcome' || store.selectedPage?.kind === 'thank_you' ? [] : store.selectedPage?.elements ?? [])" :key="element.id">
                <template v-if="previewElementVisible(element)">
                  <div v-if="element.is_hidden" class="sb-preview-hidden">🔒 {{ element.label }}</div>
                  <section v-else-if="element.type === 'section_title'" class="sb-preview-section">
                    <h3>{{ contentBlockText(element) }}</h3>
                  </section>
                  <div v-else-if="element.type === 'description_block'" class="sb-preview-desc-block survey-rich-content" v-html="contentBlockText(element)"></div>
                  <blockquote v-else-if="element.type === 'quote_block'" class="sb-preview-quote">{{ contentBlockText(element) }}</blockquote>
                  <hr v-else-if="element.type === 'divider'" class="sb-preview-divider">
                  <div v-else class="sb-preview-q">
                    <p class="sb-preview-q-title">{{ element.label }}<span v-if="element.required" class="sb-req">*</span></p>
                    <p v-if="element.description" class="sb-preview-q-desc">{{ element.description }}</p>
                    <div v-if="element.type === 'single_choice'" class="sb-preview-opts">
                      <label v-for="opt in element.options" :key="opt.id"
                        class="sb-preview-opt"
                        :class="previewSelections[element.id] === opt.value ? 'selected' : ''"
                      >
                        <input type="radio" :name="element.id" :value="opt.value" :checked="previewSelections[element.id] === opt.value" @change="previewSelectOption(element, opt.value)" />
                        <span>{{ opt.label }}</span>
                      </label>
                    </div>
                    <div v-else-if="element.type === 'multiple_choice'" class="sb-preview-opts">
                      <label v-for="opt in element.options" :key="opt.id"
                        class="sb-preview-opt"
                        :class="(previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value)) ? 'selected' : ''"
                        @click.prevent="previewToggleCheckbox(element.id, opt.value)"
                      >
                        <input type="checkbox" :name="element.id" :checked="previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value)" @change.prevent />
                        <span>{{ opt.label }}</span>
                      </label>
                    </div>
                    <select
                      v-else-if="element.type === 'select'"
                      :value="previewSelections[element.id] ?? ''"
                      class="sb-preview-input"
                      @change="previewSelectOption(element, ($event.target as HTMLSelectElement).value)"
                    >
                      <option value="">請選擇</option>
                      <option v-for="opt in element.options" :key="opt.id" :value="opt.value">{{ opt.label }}</option>
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
                      class="sb-preview-input"
                      @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                    />
                    <textarea
                      v-else-if="element.type === 'long_text'"
                      :placeholder="element.placeholder ?? ''"
                      :value="previewTextValues[element.id] ?? ''"
                      rows="4"
                      class="sb-preview-input"
                      @input="previewUpdateTextValue(element.id, ($event.target as HTMLTextAreaElement).value)"
                    />
                    <div v-else-if="element.type === 'rating'" class="sb-preview-rating">
                      <button
                        v-for="n in Number((element.settings as any)?.count ?? 5)"
                        :key="n"
                        type="button"
                        class="sb-preview-rating-icon"
                        :class="{
                          filled: n <= previewRatingDisplayValue(element.id),
                          hovered: previewRatingIsHovered(element.id, n),
                        }"
                        @mouseenter="previewRatingHover = { ...previewRatingHover, [element.id]: n }"
                        @mouseleave="previewRatingHover = { ...previewRatingHover, [element.id]: 0 }"
                        @click="previewSelectRating(element.id, n)"
                      >
                        <span v-if="(element.settings as any)?.show_numbers" class="sb-rating-number">{{ n }}</span>
                        <span class="sb-rating-symbol">{{ ratingShapeIcon((element.settings as any)?.shape ?? 'star') }}</span>
                      </button>
                    </div>
                    <div v-else-if="element.type === 'number'" class="sb-preview-number-row">
                      <input
                        type="number"
                        class="sb-preview-input sb-preview-number-input"
                        :min="(element.settings as any)?.min ?? undefined"
                        :max="(element.settings as any)?.max ?? undefined"
                        :step="(element.settings as any)?.decimal_places ? Math.pow(10, -Number((element.settings as any).decimal_places)) : 1"
                        :value="previewTextValues[element.id] ?? ''"
                        placeholder="請輸入數字"
                        @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                      />
                      <span v-if="(element.settings as any)?.unit" class="sb-preview-number-unit">{{ (element.settings as any).unit }}</span>
                    </div>
                    <div v-else-if="element.type === 'linear_scale'" class="sb-preview-number-row sb-preview-range-row">
                      <span class="sb-preview-range-value">{{ previewLinearScaleValue(element) }}</span>
                      <input
                        type="range"
                        class="sb-preview-range-input"
                        :min="(element.settings as any)?.min ?? 1"
                        :max="(element.settings as any)?.max ?? 5"
                        :step="(element.settings as any)?.step ?? 1"
                        :value="previewLinearScaleValue(element)"
                        :style="{ '--range-fill': linearScaleFillPercent(element) }"
                        @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                      />
                      <span v-if="(element.settings as any)?.unit" class="sb-preview-number-unit">{{ (element.settings as any).unit }}</span>
                    </div>
                    <div v-else-if="element.type === 'constant_sum'" class="sb-preview-opts">
                      <label v-for="opt in element.options" :key="opt.id" class="sb-preview-opt">
                        <span>{{ opt.label }}</span>
                        <input
                          type="number"
                          class="sb-preview-input"
                          :placeholder="String((element.settings as any)?.unit ?? '')"
                          :value="previewConstantSumValues[element.id]?.[opt.id] ?? ''"
                          @input="previewUpdateConstantSumValue(element.id, opt.id, ($event.target as HTMLInputElement).value)"
                        />
                      </label>
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
                    <div v-else-if="element.type === 'file_upload'" class="sb-preview-file">
                      <input
                        type="file"
                        class="sb-preview-input"
                        @change="previewFileSelected(element.id, $event)"
                      />
                      <p v-if="previewFileNames[element.id]" class="sb-preview-help">已選擇：{{ previewFileNames[element.id] }}</p>
                    </div>
                    <div v-else-if="element.type === 'signature'" class="sb-preview-signature">
                      <button
                        type="button"
                        class="sb-preview-signature-pad"
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
                        class="sb-preview-input"
                        :placeholder="String(addressKey)"
                        :value="previewAddressValues[element.id]?.[String(addressKey)] ?? ((String(addressKey) === 'country' && (element.settings as any)?.country_locked) ? String((element.settings as any).country_locked) : '')"
                        :disabled="String(addressKey) === 'country' && !!(element.settings as any)?.country_locked"
                        @input="previewUpdateAddress(element.id, String(addressKey), ($event.target as HTMLInputElement).value)"
                      />
                    </div>
                    <div v-else-if="element.type === 'matrix_single' || element.type === 'matrix_multi'" class="sb-preview-matrix-wrap">
                      <div class="sb-preview-matrix-scroll">
                        <table class="sb-preview-matrix">
                          <thead>
                            <tr>
                              <th class="sb-preview-matrix-corner"></th>
                              <th v-for="col in element.matrix_cols" :key="col.id" class="sb-preview-matrix-col-head">{{ col.label }}</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="row in element.matrix_rows" :key="row.id" class="sb-preview-matrix-row">
                              <td class="sb-preview-matrix-row-label">{{ row.label }}</td>
                              <td
                                v-for="col in element.matrix_cols"
                                :key="col.id"
                                class="sb-preview-matrix-cell"
                                :class="{
                                  selected: element.type === 'matrix_single'
                                    ? previewMatrixSingleSelected(element.id, row.id, col.id)
                                    : previewMatrixMultiSelected(element.id, row.id, col.id)
                                }"
                                @click="element.type === 'matrix_single'
                                  ? previewSelectMatrixSingle(element.id, row.id, col.id)
                                  : previewToggleMatrixMulti(element.id, row.id, col.id)"
                              >
                                <span class="sb-preview-matrix-pip" :class="element.type === 'matrix_multi' ? 'square' : ''"></span>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                    <div v-else-if="element.type === 'nps'" class="sb-preview-nps-wrap">
                      <div class="sb-preview-nps-row">
                        <span
                          v-for="n in 11" :key="n"
                          class="sb-preview-nps-pip"
                          :class="{
                            selected: previewNps[element.id] === n - 1,
                            red:    (element.settings as any)?.color_bands && n - 1 <= 6,
                            yellow: (element.settings as any)?.color_bands && n - 1 >= 7 && n - 1 <= 8,
                            green:  (element.settings as any)?.color_bands && n - 1 >= 9,
                          }"
                          @click="previewSelectNps(element.id, n - 1)"
                        >{{ n - 1 }}</span>
                      </div>
                      <div class="sb-preview-nps-labels">
                        <span>{{ (element.settings as any)?.low_label || '非常不推薦' }}</span>
                        <span>{{ (element.settings as any)?.high_label || '非常推薦' }}</span>
                      </div>
                    </div>
                    <div v-else-if="element.type === 'cascade_select'" class="sb-preview-cascade">
                      <div
                        v-for="(lvl, li) in (element.cascade_levels ?? [])"
                        :key="lvl.id"
                        class="sb-preview-cascade-row"
                      >
                        <label class="sb-preview-cascade-label">{{ lvl.label }}</label>
                        <select
                          class="sb-preview-cascade-select"
                          :disabled="li > 0 && !(previewCascade[element.id]?.[li - 1])"
                          :value="previewCascade[element.id]?.[li] ?? ''"
                          @change="cascadePreviewSelect(element.id, li, ($event.target as HTMLSelectElement).value)"
                        >
                          <option value="">── 請選擇 ──</option>
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
              <div v-if="store.selectedPage?.kind !== 'welcome' && store.selectedPage?.kind !== 'thank_you'" class="sb-preview-footer">
                <button
                  v-if="store.schema.pages.findIndex(p => p.id === store.selectedPageId) > 0"
                  type="button"
                  class="sb-btn"
                  @click="previewGoPrev()"
                >← 上一頁</button>
                <div style="flex:1" />
                <button type="button" class="sb-btn accent" @click="previewGoNext()">
                  {{ store.schema.pages.findIndex(p => p.id === store.selectedPageId) === store.schema.pages.length - 1 ? '提交' : '下一頁 →' }}
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- ── Edit mode ── -->
        <div v-else>
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
                    <span v-if="!['section_title', 'description_block', 'divider', 'quote_block'].includes(element.type)" class="sb-card-num">{{ i + 1 }}</span>
                    <div class="sb-card-title-wrap">
                      <input
                        v-if="element.type === 'section_title'"
                        class="sb-card-title"
                        :class="{ empty: !element.description }"
                        :value="contentBlockText(element)"
                        placeholder="區段標題…"
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
                  <div v-if="getQuestionType(element.type).supportsOptions" class="sb-card-body">
                    <div v-for="(opt, oi) in element.options" :key="opt.id" class="sb-opt-row">
                      <span class="sb-opt-letter">{{ String.fromCharCode(97 + oi) }}</span>
                      <span class="sb-opt-marker" :class="element.type === 'multiple_choice' ? 'square' : ''" />
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

                  <!-- Text preview -->
                  <div v-else-if="element.type === 'short_text' || element.type === 'long_text'" class="sb-card-body">
                    <div class="sb-fake-input" :class="element.type === 'long_text' ? 'tall' : ''">{{ element.placeholder || (element.type === 'long_text' ? '多行文字回應…' : '單行文字回應…') }}</div>
                  </div>

                  <!-- Number preview -->
                  <div v-else-if="element.type === 'number'" class="sb-card-body">
                    <div class="sb-fake-input sb-fake-number">
                      <span style="font-family:var(--mono); color:var(--c-ink3)">0</span>
                      <span v-if="element.settings?.unit" class="sb-fake-number-unit">{{ element.settings.unit }}</span>
                    </div>
                  </div>

                  <!-- Linear scale preview -->
                  <div v-else-if="element.type === 'linear_scale'" class="sb-card-body">
                    <div class="sb-fake-slider">
                      <div class="sb-fake-slider-value">{{ defaultLinearScaleValue(element) }}</div>
                      <div class="sb-fake-slider-track">
                        <span class="sb-fake-slider-fill" :style="{ width: linearScaleFillPercent(element, defaultLinearScaleValue(element)) }"></span>
                        <span class="sb-fake-slider-thumb" :style="{ left: linearScaleFillPercent(element, defaultLinearScaleValue(element)) }"></span>
                      </div>
                      <div class="sb-fake-slider-labels">
                        <span>{{ (element.settings as any)?.low_label || (element.settings as any)?.min || 1 }}</span>
                        <span>{{ (element.settings as any)?.high_label || (element.settings as any)?.max || 5 }}{{ element.settings?.unit ? ` ${element.settings.unit}` : '' }}</span>
                      </div>
                    </div>
                  </div>

                  <!-- Rating preview -->
                  <div v-else-if="element.type === 'rating'" class="sb-card-body">
                    <div class="sb-fake-rating">
                      <span
                        v-for="n in Number((element.settings as any)?.count ?? 5)"
                        :key="n"
                        class="sb-fake-rating-icon"
                      >
                        <span v-if="(element.settings as any)?.show_numbers" class="sb-rating-number">{{ n }}</span>
                        <span class="sb-rating-symbol">{{ ratingShapeIcon((element.settings as any)?.shape ?? 'star') }}</span>
                      </span>
                    </div>
                  </div>

                  <!-- Matrix card preview -->
                  <div v-else-if="element.type === 'matrix_single' || element.type === 'matrix_multi'" class="sb-card-body" @click.stop>
                    <div class="sb-matrix-scroll">
                      <table class="sb-matrix-table">
                        <thead>
                          <tr>
                            <th class="sb-matrix-corner"></th>
                            <th v-for="col in element.matrix_cols" :key="col.id" class="sb-matrix-col-head">{{ col.label }}</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="row in element.matrix_rows" :key="row.id">
                            <td class="sb-matrix-row-label">{{ row.label }}</td>
                            <td v-for="col in element.matrix_cols" :key="col.id" class="sb-matrix-cell">
                              <span :class="element.type === 'matrix_multi' ? 'sb-matrix-cb' : 'sb-matrix-rb'"></span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>

                  <!-- NPS preview -->
                  <div v-else-if="element.type === 'nps'" class="sb-card-body">
                    <div class="sb-fake-nps">
                      <span
                        v-for="n in 11" :key="n"
                        class="sb-nps-pip"
                        :class="(element.settings as any)?.color_bands ? (n-1 <= 6 ? 'red' : n-1 <= 8 ? 'yellow' : 'green') : ''"
                      >{{ n - 1 }}</span>
                    </div>
                    <div class="sb-fake-nps-labels">
                      <span>{{ (element.settings as any)?.low_label || '非常不推薦' }}</span>
                      <span>{{ (element.settings as any)?.high_label || '非常推薦' }}</span>
                    </div>
                  </div>

                  <!-- Cascade select preview -->
                  <div v-else-if="element.type === 'cascade_select'" class="sb-card-body sb-cascade-card-body">
                    <div class="sb-cascade-level-pills">
                      <span v-for="(lvl, li) in (element.cascade_levels ?? [])" :key="lvl.id" class="sb-cascade-level-pill">
                        <span class="sb-cascade-level-num">{{ li + 1 }}</span>{{ lvl.label }}
                      </span>
                    </div>
                    <div class="sb-cascade-stats">
                      {{ (element.cascade_data ?? []).length }} 個第一層選項
                      <span v-if="(element.cascade_levels ?? []).length > 1"> · {{ (element.cascade_levels ?? []).length }} 層</span>
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
                    <button class="sb-quick-btn" type="button" @click.stop="selectElement(element.id); store.rightPanelTab = 'logic'">⟁ 邏輯</button>
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
