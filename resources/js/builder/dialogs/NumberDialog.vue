<script setup lang="ts">
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';

export interface NumberDialogState {
  elementId: string;
  label: string;
  min: string;
  max: string;
  decimal_places: string;
  unit: string;
}

const model = defineModel<NumberDialogState | null>();
const store = useSurveyBuilderStore();

function apply() {
  if (!model.value) return;
  const patch: Record<string, unknown> = {
    min:            model.value.min !== '' ? Number(model.value.min) : null,
    max:            model.value.max !== '' ? Number(model.value.max) : null,
    decimal_places: model.value.decimal_places !== '' ? Number(model.value.decimal_places) : null,
    unit:           model.value.unit,
  };
  store.updateElementSettings(model.value.elementId, patch);
  model.value = null;
}
</script>

<template>
  <Teleport to="body">
    <div v-if="model" class="sb-settings-overlay" @click.self="model = null">
      <div class="sb-number-dialog">
        <div class="sb-settings-header">
          <h2>數字範圍設定</h2>
          <button class="sb-settings-close" type="button" @click="model = null">✕</button>
        </div>
        <div class="sb-number-dialog-body">
          <p class="sb-number-dialog-desc">
            您可以設定<strong class="sb-number-dialog-name">{{ model.label }}</strong>可填寫的數字範圍、小數位數與顯示單位。
            <span class="sb-number-dialog-hint">若不設定，請保持空白即可。</span>
          </p>
          <div class="sb-number-dialog-grid">
            <div class="sb-set-field-label">最小值</div>
            <div class="sb-set-field-label">最大值</div>
            <input class="sb-prop-input" type="number" v-model="model.min" placeholder="" />
            <input class="sb-prop-input" type="number" v-model="model.max" placeholder="" />
            <div class="sb-set-field-label">小數點後幾位</div>
            <div class="sb-set-field-label">單位（例：吋、元、日、歲）</div>
            <select class="sb-prop-input" v-model="model.decimal_places">
              <option value="">—</option>
              <option value="0">0（整數）</option>
              <option value="1">1</option>
              <option value="2">2</option>
              <option value="3">3</option>
              <option value="4">4</option>
            </select>
            <input class="sb-prop-input" type="text" v-model="model.unit" placeholder="" />
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
