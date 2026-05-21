<script setup lang="ts">
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';

export interface NpsDialogState {
  elementId: string;
  low_label: string;
  high_label: string;
  color_bands: boolean;
}

const model = defineModel<NpsDialogState | null>();
const store = useSurveyBuilderStore();

function apply() {
  if (!model.value) return;
  store.updateElementSettings(model.value.elementId, {
    low_label:   model.value.low_label,
    high_label:  model.value.high_label,
    color_bands: model.value.color_bands,
  });
  model.value = null;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="model" class="sb-settings-overlay" @click.self="model = null">
      <div class="sb-nps-dialog">
        <div class="sb-settings-header">
          <h2>NPS 淨推薦值設定</h2>
          <button class="sb-settings-close" type="button" @click="model = null">✕</button>
        </div>
        <div class="sb-nps-dialog-body">
          <div class="sb-nps-dialog-grid">
            <div>
              <div class="sb-rating-dialog-label">左側說明文字</div>
              <input class="sb-prop-input" v-model="model.low_label" placeholder="完全不可能" />
            </div>
            <div>
              <div class="sb-rating-dialog-label">右側說明文字</div>
              <input class="sb-prop-input" v-model="model.high_label" placeholder="非常有可能" />
            </div>
          </div>
          <div class="sb-nps-dialog-color-section">
            <div class="sb-nps-dialog-color-head">
              <div>
                <div class="sb-nps-dialog-color-title">依分數顯示顏色</div>
                <p class="sb-nps-dialog-color-desc">開啟後，0－6 顯示紅色、7－8 顯示黃色、9－10 顯示綠色，方便填答者理解分數區間。</p>
              </div>
              <button
                type="button"
                class="sb-set-toggle"
                :class="{ on: model.color_bands }"
                @click="model!.color_bands = !model!.color_bands"
              />
            </div>
          </div>
          <div class="sb-nps-dialog-preview">
            <div class="sb-nps-preview-row">
              <span
                v-for="n in 11" :key="n"
                class="sb-nps-preview-pip"
                :class="{
                  red:    model.color_bands && n - 1 <= 6,
                  yellow: model.color_bands && n - 1 >= 7 && n - 1 <= 8,
                  green:  model.color_bands && n - 1 >= 9,
                }"
              >{{ n - 1 }}</span>
            </div>
            <div class="sb-nps-preview-labels">
              <span>{{ model.low_label || '完全不可能' }}</span>
              <span>{{ model.high_label || '非常有可能' }}</span>
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
