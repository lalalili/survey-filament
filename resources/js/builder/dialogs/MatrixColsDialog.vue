<script setup lang="ts">
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';

export interface MatrixColItem { id: string; label: string; }

export interface MatrixColsDialogState {
  elementId: string;
  cols: MatrixColItem[];
}

const MATRIX_PRESETS: Record<string, string[]> = {
  '喜歡系列': ['非常不喜歡', '不喜歡', '普通', '喜歡', '非常喜歡'],
  '滿意系列': ['非常不滿意', '不滿意', '普通', '滿意', '非常滿意'],
  '同意系列': ['非常不同意', '不同意', '普通', '同意', '非常同意'],
  '是否': ['是', '否'],
};

const model = defineModel<MatrixColsDialogState | null>();
const store = useSurveyBuilderStore();

function applyPreset(preset: string) {
  if (!model.value) return;
  model.value.cols = MATRIX_PRESETS[preset].map((label) => ({
    id: `col_${Math.random().toString(36).slice(2, 9)}`, label,
  }));
}

function reverse() {
  if (!model.value) return;
  model.value.cols = [...model.value.cols].reverse();
}

function addAfter(index: number) {
  if (!model.value || model.value.cols.length >= 11) return;
  model.value.cols.splice(index + 1, 0, { id: `col_${Math.random().toString(36).slice(2, 9)}`, label: '' });
}

function remove(index: number) {
  if (!model.value || model.value.cols.length <= 1) return;
  model.value.cols.splice(index, 1);
}

function apply() {
  if (!model.value) return;
  store.updateQuestion(model.value.elementId, { matrix_cols: model.value.cols });
  model.value = null;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="model" class="sb-settings-overlay" @click.self="model = null">
      <div class="sb-matrix-dialog">
        <div class="sb-settings-header">
          <h2>矩陣答案設定</h2>
          <button class="sb-settings-close" type="button" @click="model = null">✕</button>
        </div>
        <div class="sb-matrix-dialog-body">
          <p class="sb-matrix-dialog-desc">設定矩陣題每一欄的可選答案。答案上限為 11 個。</p>
          <div class="sb-matrix-dialog-presets">
            <button
              v-for="preset in Object.keys(MATRIX_PRESETS)"
              :key="preset"
              class="sb-btn-sm"
              type="button"
              @click="applyPreset(preset)"
            >{{ preset }}</button>
            <button class="sb-btn-sm" type="button" style="margin-left:auto" @click="reverse">反向排序 ↕</button>
          </div>
          <div class="sb-matrix-dialog-list">
            <div v-for="(col, ci) in model.cols" :key="col.id" class="sb-matrix-dialog-row">
              <span class="sb-matrix-dialog-num">{{ ci + 1 }}</span>
              <input class="sb-prop-input" v-model="col.label" placeholder="例如 滿意、普通、不滿意" />
              <button
                class="sb-matrix-dialog-act"
                type="button"
                :disabled="model.cols.length >= 11"
                @click="addAfter(ci)"
              >⊕</button>
              <button
                class="sb-matrix-dialog-act danger"
                type="button"
                :disabled="model.cols.length <= 1"
                @click="remove(ci)"
              >⊖</button>
            </div>
          </div>
        </div>
        <div class="sb-settings-footer">
          <button class="sb-btn" type="button" @click="model = null">取消</button>
          <button class="sb-btn accent" type="button" @click="apply">確定</button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
