<script setup lang="ts">
import { computed, nextTick, ref, shallowRef, useTemplateRef, watch } from 'vue';
import { getQuestionType } from '../registry/questionTypes';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';
import type { BuilderEndpoints, SurveyElement, SurveyPage } from '../types/schema';
import SurveyRichEditor from './SurveyRichEditor.vue';
import RightPanel from './RightPanel.vue';
import PreviewCanvas from './preview/PreviewCanvas.vue';
import { providePreviewRuntime } from '../composables/usePreviewRuntime';
import { contentBlockText, elementSupportsLogic, formatSurveyNumber, hasActiveJumpLogic, isContentBlockType, ratingShapeIcon, textInputType, typeCategory } from '../utils/builderHelpers';
import { useQuestionCollapse } from '../utils/questionCollapse';
import { buildQuestionNumberMap } from '../utils/questionNumbering';
import { parseErrorKey, type ParsedError } from '../utils/validationErrors';
import { visibleSurveyElements } from '../utils/systemContextFields';

const props = defineProps<{
  endpoints: BuilderEndpoints;
  csrfToken: string;
  guideUrl?: string;
}>();

const store = useSurveyBuilderStore();
const questionCollapse = useQuestionCollapse();
type BuilderWorkspace = 'canvas' | 'library' | 'properties' | 'logic';
const workspaceOrder: BuilderWorkspace[] = ['canvas', 'library', 'properties', 'logic'];
const mobileWorkspace = shallowRef<BuilderWorkspace>('canvas');
const pageTabsEl = useTemplateRef<HTMLElement>('pageTabs');
const selectedPageTabId = computed(() => store.selectedPageId ? `sb-page-tab-${store.selectedPageId}` : undefined);

function selectWorkspace(workspace: BuilderWorkspace) {
  mobileWorkspace.value = workspace;
  if (workspace !== 'canvas') store.rightPanelTab = workspace;
}

function adjacentIndex(event: KeyboardEvent, currentIndex: number, itemCount: number): number | null {
  if (event.key === 'Home') return 0;
  if (event.key === 'End') return itemCount - 1;
  if (event.key === 'ArrowLeft') return (currentIndex - 1 + itemCount) % itemCount;
  if (event.key === 'ArrowRight') return (currentIndex + 1) % itemCount;

  return null;
}

async function onWorkspaceKeydown(event: KeyboardEvent, workspace: BuilderWorkspace) {
  const targetIndex = adjacentIndex(event, workspaceOrder.indexOf(workspace), workspaceOrder.length);
  if (targetIndex === null) return;

  event.preventDefault();
  const targetWorkspace = workspaceOrder[targetIndex];
  selectWorkspace(targetWorkspace);
  await nextTick();
  document.getElementById(`sb-workspace-tab-${targetWorkspace}`)?.focus();
}

async function onPageTabKeydown(event: KeyboardEvent, pageId: string) {
  const pageIds = [
    ...(store.welcomePage ? [store.welcomePage.id] : []),
    ...store.questionPages.map((page) => page.id),
    ...(store.thankYouPage ? [store.thankYouPage.id] : []),
  ];
  const targetIndex = adjacentIndex(event, pageIds.indexOf(pageId), pageIds.length);
  if (targetIndex === null) return;

  event.preventDefault();
  const targetPageId = pageIds[targetIndex];
  selectPage(targetPageId);
  await nextTick();
  Array.from(pageTabsEl.value?.querySelectorAll<HTMLElement>('[data-page-id]') ?? [])
    .find((tab) => tab.dataset.pageId === targetPageId)
    ?.focus();
}

watch(() => store.selectedElementId, (selectedElementId, previousElementId) => {
  if (selectedElementId && selectedElementId !== previousElementId) {
    selectWorkspace('properties');
  }
}, { flush: 'sync' });

watch(() => store.rightPanelTab, (rightPanelTab) => {
  mobileWorkspace.value = rightPanelTab;
}, { flush: 'sync' });

watch(() => store.selectedPageId, async (pageId) => {
  await nextTick();
  const activeTab = Array.from(pageTabsEl.value?.querySelectorAll<HTMLElement>('[data-page-id]') ?? [])
    .find((tab) => tab.dataset.pageId === pageId);
  activeTab?.scrollIntoView?.({ block: 'nearest', inline: 'nearest' });
});

// 預覽執行期由這裡建立並 provide 給 <PreviewCanvas>；編輯面自己也要用其中幾個
// 輔助函式（選項顯示順序、主題變數、計算變數 token），兩面因此共用同一份狀態。
const {
  constantSumTotal,
  defaultLinearScaleValue,
  linearScaleFillPercent,
  previewOptions,
  previewThemeVars,
  renderCalculationTokens,
  selectionBasedSourceElement,
  selectionBasedSourceLabel,
} = providePreviewRuntime();

function removeQuestion(questionId: string) {
  const impactMessage = store.questionRemovalMessage(questionId);
  if (impactMessage && !confirm(impactMessage)) return;

  questionCollapse.remove(questionId);
  store.removeQuestion(questionId);
}

// ── Drag state ──────────────────────────────────────────────────────────────
const dragQId = ref<string | null>(null);
const dropTarget = ref<{ type: 'zone'; pageId: string; index: number } | { type: 'tab'; pageId: string } | null>(null);
const dragPageId = ref<string | null>(null);
const pageDropTarget = ref<{ pageId: string; position: 'before' | 'after' } | null>(null);

// ── Validation error parsing ────────────────────────────────────────────────
const parsedErrors = computed<ParsedError[]>(() =>
  Object.entries(store.validationErrors).map(
    ([key, msgs]) => parseErrorKey(key, msgs, store.schema?.pages ?? [], questionNumberMap.value),
  ),
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

const questionNumberMap = computed(() => buildQuestionNumberMap(store.schema?.pages ?? []));

const selectedPageVisibleElements = computed(() => visibleSurveyElements(store.selectedPage?.elements ?? []));

function elementSchemaIndex(element: SurveyElement): number {
  return store.selectedPage?.elements.findIndex((candidate) => candidate.id === element.id) ?? 0;
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
  const impact = store.fieldImpact(el.id);
  if (impact?.locked_properties.includes('used_option_values')) {
    const optionLabel = el.options[i]?.label || `選項 ${i + 1}`;
    if (!confirm(`此題已有 ${impact.answer_count} 筆歷史答案。「${optionLabel}」若曾被填答，發布時將禁止刪除其 option value。確定先從草稿移除此選項？`)) return;
  }

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
  mobileWorkspace.value = 'properties';
}

function openLogic(qId: string, jumpLogic = false) {
  store.selectElement(qId);
  store.rightPanelTab = 'logic';
  store.jumpLogicOpen = jumpLogic;
  mobileWorkspace.value = 'logic';
}

// ── Page management ─────────────────────────────────────────────────────────
function deletePage(pageId: string) {
  if (!store.schema) return;
  const p = store.schema.pages.find((pp) => pp.id === pageId);
  if (!p || p.kind === 'welcome' || p.kind === 'thank_you') return;
  const qPages = store.schema.pages.filter((pp) => (pp.kind ?? 'question') === 'question');
  if (qPages.length <= 1) { alert('至少需要保留一個問題頁'); return; }
  const count = visibleSurveyElements(p.elements ?? []).length;
  const impactMessage = store.pageRemovalMessage(pageId);
  const confirmation = impactMessage ?? `刪除「${p.title || '未命名頁面'}」？此頁包含 ${count} 道題目，將一併移除。`;
  if (count > 0 && !confirm(confirmation)) return;
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
  <div v-if="store.isLoading" class="sb-loading" role="status" aria-live="polite">
    載入問卷設計中…
  </div>

  <!-- ── Main ── -->
  <div v-else-if="store.schema" class="sb-body">

    <nav v-if="!store.isPreviewMode" class="sb-workspace-nav" aria-label="問卷建立器工作區">
      <div class="sb-workspace-tabs" aria-label="編輯工作區">
        <button
          v-for="workspace in ([['canvas', '畫布'], ['library', '題型庫'], ['properties', '屬性'], ['logic', '邏輯']] as const)"
          :id="`sb-workspace-tab-${workspace[0]}`"
          :key="workspace[0]"
          type="button"
          class="sb-workspace-tab"
          :aria-pressed="mobileWorkspace === workspace[0]"
          :tabindex="mobileWorkspace === workspace[0] ? 0 : -1"
          :class="{ active: mobileWorkspace === workspace[0] }"
          @click="selectWorkspace(workspace[0])"
          @keydown="onWorkspaceKeydown($event, workspace[0])"
        >{{ workspace[1] }}</button>
      </div>
      <button type="button" class="sb-workspace-settings" aria-label="問卷設定" @click="store.showSettingsModal = true">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
      </button>
    </nav>

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
    <main
      id="sb-workspace-canvas"
      class="sb-canvas"
      :class="{ 'is-mobile-active': mobileWorkspace === 'canvas' }"
      :role="!store.isPreviewMode ? 'region' : undefined"
      :aria-label="!store.isPreviewMode ? '問卷畫布' : undefined"
    >
      <div class="sb-canvas-inner">

        <!-- Page tabs (sticky) -->
        <div v-if="!store.isPreviewMode" class="sb-page-tabs-wrap">
          <div ref="pageTabs" class="sb-page-tabs" role="tablist" aria-label="問卷頁面">
            <button
              v-if="store.welcomePage"
              class="sb-page-tab welcome"
              :id="`sb-page-tab-${store.welcomePage.id}`"
              role="tab"
              :aria-selected="store.selectedPageId === store.welcomePage.id"
              aria-controls="sb-page-panel"
              :tabindex="store.selectedPageId === store.welcomePage.id ? 0 : -1"
              :data-page-id="store.welcomePage.id"
              :class="{ active: store.selectedPageId === store.welcomePage.id }"
              @click="selectPage(store.welcomePage.id)"
              @keydown="onPageTabKeydown($event, store.welcomePage.id)"
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
              draggable="true"
              @dragstart="onPageDragStart($event, page.id)"
              @dragend="onPageDragEnd"
              @dragover="onPageTabDragOver($event, page.id)"
              @dragleave="onPageTabDragLeave($event, page.id)"
              @drop="onPageTabDrop($event, page.id)"
            >
              <button
                type="button"
                class="sb-page-tab-select"
                :id="`sb-page-tab-${page.id}`"
                role="tab"
                :aria-selected="store.selectedPageId === page.id"
                aria-controls="sb-page-panel"
                :tabindex="store.selectedPageId === page.id ? 0 : -1"
                :data-page-id="page.id"
                @click="selectPage(page.id)"
                @keydown="onPageTabKeydown($event, page.id)"
              >
                <span class="sb-page-tab-num" :class="{ 'error-num': errorPageIds.has(page.id) }">P{{ String(i + 1).padStart(2, '0') }}</span>
                <span>{{ page.title || '未命名頁面' }}</span>
                <span v-if="errorPageIds.has(page.id)" class="sb-page-tab-error-dot" title="此頁有驗證錯誤">!</span>
                <span class="sb-page-tab-count">{{ visibleSurveyElements(page.elements).length }}</span>
              </button>
              <button
                class="sb-page-tab-close"
                type="button"
                @click.stop="deletePage(page.id)"
                title="刪除此頁"
                :aria-label="`刪除頁面：${page.title || `第 ${i + 1} 頁`}`"
              >×</button>
            </div>

            <button
              v-if="store.thankYouPage"
              class="sb-page-tab thanks"
              :id="`sb-page-tab-${store.thankYouPage.id}`"
              role="tab"
              :aria-selected="store.selectedPageId === store.thankYouPage.id"
              aria-controls="sb-page-panel"
              :tabindex="store.selectedPageId === store.thankYouPage.id ? 0 : -1"
              :data-page-id="store.thankYouPage.id"
              :class="{ active: store.selectedPageId === store.thankYouPage.id }"
              @click="selectPage(store.thankYouPage.id)"
              @keydown="onPageTabKeydown($event, store.thankYouPage.id)"
            >
              <span class="sb-page-tab-num">感謝</span>
            </button>
          </div>
          <button class="sb-page-tab-add" type="button" @click="store.addPage()">
            + 新增頁面
          </button>
        </div>

        <!-- ── Preview mode ── -->
        <PreviewCanvas v-if="store.isPreviewMode" />

        <!-- ── Edit mode ── -->
        <div
          v-else
          id="sb-page-panel"
          class="sb-edit-surface survey-preview-surface"
          role="tabpanel"
          :aria-labelledby="selectedPageTabId"
          :style="previewThemeVars"
        >
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
                v-html="renderCalculationTokens(store.selectedPage.thank_you_settings.message, false)"
              ></div>
              <div v-else class="sb-special-title">感謝您的填寫！</div>
            </div>
          </template>

          <!-- Question list with drop zones -->
          <template v-else-if="store.selectedPage">
            <template v-if="selectedPageVisibleElements.length === 0">
              <div class="sb-empty-page">
                <div class="sb-empty-page-icon">＋</div>
                <p>從題型庫加入此頁面</p>
                <button class="sb-btn" type="button" @click="selectWorkspace('library')">瀏覽題型</button>
              </div>
            </template>
            <template v-else>
              <template v-for="element in selectedPageVisibleElements" :key="element.id">
                <!-- Drop zone before each card -->
                <div
                  class="sb-drop-zone"
                  :class="{ over: isZoneActive(store.selectedPage.id, elementSchemaIndex(element)) }"
                  @dragover="onDragOverZone($event, store.selectedPage.id, elementSchemaIndex(element))"
                  @dragleave="onDragLeave"
                  @drop="onDropZone($event, store.selectedPage.id, elementSchemaIndex(element))"
                >
                  <span v-if="isZoneActive(store.selectedPage.id, elementSchemaIndex(element))">放開以移動至此</span>
                </div>

                <!-- Question card -->
                <article
                  class="sb-card"
                  :class="{
                    selected: store.selectedElementId === element.id,
                    'is-dragging': dragQId === element.id,
                    'is-hidden-field': element.is_hidden,
                    'has-error': errorElementIds.has(element.id),
                    'is-collapsed': questionCollapse.isCollapsed(element.id),
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
                      <span
                        v-if="questionCollapse.isCollapsed(element.id) && isContentBlockType(element.type)"
                        class="sb-card-collapsed-title"
                      >{{ getQuestionType(element.type).label }}</span>
                      <input
                        v-else-if="element.type === 'section_title'"
                        class="sb-card-title"
                        :class="{ empty: !element.description }"
                        :value="contentBlockText(element)"
                        placeholder="標題…"
                        @input="updateContentBlockText(element, ($event.target as HTMLInputElement).value)"
                        @click.stop
                      >
                      <div v-else-if="element.type === 'description_block' && !questionCollapse.isCollapsed(element.id)" class="sb-card-rich-editor" @click.stop>
                        <SurveyRichEditor
                          :model-value="contentBlockText(element)"
                          placeholder="說明文字…"
                          :upload-url="props.endpoints.uploadImage"
                          :csrf-token="props.csrfToken"
                          @update:model-value="updateContentBlockText(element, $event)"
                        />
                      </div>
                      <textarea
                        v-else-if="element.type === 'quote_block' && !questionCollapse.isCollapsed(element.id)"
                        class="sb-card-quote"
                        :value="contentBlockText(element)"
                        rows="2"
                        placeholder="引言內容…"
                        @input="updateContentBlockText(element, ($event.target as HTMLTextAreaElement).value)"
                        @click.stop
                      />
                      <div v-else-if="element.type === 'divider' && !questionCollapse.isCollapsed(element.id)" class="sb-card-divider" aria-label="分隔線"></div>
                      <input
                        v-else-if="!isContentBlockType(element.type)"
                        class="sb-card-title"
                        :class="{ empty: !element.label }"
                        v-model="element.label"
                        :placeholder="`未命名${getQuestionType(element.type).label}`"
                        @input="store.markDirty()"
                        @click.stop
                      />
                      <textarea
                        v-if="!questionCollapse.isCollapsed(element.id) && !['section_title', 'description_block', 'divider', 'quote_block'].includes(element.type)"
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
                      <button v-if="element.show_if_field_key || (element.show_if?.conditions ?? []).length > 0" type="button" class="sb-badge amber sb-badge-btn" @click.stop="openLogic(element.id)">條件</button>
                      <button v-if="hasActiveJumpLogic(element)" type="button" class="sb-badge violet sb-badge-btn" @click.stop="openLogic(element.id, true)">跳題</button>
                    </div>
                    <button
                      class="sb-card-collapse"
                      :class="{ collapsed: questionCollapse.isCollapsed(element.id) }"
                      type="button"
                      :title="questionCollapse.isCollapsed(element.id) ? '展開題目' : '收合題目'"
                      :aria-label="questionCollapse.isCollapsed(element.id) ? '展開題目' : '收合題目'"
                      :aria-expanded="!questionCollapse.isCollapsed(element.id)"
                      @click.stop="questionCollapse.toggle(element.id)"
                    >
                      <svg viewBox="0 0 20 20" aria-hidden="true">
                        <path d="m5.5 12.5 4.5-4.5 4.5 4.5" />
                      </svg>
                    </button>
                  </div>

                  <!-- Select preview -->
                  <div v-if="element.type === 'select'" class="sb-card-body">
                    <select class="survey-select" disabled>
                      <option>請選擇</option>
                      <option v-for="opt in previewOptions(element)" :key="opt.id">{{ opt.label }}</option>
                    </select>
                  </div>

                  <!-- Options editor -->
                  <div v-if="getQuestionType(element.type).supportsOptions && element.type !== 'constant_sum'" class="sb-card-body survey-choices sb-edit-options">
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
                    <div class="survey-rating-stars sb-fake-rating" :style="{ '--rating-count': Number((element.settings as any)?.count ?? 5) }">
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
                    <button v-if="elementSupportsLogic(element)" class="sb-quick-btn" type="button" @click.stop="openLogic(element.id)">⟁ 邏輯</button>
                    <div style="flex:1" />
                    <button class="sb-quick-btn danger" type="button" @click.stop="removeQuestion(element.id)">✕ 刪除</button>
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
              <button class="sb-add-q-btn" type="button" @click="selectWorkspace('library')">
                + 從題型庫新增題目
              </button>
            </div>
          </template>
        </div>
      </div>
    </main>

    <!-- ── Right panel ── -->
    <div
      v-if="!store.isPreviewMode"
      id="sb-workspace-panel"
      class="sb-workspace-panel"
      :class="{ 'is-mobile-active': mobileWorkspace !== 'canvas' }"
      role="region"
      :aria-label="store.rightPanelTab === 'library' ? '題型庫' : store.rightPanelTab === 'properties' ? '題目屬性' : '題目邏輯'"
    >
      <RightPanel :guide-url="props.guideUrl" />
    </div>

  </div><!-- /sb-body -->
</template>
