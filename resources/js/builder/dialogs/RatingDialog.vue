<script setup lang="ts">
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';

const RATING_SHAPES = [
  { value: 'star',  label: '星星', icon: '★' },
  { value: 'heart', label: '心心', icon: '♥' },
  { value: 'check', label: '勾勾', icon: '✔' },
  { value: 'thumb', label: '讚讚', icon: '👍' },
] as const;

type RatingShape = 'star' | 'heart' | 'check' | 'thumb';

export interface RatingDialogState {
  elementId: string;
  count: number;
  shape: RatingShape;
  show_numbers: boolean;
}

const model = defineModel<RatingDialogState | null>();
const store = useSurveyBuilderStore();

function shapeIcon(shape: string): string {
  return RATING_SHAPES.find((s) => s.value === shape)?.icon ?? '★';
}

function apply() {
  if (!model.value) return;
  store.updateElementSettings(model.value.elementId, {
    count: model.value.count,
    shape: model.value.shape,
    show_numbers: model.value.show_numbers,
  });
  model.value = null;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="model" class="sb-settings-overlay" @click.self="model = null">
      <div class="sb-rating-dialog">
        <div class="sb-settings-header">
          <h2>評分題設定</h2>
          <button class="sb-settings-close" type="button" @click="model = null">✕</button>
        </div>
        <div class="sb-rating-dialog-body">
          <div class="sb-rating-dialog-grid">
            <div>
              <div class="sb-rating-dialog-label">評分級數</div>
              <select class="sb-prop-input" v-model.number="model.count">
                <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
              </select>
            </div>
            <div>
              <div class="sb-rating-dialog-label">圖示樣式</div>
              <div class="sb-rating-shape-list">
                <button
                  v-for="s in RATING_SHAPES" :key="s.value"
                  type="button"
                  class="sb-rating-shape-opt"
                  :class="{ active: model.shape === s.value }"
                  @click="model!.shape = s.value"
                >
                  <span class="sb-rating-shape-icon">{{ s.icon }}</span>
                  <span>{{ s.label }}</span>
                </button>
              </div>
            </div>
          </div>
          <div class="sb-rating-dialog-toggle-row">
            <div>
              <div class="sb-rating-dialog-label">顯示數字</div>
              <p class="sb-rating-dialog-help">開啟後會在每個圖示上方顯示 1、2、3… 的分數。</p>
            </div>
            <button
              type="button"
              class="sb-set-toggle"
              :class="{ on: model.show_numbers }"
              @click="model!.show_numbers = !model!.show_numbers"
            />
          </div>
          <div class="sb-rating-dialog-preview">
            <span v-for="n in model.count" :key="n" class="sb-rating-preview-icon">
              <span v-if="model.show_numbers" class="sb-rating-number">{{ n }}</span>
              <span class="sb-rating-symbol">{{ shapeIcon(model.shape) }}</span>
            </span>
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
