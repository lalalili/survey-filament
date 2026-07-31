import { computed, inject, onBeforeUnmount, provide, ref, watch, type InjectionKey } from 'vue';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';
import type { CascadeNode, Condition, SurveyCalculation, SurveyElement, SurveyOption, SurveyOptionAction, SurveyPage } from '../types/schema';
import { formatSurveyNumber } from '../utils/builderHelpers';
import { findPreviewThankYouPageId } from '../utils/previewSubmission';
import { normalizeVariableTokenChips } from '../utils/variableTokens';
import { visibleSurveyElements } from '../utils/systemContextFields';

/**
 * 建立器「預覽模式」的執行期：作答狀態、跳題導覽、顯示條件、計算變數與各題型的
 * 預覽輔助函式。
 *
 * 這裡是**第二份**填答邏輯實作——公開填答頁在 survey-core 的
 * `resources/views/survey/partials/scripts.blade.php` 另有一份。兩邊的分頁、必填、
 * 跳題語意必須人工保持一致；改動任一側時請一併檢查另一側。
 */

// ── 預覽用隨機（對應公開填答端 SurveyField::arrangeForDisplay / displayOptions）──

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

function decodeHtmlEntities(value: string): string {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = value;
  return textarea.value;
}

function resolveGradeLabel(score: number, gradeMap: Array<Record<string, unknown>> = []): string | number {
  for (const grade of gradeMap) {
    const min = Object.prototype.hasOwnProperty.call(grade, 'min') ? Number(grade.min) : Number.NEGATIVE_INFINITY;
    const max = Object.prototype.hasOwnProperty.call(grade, 'max') ? Number(grade.max) : Number.POSITIVE_INFINITY;

    if (score >= min && score <= max) {
      return typeof grade.label === 'string' && grade.label !== '' ? grade.label : score;
    }
  }

  return score;
}

function previewValueMatches(current: unknown, expected: unknown): boolean {
  if (Array.isArray(current)) return current.includes(expected);
  return String(current ?? '') === String(expected ?? '');
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

export function usePreviewRuntime() {
  const store = useSurveyBuilderStore();

  // ── 作答狀態 ──────────────────────────────────────────────────────────────
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
  const previewSubmitNoticeVisible = ref(false);
  const previewRatings = ref<Record<string, number | null>>({});
  const previewRatingHover = ref<Record<string, number>>({});
  const previewRatingPop = ref<Record<string, number>>({});
  const previewNps = ref<Record<string, number | null>>({});
  const previewTermsAccepted = ref(false);
  const previewSeed = ref(Math.floor(Math.random() * 0xffffffff));
  let previewSubmitNoticeTimer: ReturnType<typeof setTimeout> | null = null;

  function dismissPreviewSubmitNotice() {
    previewSubmitNoticeVisible.value = false;
    if (previewSubmitNoticeTimer) {
      clearTimeout(previewSubmitNoticeTimer);
      previewSubmitNoticeTimer = null;
    }
  }

  function showPreviewSubmitNotice() {
    dismissPreviewSubmitNotice();
    previewSubmitNoticeVisible.value = true;
    previewSubmitNoticeTimer = setTimeout(dismissPreviewSubmitNotice, 5000);
  }

  onBeforeUnmount(dismissPreviewSubmitNotice);

  watch(() => store.isPreviewMode, (entering) => {
    dismissPreviewSubmitNotice();
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

  // ── 題目與選項的顯示順序 ──────────────────────────────────────────────────
  const previewPageElements = computed<SurveyElement[]>(() => {
    const page = store.selectedPage;
    if (!page || page.kind === 'welcome' || page.kind === 'thank_you') return [];
    const elements = visibleSurveyElements(page.elements ?? []);
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

  // ── 顯示條件 ──────────────────────────────────────────────────────────────
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

  // ── 計算變數 ──────────────────────────────────────────────────────────────
  function calculationValuesFromPreviewAnswers(useAnswers: boolean): Record<string, string | number> {
    const calculations = store.schema?.calculations ?? [];
    const values = calculations.reduce<Record<string, number>>((carry, calculation) => {
      carry[calculation.key] = Number(calculation.initial_value ?? 0);
      return carry;
    }, {});

    if (useAnswers) {
      for (const element of store.allElements) {
        if (!element.field_key || !previewElementVisible(element) || element.options.length === 0) continue;

        const answer = previewAnswerValue(element.field_key);
        const submittedValues = Array.isArray(answer)
          ? answer.map(String)
          : answer === null || answer === undefined || answer === ''
            ? []
            : [String(answer)];

        for (const option of element.options) {
          if (!submittedValues.includes(String(option.value))) continue;

          const scoreDeltaEntries = Object.entries(option.score_delta_json ?? {});

          for (const [rawCalculationKey, delta] of scoreDeltaEntries) {
            const calculationKey = Object.prototype.hasOwnProperty.call(values, rawCalculationKey)
              ? rawCalculationKey
              : calculations.length === 1 && scoreDeltaEntries.length === 1
                ? calculations[0].key
                : rawCalculationKey;

            if (!Object.prototype.hasOwnProperty.call(values, calculationKey)) continue;
            values[calculationKey] += Number.isFinite(Number(delta)) ? Number(delta) : 0;
          }
        }
      }
    }

    return calculations.reduce<Record<string, string | number>>((carry, calculation: SurveyCalculation) => {
      const score = values[calculation.key] ?? Number(calculation.initial_value ?? 0);
      carry[calculation.key] = calculation.output_format === 'grade'
        ? resolveGradeLabel(score, calculation.grade_map_json ?? [])
        : score;
      return carry;
    }, {});
  }

  function renderCalculationTokens(message: string | null | undefined, usePreviewAnswers: boolean): string {
    if (!message) return '';

    const values = calculationValuesFromPreviewAnswers(usePreviewAnswers);

    return normalizeVariableTokenChips(message).replace(/\{\{(.*?)\}\}/gs, (fullMatch, inner: string) => {
      const normalized = decodeHtmlEntities(String(inner).replace(/<[^>]*>/g, ''))
        .replace(/\s+/g, ' ')
        .trim();
      const match = normalized.match(/^calc\.([A-Za-z0-9_-]+)$/);

      if (!match) return fullMatch;

      return String(values[match[1]] ?? '');
    });
  }

  // ── 分頁導覽 ──────────────────────────────────────────────────────────────
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
  const previewHasTerms = computed(() => !!store.schema?.settings?.terms_text);
  const previewSubmitDisabled = computed(() => previewIsLastPage.value && previewHasTerms.value && !previewTermsAccepted.value);

  function previewGoNext() {
    if (previewIsLastPage.value && previewSubmitDisabled.value) return;
    const action = previewSelectedPageAction();
    if (action && previewApplyAction(action)) return;
    const pages = store.schema?.pages ?? [];
    const idx = pages.findIndex((p) => p.id === store.selectedPageId);
    if (previewIsLastPage.value) {
      showPreviewSubmitNotice();
      const thankYouPageId = findPreviewThankYouPageId(pages, store.selectedPageId);
      if (thankYouPageId) { previewNavigateTo(thankYouPageId); }
      else { previewEnded.value = true; }
      return;
    }
    if (idx < pages.length - 1) { previewNavigateTo(pages[idx + 1].id); }
    else { previewEnded.value = true; }
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
    dismissPreviewSubmitNotice();
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

  // ── 各題型的作答輔助 ──────────────────────────────────────────────────────
  function previewSelectOption(el: SurveyElement, val: string) {
    previewSelections.value = { ...previewSelections.value, [el.id]: val };
    const action = el.options.find((o) => o.value === val)?.action;
    if (action) previewApplyAction(action);
  }

  function previewToggleCheckbox(elId: string, val: string) {
    const cur = previewSelections.value[elId];
    const set = cur instanceof Set ? new Set(cur) : new Set<string>();
    if (set.has(val)) set.delete(val); else set.add(val);
    previewSelections.value = { ...previewSelections.value, [elId]: set };
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

  // ── 檔案上傳 ──────────────────────────────────────────────────────────────
  function previewFileSelected(elementId: string, event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0];
    previewFileNames.value = { ...previewFileNames.value, [elementId]: file?.name ?? '' };
  }

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

  // ── 主題 ──────────────────────────────────────────────────────────────────
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

  return {
    previewSelections,
    previewTextValues,
    previewConstantSumValues,
    previewMatrixSelections,
    previewCascade,
    previewAddressValues,
    previewRankingOrders,
    previewFileNames,
    previewFileDragOver,
    previewSignatures,
    previewPageHistory,
    previewEnded,
    previewSubmitNoticeVisible,
    previewRatings,
    previewRatingHover,
    previewRatingPop,
    previewNps,
    previewTermsAccepted,
    previewSeed,
    dismissPreviewSubmitNotice,
    previewPageElements,
    previewOptions,
    selectionBasedSourceElement,
    selectionBasedSourceOptions,
    selectionBasedSourceLabel,
    previewSelectionBasedOptions,
    cascadePreviewLevelOptions,
    cascadePreviewSelect,
    previewAnswerValue,
    previewConditionPasses,
    previewElementVisible,
    renderCalculationTokens,
    previewNavigateTo,
    previewIsLastPage,
    previewQuestionPages,
    previewCurrentQuestionPageIndex,
    previewShowsProgress,
    previewProgressWidth,
    previewHasTerms,
    previewSubmitDisabled,
    previewGoNext,
    previewGoPrev,
    resetPreview,
    previewSelectOption,
    previewToggleCheckbox,
    previewUpdateTextValue,
    previewUpdateConstantSumValue,
    constantSumTotal,
    constantSumValue,
    constantSumCurrent,
    constantSumStatus,
    constantSumStatusText,
    previewLinearScaleValue,
    linearScaleFillPercent,
    defaultLinearScaleValue,
    previewSelectRating,
    previewRatingDisplayValue,
    previewRatingIsHovered,
    previewRatingIsPopping,
    previewSelectMatrixSingle,
    previewToggleMatrixMulti,
    previewMatrixSingleSelected,
    previewMatrixMultiSelected,
    previewSelectNps,
    previewRankingOrder,
    previewMoveRanking,
    previewUpdateAddress,
    previewFileSelected,
    previewFileAccept,
    previewFileSizeLabel,
    previewFileFormatLabel,
    previewChooseFile,
    previewFileDropped,
    previewPrimaryColor,
    previewThemeVars,
  };
}

export type PreviewRuntime = ReturnType<typeof usePreviewRuntime>;

const previewRuntimeKey: InjectionKey<PreviewRuntime> = Symbol('survey-builder-preview-runtime');

/** 由 CanvasArea 建立並提供；編輯面與預覽面共用同一份作答狀態。 */
export function providePreviewRuntime(): PreviewRuntime {
  const runtime = usePreviewRuntime();
  provide(previewRuntimeKey, runtime);

  return runtime;
}

export function injectPreviewRuntime(): PreviewRuntime {
  const runtime = inject(previewRuntimeKey, null);

  if (!runtime) {
    throw new Error('usePreviewRuntime：找不到預覽執行期，請確認元件位於 CanvasArea 之下。');
  }

  return runtime;
}
