<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { useSurveyBuilderStore } from './stores/useSurveyBuilderStore';
import type { BuilderEndpoints } from './types/schema';
import { BuilderShell } from '@builder-ui-core';
import CanvasArea from './components/CanvasArea.vue';
import BuilderActivityPanel from './dialogs/BuilderActivityPanel.vue';
import SettingsModal from './dialogs/SettingsModal.vue';

const props = defineProps<{
  endpoints: BuilderEndpoints;
  csrfToken: string;
  guideUrl?: string;
  categoryOptions?: Record<string, string>;
}>();

const store = useSurveyBuilderStore();
const showActivityPanel = ref(false);

// Save status
const saveStatus = computed(() => {
  if (store.isPublishing) return 'Publishing...';
  if (store.isSaving) return '儲存中…';
  if (store.hasPendingShowIfDrafts) return '規則設定中';
  if (store.publishError) {
    return store.publishError.length <= 28 ? `發布失敗：${store.publishError}` : '發布失敗';
  }
  if (store.saveError) {
    const count = Object.keys(store.validationErrors).length;
    return count > 0 ? `儲存失敗（${count} 個錯誤）` : '儲存失敗';
  }
  if (store.isDirty) return '未儲存';
  return '已儲存';
});

const statusTooltip = computed(() => store.publishError || store.saveError || saveStatus.value);

function beforeUnload(event: BeforeUnloadEvent) {
  if (!store.hasUnsavedChanges) return;
  event.preventDefault();
  event.returnValue = '';
}

onMounted(async () => {
  store.configure(props.endpoints, props.csrfToken);
  await store.loadBuilder();
  window.addEventListener('beforeunload', beforeUnload);
});
onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload));
</script>

<template>
  <BuilderShell class="sb-auto-dark" :is-preview-mode="store.isPreviewMode">
    <template #topbar>
      <div class="sb-topbar-left">
        <div class="sb-logo-spacer"></div>
        <input
          :value="store.surveyTitle"
          class="sb-title-input"
          @input="store.updateSurveyTitle(($event.target as HTMLInputElement).value)"
        />
      </div>

      <div class="sb-topbar-spacer" />

      <div class="sb-topbar-right">
        <span class="sb-action-tooltip" :data-tooltip="statusTooltip" tabindex="0">
          <span
            class="sb-save-status"
            :class="{ saving: store.isSaving || store.isPublishing, error: !!store.saveError || !!store.publishError }"
          >
            <span class="sb-save-dot" />
            {{ saveStatus }}
          </span>
        </span>

        <span class="sb-action-tooltip" data-tooltip="編輯紀錄" tabindex="0">
          <button
            type="button"
            class="sb-icon-btn"
            :class="{ active: showActivityPanel }"
            aria-label="編輯紀錄"
            @click="showActivityPanel = true"
          >↺</button>
        </span>

        <span v-if="props.guideUrl" class="sb-action-tooltip" data-tooltip="問卷使用說明" tabindex="0">
          <a
            class="sb-icon-btn"
            :href="props.guideUrl"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="問卷使用說明"
          >?</a>
        </span>

        <span v-if="store.isPreviewMode" class="sb-action-tooltip" data-tooltip="切換行動版" tabindex="0">
          <button
            type="button"
            class="sb-icon-btn"
            :class="store.isMobilePreview ? 'active' : ''"
            aria-label="切換行動版"
            @click="store.isMobilePreview = !store.isMobilePreview"
          >📱</button>
        </span>

        <span
          class="sb-action-tooltip"
          :data-tooltip="store.isPreviewMode ? '返回問卷編輯' : '預覽模式下不會真的送出填答'"
          tabindex="0"
        >
          <button
            type="button"
            class="sb-btn"
            :class="store.isPreviewMode ? 'primary' : ''"
            @click="store.togglePreview()"
          >
            {{ store.isPreviewMode ? '返回編輯' : '預覽' }}
          </button>
        </span>
        <span
          class="sb-action-tooltip align-end"
          data-tooltip="發佈後，您編輯的內容才會更新"
          tabindex="0"
        >
          <button
            type="button"
            class="sb-btn accent"
            :disabled="store.isSaving || store.isPublishing || (store.status === 'published' && !store.isDirty && !store.hasUnpublishedChanges && !store.hasPendingShowIfDrafts)"
            @click="store.publish()"
          >
            {{ (store.status === 'published' && !store.isDirty && !store.hasUnpublishedChanges && !store.hasPendingShowIfDrafts) ? '已發佈' : '發佈問卷' }}
          </button>
        </span>
      </div>
    </template>

    <CanvasArea :endpoints="props.endpoints" :csrf-token="props.csrfToken" />

    <SettingsModal
      v-model="store.showSettingsModal"
      :upload-image-url="props.endpoints.uploadImage"
      :csrf-token="props.csrfToken"
      :category-options="props.categoryOptions ?? {}"
    />

    <BuilderActivityPanel v-model="showActivityPanel" />
  </BuilderShell>
</template>

<style>
@import './styles/builder.css';
</style>
