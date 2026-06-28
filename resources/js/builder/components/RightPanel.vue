<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { getQuestionType, questionTypes } from '../registry/questionTypes';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';
import type { AudienceListColumn, Condition, SurveyElement, SurveyOptionAction } from '../types/schema';
import { elementSupportsJump, hasActiveJumpLogic, typeCategory } from '../utils/builderHelpers';
import MatrixColsDialog from '../dialogs/MatrixColsDialog.vue';
import type { MatrixColsDialogState } from '../dialogs/MatrixColsDialog.vue';
import NumberDialog from '../dialogs/NumberDialog.vue';
import type { NumberDialogState } from '../dialogs/NumberDialog.vue';
import RatingDialog from '../dialogs/RatingDialog.vue';
import type { RatingDialogState } from '../dialogs/RatingDialog.vue';
import NpsDialog from '../dialogs/NpsDialog.vue';
import type { NpsDialogState } from '../dialogs/NpsDialog.vue';
import CascadeDialog from '../dialogs/CascadeDialog.vue';
import type { CascadeDialogState } from '../dialogs/CascadeDialog.vue';

const store = useSurveyBuilderStore();
const canManageAdvancedFields = computed(() => store.capabilities.can_manage_advanced_fields);

// ── Dialog state ─────────────────────────────────────────────────────
const matrixColsDialog = ref<MatrixColsDialogState | null>(null);
const numberDialog = ref<NumberDialogState | null>(null);
const ratingDialog = ref<RatingDialogState | null>(null);
const npsDialog = ref<NpsDialogState | null>(null);
const cascadeDialog = ref<CascadeDialogState | null>(null);

function openMatrixColsDialog(elementId: string) {
  const el = store.schema?.pages.flatMap((p) => p.elements).find((e) => e.id === elementId);
  if (!el) return;
  matrixColsDialog.value = { elementId, cols: (el.matrix_cols ?? []).map((c) => ({ ...c })) };
}

function openNumberDialog(elementId: string) {
  const el = store.schema?.pages.flatMap((p) => p.elements).find((e) => e.id === elementId);
  if (!el) return;
  const s = el.settings as Record<string, unknown>;
  numberDialog.value = {
    elementId,
    label: el.label || '未命名題目',
    min: s.min !== undefined && s.min !== null ? String(s.min) : '',
    max: s.max !== undefined && s.max !== null ? String(s.max) : '',
    decimal_places: s.decimal_places !== undefined && s.decimal_places !== null ? String(s.decimal_places) : '',
    unit: (s.unit as string) ?? '',
  };
}

function openRatingDialog(elementId: string) {
  const el = store.schema?.pages.flatMap((p) => p.elements).find((e) => e.id === elementId);
  if (!el) return;
  const s = el.settings as Record<string, unknown>;
  ratingDialog.value = {
    elementId,
    count: Number(s.count ?? 5),
    shape: (s.shape as RatingDialogState['shape']) ?? 'star',
    show_numbers: (s.show_numbers as boolean) ?? false,
  };
}

function openNpsDialog(elementId: string) {
  const el = store.schema?.pages.flatMap((p) => p.elements).find((e) => e.id === elementId);
  if (!el) return;
  const s = el.settings as Record<string, unknown>;
  npsDialog.value = {
    elementId,
    low_label:   (s.low_label  as string) ?? '非常不推薦',
    high_label:  (s.high_label as string) ?? '非常推薦',
    color_bands: (s.color_bands as boolean) ?? false,
  };
}

function openCascadeDialog(elementId: string) {
  const el = store.schema?.pages.flatMap((p) => p.elements).find((e) => e.id === elementId);
  if (!el) return;
  cascadeDialog.value = {
    elementId,
    levels: JSON.parse(JSON.stringify(el.cascade_levels ?? [])),
    data: JSON.parse(JSON.stringify(el.cascade_data ?? [])),
  };
}

// ── Audience personalization ─────────────────────────────────────────
const selectedAudienceList = computed(() => {
  const listId = store.schema?.settings?.personalization?.audience_list_id;
  if (!listId) return null;
  return store.audienceLists?.find((l) => String(l.id) === String(listId)) ?? null;
});

type AudienceColumnOption = {
  value: string;
  label: string;
};

function normalizeAudienceColumnOption(column: string | AudienceListColumn): AudienceColumnOption | null {
  if (typeof column === 'string') {
    return {
      value: column,
      label: column,
    };
  }

  const value = column.key ?? column.value ?? column.name ?? column.label;

  if (value === undefined || value === null || String(value).trim() === '') {
    return null;
  }

  const normalizedValue = String(value);
  const label = column.label !== undefined && column.label !== null && String(column.label).trim() !== ''
    ? String(column.label)
    : normalizedValue;

  return {
    value: normalizedValue,
    label: label === normalizedValue ? label : `${label} (${normalizedValue})`,
  };
}

const audienceColumnOptions = computed(() => (selectedAudienceList.value?.columns ?? [])
  .map((column) => normalizeAudienceColumnOption(column))
  .filter((column): column is AudienceColumnOption => column !== null));

// ── Selected element definition ──────────────────────────────────────
const selectedDefinition = computed(() =>
  store.selectedElement ? getQuestionType(store.selectedElement.type) : null,
);

// ── Library ──────────────────────────────────────────────────────────
const questionTypeGroups = [
  { label: '選擇題', types: ['single_choice', 'multiple_choice', 'select', 'cascade_select', 'matrix_single', 'matrix_multi', 'date'] },
  { label: '輸入題', types: ['short_text', 'long_text', 'number', 'constant_sum'] },
  { label: '評分題', types: ['ranking', 'rating', 'nps', 'linear_scale'] },
  { label: '內容與樣式', types: ['section_title', 'description_block', 'quote_block', 'divider'] },
];

const canReplaceWithAllQuestionTypes = computed(() => (
  canManageAdvancedFields.value
    && !store.isPreviewMode
    && (store.selectedPage?.kind ?? 'question') === 'question'
));

function replaceWithAllQuestionTypes() {
  if (!canReplaceWithAllQuestionTypes.value) return;
  const confirmed = confirm('這會刪除現有問卷頁與題目，並依題型庫重新建立 5 個分類頁。歡迎頁與感謝頁會保留。確定繼續？');
  if (!confirmed) return;
  store.replaceQuestionPagesWithAllQuestionTypes(questionTypeGroups.map((group) => ({
    label: group.label,
    types: questionTypes
      .filter((qt) => group.types.includes(qt.id))
      .map((qt) => qt.id),
  })));
}

// ── Jump logic ───────────────────────────────────────────────────────
watch(() => store.selectedElementId, () => {
  const el = store.selectedElement;
  store.jumpLogicOpen = el ? hasActiveJumpLogic(el) : false;
});

const jumpTargetPages = computed(() => {
  if (!store.selectedElement || !store.schema) return [];
  const pages = store.schema.pages;
  const ci = pages.findIndex((p) => p.elements.some((e) => e.id === store.selectedElementId));
  return ci === -1 ? [] : pages.slice(ci + 1);
});

function decodeJump(val: string): SurveyOptionAction {
  if (val === 'end_survey') return { type: 'end_survey' };
  if (val.startsWith('page:')) return { type: 'go_to_page', target_page_id: val.slice(5) };
  return { type: 'next_page' };
}
function encodeJump(action: SurveyOptionAction | null | undefined): string {
  if (!action || action.type === 'next_page') return 'next_page';
  if (action.type === 'end_survey') return 'end_survey';
  if (action.type === 'go_to_page' && action.target_page_id) return `page:${action.target_page_id}`;
  return 'next_page';
}
function onJumpChange(elementId: string, optionId: string, raw: string) {
  const a = decodeJump(raw);
  store.updateOptionAction(elementId, optionId, a.type === 'next_page' ? null : a);
}

// ── Show-if conditions ───────────────────────────────────────────────
const showIfTargetOptions = computed(() =>
  (store.schema?.pages ?? [])
    .flatMap((p) => p.elements)
    .filter((el) => {
      const d = getQuestionType(el.type);
      return el.field_key && el.id !== store.selectedElementId && !d.type.startsWith('section_') && el.type !== 'description_block';
    })
    .map((el) => ({ value: el.field_key!, label: el.label || el.field_key! })),
);

function showIfConditionValueOptions(fieldKey: string): Array<{ value: string; label: string }> | null {
  const el = store.allElements.find((e) => e.field_key === fieldKey);
  if (!el) return null;
  const choiceTypes = ['single_choice', 'multiple_choice', 'select'];
  if (!choiceTypes.includes(el.type)) return null;
  return el.options
    .filter((o) => o.label || o.value)
    .map((o) => ({ value: String(o.value ?? o.id), label: o.label || String(o.value ?? o.id) }));
}

function addShowIfCondition(el: SurveyElement) {
  const conditions = [...(el.show_if?.conditions ?? [])];
  conditions.push({ field_key: showIfTargetOptions.value[0]?.value ?? '', op: 'equals', value: '' });
  store.updateShowIf(el.id, { logic: el.show_if?.logic ?? 'and', conditions });
}
function updateShowIfCondition(el: SurveyElement, i: number, patch: Partial<Condition>) {
  const conditions = [...(el.show_if?.conditions ?? [])];
  conditions[i] = { ...conditions[i], ...patch };
  store.updateShowIf(el.id, { logic: el.show_if?.logic ?? 'and', conditions });
}
function removeShowIfCondition(el: SurveyElement, i: number) {
  const conditions = [...(el.show_if?.conditions ?? [])];
  conditions.splice(i, 1);
  store.updateShowIf(el.id, conditions.length === 0 ? null : { logic: el.show_if?.logic ?? 'and', conditions });
}
</script>

<template>
  <aside class="sb-right">
    <!-- Tabs -->
    <div class="sb-rp-tabs">
      <button class="sb-rp-tab" :class="{ active: store.rightPanelTab === 'library' }" @click="store.rightPanelTab = 'library'">題型庫</button>
      <button class="sb-rp-tab" :class="{ active: store.rightPanelTab === 'properties' }" @click="store.rightPanelTab = 'properties'">
        屬性<span v-if="store.selectedElement" class="sb-rp-tab-dot" />
      </button>
      <button class="sb-rp-tab" :class="{ active: store.rightPanelTab === 'logic' }" @click="store.rightPanelTab = 'logic'">邏輯</button>
    </div>

    <div class="sb-rp-body">

      <!-- ── 題型庫 ── -->
      <template v-if="store.rightPanelTab === 'library'">
        <div v-if="canReplaceWithAllQuestionTypes" class="sb-qlib-tools">
          <button class="sb-qlib-bulk-btn" type="button" @click="replaceWithAllQuestionTypes">
            加入所有題型
          </button>
        </div>
        <div v-for="group in questionTypeGroups" :key="group.label" class="sb-qlib-section">
          <div class="sb-qlib-heading">{{ group.label }}</div>
          <div class="sb-qlib-grid">
            <button
              v-for="t in questionTypes.filter(qt => group.types.includes(qt.id))"
              :key="t.id"
              type="button"
              class="sb-qlib-item"
              :class="typeCategory(t.type)"
              :disabled="(store.selectedPage?.kind ?? 'question') !== 'question'"
              @click="store.addQuestion(t.id)"
            >
              <span class="sb-qlib-icon">{{ t.icon }}</span>
              <span>{{ t.label }}</span>
            </button>
          </div>
        </div>
      </template>

      <!-- ── 屬性 ── -->
      <template v-else-if="store.rightPanelTab === 'properties'">

        <!-- No element: page + survey settings -->
        <template v-if="!store.selectedElement">
          <!-- Page settings (question pages only) -->
          <div v-if="store.selectedPage && (store.selectedPage.kind ?? 'question') === 'question'" class="sb-prop-section">
            <div class="sb-prop-heading">頁面</div>
            <label class="sb-prop-row col">
              <span class="sb-prop-label">頁面標題</span>
              <input class="sb-prop-input" :value="store.selectedPage.title" @input="store.updatePageTitle(store.selectedPage!.id, ($event.target as HTMLInputElement).value)" />
            </label>
            <button class="sb-prop-action" type="button" @click="store.duplicatePage(store.selectedPage!.id)">⊕ 複製此頁</button>
            <button
              class="sb-prop-action danger"
              type="button"
              :disabled="!store.schema || store.schema.pages.length <= 1"
              @click="store.schema && store.schema.pages.length > 1 && store.removePage(store.selectedPage!.id)"
            >⊖ 刪除此頁</button>
          </div>

          <!-- Calculations -->
          <div class="sb-prop-section">
            <div class="sb-prop-heading-row">
              <span class="sb-prop-heading" style="margin:0">計算變數</span>
              <button class="sb-btn-sm" type="button" @click="store.addCalculation()">+ 新增</button>
            </div>
            <div v-if="(store.schema?.calculations ?? []).length === 0" class="sb-prop-empty">尚未設定計算變數</div>
            <div v-for="calc in store.schema?.calculations" :key="calc.id" class="sb-calc-card">
              <label class="sb-prop-row col">
                <span class="sb-prop-label">變數代碼</span>
                <input :value="calc.key" class="sb-prop-input" placeholder="例如 total_score" @input="store.updateCalculation(calc.id!, { key: ($event.target as HTMLInputElement).value })" />
                <span class="sb-prop-help">給系統計算用的代碼，建議使用英文、數字或底線。</span>
              </label>
              <label class="sb-prop-row col">
                <span class="sb-prop-label">顯示名稱</span>
                <input :value="calc.label" class="sb-prop-input" placeholder="例如 總分" @input="store.updateCalculation(calc.id!, { label: ($event.target as HTMLInputElement).value })" />
                <span class="sb-prop-help">後台報表與分數設定中顯示的名稱。</span>
              </label>
              <div class="sb-calc-row">
                <label class="sb-prop-row col" style="margin:0;flex:1">
                  <span class="sb-prop-label">初始分數</span>
                  <input :value="calc.initial_value ?? 0" type="number" class="sb-prop-input" @input="store.updateCalculation(calc.id!, { initial_value: Number(($event.target as HTMLInputElement).value) || 0 })" />
                </label>
                <button class="sb-btn-sm danger" type="button" @click="store.removeCalculation(calc.id!)">刪除</button>
              </div>
            </div>
          </div>

        </template>

        <!-- Element selected: properties -->
        <template v-else>
          <div class="sb-prop-section">
            <div class="sb-prop-heading">題目</div>
            <label class="sb-prop-row col">
              <span class="sb-prop-label">欄位名稱</span>
              <input class="sb-prop-input" v-model="store.selectedElement.label" @input="store.markDirty()" />
            </label>
            <label class="sb-prop-row col">
              <span class="sb-prop-label">說明</span>
              <textarea class="sb-prop-input" rows="2" v-model="store.selectedElement.description" @input="store.markDirty()" />
            </label>
            <label v-if="selectedDefinition?.supportsPlaceholder" class="sb-prop-row col">
              <span class="sb-prop-label">填答提示文字</span>
              <input class="sb-prop-input" v-model="store.selectedElement.placeholder" @input="store.markDirty()" />
              <span class="sb-prop-help">顯示在輸入框內，提醒填答者要填什麼內容。</span>
            </label>
            <label v-if="selectedDefinition?.supportsRequired" class="sb-prop-row">
              <span class="sb-prop-label">必填</span>
              <input type="checkbox" v-model="store.selectedElement.required" @change="store.markDirty()" />
            </label>
          </div>

          <!-- Type-specific settings -->
          <div v-if="['number', 'linear_scale', 'constant_sum', 'nps', 'rating', 'file_upload'].includes(store.selectedElement.type)" class="sb-prop-section">
            <div class="sb-prop-heading">題型設定</div>
            <template v-if="store.selectedElement.type === 'rating'">
              <button class="sb-btn" style="width:100%;justify-content:center" type="button" @click="openRatingDialog(store.selectedElement!.id)">星級評分設定</button>
              <div class="sb-number-summary">
                <span>{{ (store.selectedElement.settings as any)?.count ?? 5 }} 級</span>
                <span>{{ { star: '星星', heart: '心心', check: '勾勾', thumb: '讚讚' }[((store.selectedElement!.settings as any)?.shape ?? 'star') as string] ?? '星星' }}</span>
                <span v-if="(store.selectedElement.settings as any)?.show_numbers">顯示數字</span>
              </div>
            </template>
            <template v-else-if="store.selectedElement.type === 'number' || store.selectedElement.type === 'linear_scale'">
              <button class="sb-btn" style="width:100%;justify-content:center" type="button" @click="openNumberDialog(store.selectedElement!.id)">設定數字區間</button>
              <div class="sb-number-summary">
                <span v-if="store.selectedElement.settings.min !== null && store.selectedElement.settings.min !== undefined">最小 {{ store.selectedElement.settings.min }}</span>
                <span v-if="store.selectedElement.settings.max !== null && store.selectedElement.settings.max !== undefined">最大 {{ store.selectedElement.settings.max }}</span>
                <span v-if="store.selectedElement.settings.step !== null && store.selectedElement.settings.step !== undefined">間距 {{ store.selectedElement.settings.step }}</span>
                <span v-if="(store.selectedElement.settings as any).decimal_places !== null && (store.selectedElement.settings as any).decimal_places !== undefined">小數 {{ (store.selectedElement.settings as any).decimal_places }} 位</span>
                <span v-if="store.selectedElement.settings.unit">單位：{{ store.selectedElement.settings.unit }}</span>
              </div>
            </template>
            <template v-else-if="store.selectedElement.type === 'constant_sum'">
              <div class="sb-prop-grid-2">
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">合計目標</span>
                  <input :value="store.selectedElement.settings.total ?? ''" type="number" placeholder="例如 100" class="sb-prop-input" @input="store.updateElementSettings(store.selectedElement!.id, { total: Number(($event.target as HTMLInputElement).value) })" />
                  <span class="sb-prop-help">所有選項輸入值加總後應等於此數字。</span>
                </label>
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">單位</span>
                  <input :value="store.selectedElement.settings.unit ?? ''" placeholder="例如 分、%、元" class="sb-prop-input" @input="store.updateElementSettings(store.selectedElement!.id, { unit: ($event.target as HTMLInputElement).value })" />
                  <span class="sb-prop-help">顯示在填答數字旁，協助填答者理解數值用途。</span>
                </label>
              </div>
            </template>
            <template v-else-if="store.selectedElement.type === 'nps'">
              <button class="sb-btn" style="width:100%;justify-content:center" type="button" @click="openNpsDialog(store.selectedElement!.id)">選項設定</button>
              <div class="sb-number-summary">
                <span v-if="store.selectedElement.settings.low_label">{{ store.selectedElement.settings.low_label }}</span>
                <span v-if="store.selectedElement.settings.high_label">{{ store.selectedElement.settings.high_label }}</span>
                <span v-if="(store.selectedElement.settings as any)?.color_bands">色彩分段</span>
              </div>
            </template>
            <template v-else>
              <div class="sb-prop-grid-2">
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最大檔案大小 (MB)</span>
                  <input :value="store.selectedElement.settings.max_size_mb ?? ''" type="number" placeholder="例如 10" class="sb-prop-input" @input="store.updateElementSettings(store.selectedElement!.id, { max_size_mb: Number(($event.target as HTMLInputElement).value) })" />
                  <span class="sb-prop-help">限制單一上傳檔案的容量；留空時使用系統預設限制。</span>
                </label>
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">允許檔案格式</span>
                  <input :value="(store.selectedElement.settings.allowed_mimes as string[] | undefined)?.join(',') ?? ''" placeholder="pdf,jpg,png" class="sb-prop-input" @input="store.updateElementSettings(store.selectedElement!.id, { allowed_mimes: ($event.target as HTMLInputElement).value.split(',').map(s => s.trim()).filter(Boolean) })" />
                  <span class="sb-prop-help">以逗號分隔可接受的副檔名或 MIME 類型；留空時不額外限制格式。</span>
                </label>
              </div>
            </template>
          </div>

          <!-- Matrix settings -->
          <div v-if="['matrix_single', 'matrix_multi'].includes(store.selectedElement.type)" class="sb-prop-section">
            <div class="sb-prop-heading-row">
              <span class="sb-prop-heading" style="margin:0">評分項目</span>
              <button class="sb-btn-sm" type="button" @click="store.addMatrixRow(store.selectedElement!.id)">+ 新增</button>
            </div>
            <p class="sb-prop-hint">每一列是一個要讓填答者評估的項目。</p>
            <label class="sb-prop-row">
              <span class="sb-prop-label">隨機排列列</span>
              <input
                type="checkbox"
                :checked="Boolean((store.selectedElement.settings as any)?.randomize_rows)"
                @change="store.updateElementSettings(store.selectedElement!.id, { randomize_rows: ($event.target as HTMLInputElement).checked })"
              />
            </label>
            <div v-for="(row, ri) in store.selectedElement.matrix_rows" :key="row.id" class="sb-matrix-row-edit">
              <span class="sb-matrix-row-num">{{ ri + 1 }}</span>
              <input :value="row.label" class="sb-prop-input" placeholder="例如 服務態度" @input="row.label = ($event.target as HTMLInputElement).value; store.markDirty()" />
              <button
                class="sb-btn-sm danger"
                type="button"
                :disabled="(store.selectedElement.matrix_rows?.length ?? 0) <= 1"
                @click="store.selectedElement!.matrix_rows!.splice(ri, 1); store.markDirty()"
              >－</button>
            </div>
            <div class="sb-prop-heading-row" style="margin-top:12px">
              <span class="sb-prop-heading" style="margin:0">可選答案</span>
              <button class="sb-btn-sm" type="button" @click="openMatrixColsDialog(store.selectedElement!.id)">設定答案</button>
            </div>
            <p class="sb-prop-hint">每一欄是填答者可以選擇的答案，例如差、普通、好。</p>
            <div class="sb-matrix-cols-preview">
              <span v-for="col in store.selectedElement.matrix_cols" :key="col.id" class="sb-matrix-col-chip">{{ col.label }}</span>
            </div>
          </div>

          <!-- Cascade select settings -->
          <div v-if="store.selectedElement.type === 'cascade_select'" class="sb-prop-section">
            <div class="sb-prop-heading">巢狀選擇設定</div>
            <div class="sb-cascade-prop-levels">
              <div v-for="(lvl, li) in (store.selectedElement.cascade_levels ?? [])" :key="lvl.id" class="sb-cascade-prop-level">
                <span class="sb-matrix-row-num">{{ li + 1 }}</span>
                <input
                  :value="lvl.label"
                  class="sb-prop-input"
                  placeholder="層級名稱"
                  @input="lvl.label = ($event.target as HTMLInputElement).value; store.markDirty()"
                />
              </div>
            </div>
            <div class="sb-cascade-prop-actions">
              <button
                class="sb-btn-sm"
                type="button"
                :disabled="(store.selectedElement.cascade_levels?.length ?? 0) >= 5"
                @click="store.selectedElement!.cascade_levels!.push({ id: `lvl_${Math.random().toString(36).slice(2,9)}`, label: `層級 ${(store.selectedElement!.cascade_levels!.length ?? 0) + 1}` }); store.markDirty()"
              >+ 增加層級</button>
              <button
                class="sb-btn-sm danger"
                type="button"
                :disabled="(store.selectedElement.cascade_levels?.length ?? 0) <= 1"
                @click="store.selectedElement!.cascade_levels!.pop(); store.markDirty()"
              >－ 移除層級</button>
            </div>
            <div style="margin-top:10px; display:flex; flex-direction:column; gap:6px;">
              <button class="sb-btn" style="width:100%;justify-content:center" type="button" @click="openCascadeDialog(store.selectedElement!.id)">📋 編輯選項資料</button>
              <div class="sb-cascade-data-summary">
                <span>{{ (store.selectedElement.cascade_data ?? []).length }} 個第一層選項</span>
                <span>{{ (store.selectedElement.cascade_levels ?? []).length }} 個層級</span>
              </div>
            </div>
          </div>

          <!-- Validation rules -->
          <div v-if="['short_text', 'long_text', 'number', 'multiple_choice', 'phone'].includes(store.selectedElement.type)" class="sb-prop-section">
            <div class="sb-prop-heading">填答限制</div>
            <template v-if="['short_text', 'long_text', 'phone'].includes(store.selectedElement.type)">
              <div class="sb-prop-grid-2">
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最少字數</span>
                  <input :value="store.selectedElement.validation_rules?.min_length ?? ''" type="number" placeholder="不限制" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { min_length: Number(($event.target as HTMLInputElement).value) })" />
                </label>
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最多字數</span>
                  <input :value="store.selectedElement.validation_rules?.max_length ?? ''" type="number" placeholder="不限制" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { max_length: Number(($event.target as HTMLInputElement).value) })" />
                </label>
              </div>
            </template>
            <template v-else-if="store.selectedElement.type === 'number'">
              <div class="sb-prop-grid-2">
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最小可填數值</span>
                  <input :value="store.selectedElement.validation_rules?.min_value ?? ''" type="number" placeholder="不限制" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { min_value: Number(($event.target as HTMLInputElement).value) })" />
                </label>
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最大可填數值</span>
                  <input :value="store.selectedElement.validation_rules?.max_value ?? ''" type="number" placeholder="不限制" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { max_value: Number(($event.target as HTMLInputElement).value) })" />
                </label>
              </div>
            </template>
            <template v-else>
              <div class="sb-prop-grid-2">
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最少選幾個</span>
                  <input :value="store.selectedElement.validation_rules?.min_selections ?? ''" type="number" placeholder="不限制" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { min_selections: Number(($event.target as HTMLInputElement).value) })" />
                </label>
                <label class="sb-prop-row col">
                  <span class="sb-prop-label">最多選幾個</span>
                  <input :value="store.selectedElement.validation_rules?.max_selections ?? ''" type="number" placeholder="不限制" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { max_selections: Number(($event.target as HTMLInputElement).value) })" />
                </label>
              </div>
            </template>
          </div>

          <!-- Hidden / personalized -->
          <div v-if="['short_text', 'long_text', 'number', 'address', 'date', 'time'].includes(store.selectedElement.type)" class="sb-prop-section">
            <div class="sb-prop-heading">個性化設定</div>
            <label class="sb-prop-row">
              <span class="sb-prop-label">個性化欄位</span>
              <input type="checkbox" :checked="store.selectedElement.is_hidden ?? false" @change="store.updateQuestion(store.selectedElement!.id, { is_hidden: ($event.target as HTMLInputElement).checked, personalized_key: ($event.target as HTMLInputElement).checked ? (store.selectedElement!.personalized_key ?? null) : null })" />
            </label>
            <p class="sb-prop-hint">開啟後，此題不會讓填答者手動填寫，答案會從個性化名單帶入。</p>
            <label v-if="store.selectedElement.is_hidden" class="sb-prop-row col">
              <span class="sb-prop-label">對應名單欄位</span>
              <template v-if="selectedAudienceList && audienceColumnOptions.length > 0">
                <select
                  class="sb-prop-input"
                  :value="store.selectedElement.personalized_key ?? ''"
                  @change="store.updateQuestion(store.selectedElement!.id, { personalized_key: ($event.target as HTMLSelectElement).value || null })"
                >
                  <option value="">未指定</option>
                  <option v-for="column in audienceColumnOptions" :key="column.value" :value="column.value">{{ column.label }}</option>
                </select>
                <span class="sb-prop-help">欄位來源：{{ selectedAudienceList.name }}</span>
              </template>
              <template v-else>
                <input class="sb-prop-input" :value="store.selectedElement.personalized_key ?? ''" placeholder="請先選擇個性化名單，或手動輸入變數鍵" @input="store.updateQuestion(store.selectedElement!.id, { personalized_key: ($event.target as HTMLInputElement).value || null })" />
                <span class="sb-prop-help">選擇個性化名單後，這裡會改為名單欄位下拉選單。</span>
              </template>
            </label>
          </div>

          <!-- Advanced system settings -->
          <div v-if="canManageAdvancedFields && !['section_title', 'description_block', 'divider', 'quote_block'].includes(store.selectedElement.type)" class="sb-prop-section">
            <div class="sb-prop-heading">進階設定</div>
            <div v-if="store.selectedElement.field_key" class="sb-prop-row col">
              <span class="sb-prop-label">欄位代碼</span>
              <code class="sb-field-key">{{ store.selectedElement.field_key }}</code>
              <span class="sb-prop-help">匯出資料、Webhook 與個性化名單對應時使用的系統欄位名稱。</span>
            </div>
            <template v-if="['short_text', 'long_text', 'phone'].includes(store.selectedElement.type)">
              <label class="sb-prop-row col">
                <span class="sb-prop-label">格式規則</span>
                <input :value="store.selectedElement.validation_rules?.regex ?? ''" placeholder="進階設定，可留空" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { regex: ($event.target as HTMLInputElement).value })" />
                <span class="sb-prop-help">使用正規表示式限制輸入格式，通常只有進階需求才需要設定。</span>
              </label>
              <label class="sb-prop-row col">
                <span class="sb-prop-label">格式錯誤提示</span>
                <input :value="store.selectedElement.validation_rules?.pattern_label ?? ''" placeholder="例如 請輸入正確格式" class="sb-prop-input" @input="store.updateElementValidationRules(store.selectedElement!.id, { pattern_label: ($event.target as HTMLInputElement).value })" />
                <span class="sb-prop-help">當填答內容不符合格式規則時顯示。</span>
              </label>
            </template>
          </div>

          <!-- Option capacity / score -->
          <div v-if="store.selectedElement.options.length > 0" class="sb-prop-section">
            <div class="sb-prop-heading">選項設定</div>
            <label class="sb-prop-row">
              <span class="sb-prop-label">隨機排列選項</span>
              <input
                type="checkbox"
                :checked="Boolean((store.selectedElement.settings as any)?.randomize_options)"
                @change="store.updateElementSettings(store.selectedElement!.id, { randomize_options: ($event.target as HTMLInputElement).checked })"
              />
            </label>
            <p class="sb-prop-hint">開啟後，每位填答者看到的選項順序會隨機調整，避免位置偏誤；隱藏選項與名額限制不受影響。</p>
            <p class="sb-prop-hint">可限制每個選項最多被選幾次；留空代表不限名額。</p>
            <div v-for="opt in store.selectedElement.options" :key="opt.id" class="sb-opt-prop">
              <span class="sb-opt-prop-label">{{ opt.label || opt.value }}</span>
              <input :value="opt.capacity ?? ''" type="number" min="0" placeholder="名額不限" class="sb-prop-input-sm" @input="opt.capacity = ($event.target as HTMLInputElement).value === '' ? null : Math.max(0, Number(($event.target as HTMLInputElement).value) || 0); store.markDirty()" />
              <label class="sb-opt-hidden-label">
                <input v-model="opt.is_hidden" type="checkbox" @change="store.markDirty()" /> 隱藏
              </label>
            </div>
          </div>

          <!-- Score delta -->
          <div v-if="store.selectedElement.options.length > 0 && (store.schema?.calculations ?? []).length > 0" class="sb-prop-section">
            <div class="sb-prop-heading">分數設定</div>
            <p class="sb-prop-hint">填答者選到該選項時，對指定計算變數加上或扣除多少分。</p>
            <div v-for="opt in store.selectedElement.options" :key="opt.id" class="sb-score-row">
              <span class="sb-opt-prop-label">{{ opt.label || opt.value }}</span>
              <label v-for="calc in store.schema?.calculations" :key="calc.id" class="sb-score-label">
                <span>{{ calc.label || calc.key }}</span>
                <input type="number" :value="opt.score_delta_json?.[calc.key] ?? 0" placeholder="加減分" class="sb-prop-input-sm" @input="store.updateOptionScoreDelta(store.selectedElement!.id, opt.id, calc.key, Number(($event.target as HTMLInputElement).value) || 0)" />
              </label>
            </div>
          </div>
        </template>
      </template>

      <!-- ── 邏輯 ── -->
      <template v-else-if="store.rightPanelTab === 'logic'">
        <template v-if="!store.selectedElement">
          <div class="sb-prop-empty-state">
            <div class="sb-prop-empty-icon">⟁</div>
            <p>選取題目以設定顯示條件與跳題邏輯</p>
          </div>
        </template>
        <template v-else>
          <!-- Show_if conditions -->
          <div class="sb-prop-section" v-if="!['section_title', 'description_block', 'divider', 'quote_block'].includes(store.selectedElement.type)">
            <div class="sb-prop-heading">顯示條件</div>
            <p class="sb-prop-hint">當以下條件成立時，才顯示此題目。</p>

            <template v-if="(store.selectedElement.show_if?.conditions ?? []).length === 0">
              <div class="sb-logic-empty">
                <template v-if="store.selectedElement.show_if_field_key">
                  <p class="sb-logic-empty-title">顯示條件（舊格式）</p>
                  <p class="sb-logic-empty-desc">此題使用舊版顯示條件格式，無法在此編輯。點擊「清除條件」可移除。</p>
                  <button class="sb-btn danger" type="button" @click="store.updateShowIf(store.selectedElement!.id, null)">清除條件</button>
                </template>
                <template v-else>
                  <p class="sb-logic-empty-title">未設定顯示條件</p>
                  <p class="sb-logic-empty-desc">此題會永遠顯示。設定條件以建立分支邏輯。</p>
                  <button class="sb-btn accent" type="button" @click="addShowIfCondition(store.selectedElement!)">+ 新增規則</button>
                </template>
              </div>
            </template>
            <template v-else>
              <select
                class="sb-prop-input"
                :value="store.selectedElement.show_if?.logic ?? 'and'"
                @change="store.updateShowIf(store.selectedElement!.id, { logic: ($event.target as HTMLSelectElement).value as 'and' | 'or' })"
              >
                <option value="and">所有條件成立</option>
                <option value="or">任一條件成立</option>
              </select>
              <div v-for="(condition, ci) in store.selectedElement.show_if?.conditions" :key="ci" class="sb-cond-block">
                <div class="sb-cond-block-header">
                  <span class="sb-cond-index">條件 {{ ci + 1 }}</span>
                  <button type="button" class="sb-cond-del" title="刪除此條件" @click="removeShowIfCondition(store.selectedElement!, ci)">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>
                  </button>
                </div>
                <select :value="condition.field_key" class="sb-prop-input-sm" style="width:100%" @change="updateShowIfCondition(store.selectedElement!, ci, { field_key: ($event.target as HTMLSelectElement).value, value: '' })">
                  <option v-for="opt in showIfTargetOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                </select>
                <div class="sb-cond-block-row">
                  <select :value="condition.op" class="sb-prop-input-sm" @change="updateShowIfCondition(store.selectedElement!, ci, { op: ($event.target as HTMLSelectElement).value as Condition['op'] })">
                    <option value="equals">等於</option>
                    <option value="not_equals">不等於</option>
                    <option value="contains">包含</option>
                    <option value="greater_than">大於</option>
                    <option value="less_than">小於</option>
                    <option value="is_empty">為空</option>
                    <option value="is_not_empty">不為空</option>
                  </select>
                  <select
                    v-if="showIfConditionValueOptions(condition.field_key)"
                    :value="condition.value ?? ''"
                    class="sb-prop-input-sm"
                    @change="updateShowIfCondition(store.selectedElement!, ci, { value: ($event.target as HTMLSelectElement).value })"
                  >
                    <option value="">— 選擇答案 —</option>
                    <option v-for="opt in showIfConditionValueOptions(condition.field_key)!" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
                  </select>
                  <input
                    v-else
                    :value="condition.value ?? ''"
                    class="sb-prop-input-sm"
                    placeholder="輸入值…"
                    @input="updateShowIfCondition(store.selectedElement!, ci, { value: ($event.target as HTMLInputElement).value })"
                  />
                </div>
              </div>
              <button type="button" class="sb-btn-sm" @click="addShowIfCondition(store.selectedElement!)">+ 新增條件</button>
            </template>
          </div>

          <!-- Jump logic -->
          <div v-if="elementSupportsJump(store.selectedElement) && store.selectedElement.options.length > 0" class="sb-prop-section" :class="hasActiveJumpLogic(store.selectedElement) ? 'has-jump' : ''">
            <div class="sb-prop-heading-row">
              <span class="sb-prop-heading" style="margin:0">跳題邏輯</span>
              <span v-if="hasActiveJumpLogic(store.selectedElement)" class="sb-badge amber">已設定</span>
            </div>
            <template v-if="store.jumpLogicOpen">
              <p class="sb-prop-hint">每個選項可指定下一步動作。</p>
              <div v-for="opt in store.selectedElement.options" :key="opt.id" class="sb-jump-row">
                <span class="sb-jump-opt-label">{{ opt.label || '（未命名）' }}</span>
                <select
                  :value="encodeJump(opt.action)"
                  class="sb-prop-input-sm"
                  @change="onJumpChange(store.selectedElement!.id, opt.id, ($event.target as HTMLSelectElement).value)"
                >
                  <option value="next_page">前往下一頁</option>
                  <option value="end_survey">結束問卷</option>
                  <optgroup v-if="jumpTargetPages.length > 0" label="前往區段">
                    <option v-for="page in jumpTargetPages" :key="page.id" :value="`page:${page.id}`">
                      {{ page.title || '未命名區段' }}
                    </option>
                  </optgroup>
                </select>
              </div>
              <button class="sb-prop-action" type="button" style="margin-top:6px" @click="store.jumpLogicOpen = false">收合</button>
            </template>
            <template v-else>
              <div class="sb-logic-empty">
                <p class="sb-logic-empty-desc">每個選項可指定下一步動作。</p>
                <button class="sb-logic-add-btn" type="button" @click="store.jumpLogicOpen = true">+ 設定跳題邏輯</button>
              </div>
            </template>
          </div>
        </template>
      </template>

    </div><!-- /sb-rp-body -->
  </aside>

  <!-- Dialogs (Teleport to body internally) -->
  <RatingDialog v-model="ratingDialog" />
  <NpsDialog v-model="npsDialog" />
  <NumberDialog v-model="numberDialog" />
  <CascadeDialog v-model="cascadeDialog" />
  <MatrixColsDialog v-model="matrixColsDialog" />
</template>
