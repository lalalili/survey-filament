<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';

const model = defineModel<boolean>({ required: true });
const store = useSurveyBuilderStore();
const confirmingRestore = ref(false);

const publishedAtLabel = computed(() => formatDateTime(store.publishedAt));

watch(model, (isOpen) => {
  if (isOpen) {
    confirmingRestore.value = false;
    void store.loadActivities();
  }
});

function closePanel(): void {
  model.value = false;
  confirmingRestore.value = false;
}

async function restorePublished(): Promise<void> {
  await store.restorePublished();

  if (!store.activitiesError) {
    confirmingRestore.value = false;
  }
}

function formatDateTime(value: string | null): string {
  if (!value) {
    return '—';
  }

  return new Intl.DateTimeFormat('zh-TW', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(new Date(value));
}
</script>

<template>
  <Teleport to="body">
    <div v-if="model" class="sb-activity-layer sb-theme sb-auto-dark" role="dialog" aria-modal="true" aria-label="編輯紀錄">
      <button type="button" class="sb-activity-backdrop" aria-label="關閉編輯紀錄" @click="closePanel" />

      <aside class="sb-activity-panel">
        <header class="sb-activity-head">
          <div>
            <p class="sb-activity-kicker">History</p>
            <h2>編輯紀錄</h2>
          </div>
          <button type="button" class="sb-activity-close" aria-label="關閉編輯紀錄" @click="closePanel">×</button>
        </header>

        <section class="sb-activity-restore">
          <div>
            <h3>目前發布版本</h3>
            <p>{{ store.canRestorePublished ? `發布時間 ${publishedAtLabel}` : '尚未發布，無可回復版本。' }}</p>
          </div>
          <button
            v-if="!confirmingRestore"
            type="button"
            class="sb-btn"
            :disabled="!store.canRestorePublished || store.isRestoringPublished"
            @click="confirmingRestore = true"
          >
            回復
          </button>
        </section>

        <div v-if="confirmingRestore" class="sb-activity-confirm">
          <p>回復後會清除目前尚未發布的草稿變更，並回到目前填答者看到的發布版本。</p>
          <div class="sb-activity-confirm-actions">
            <button type="button" class="sb-btn" :disabled="store.isRestoringPublished" @click="confirmingRestore = false">取消</button>
            <button type="button" class="sb-btn accent" :disabled="store.isRestoringPublished" @click="restorePublished">
              {{ store.isRestoringPublished ? '回復中…' : '確認回復' }}
            </button>
          </div>
        </div>

        <p v-if="store.activitiesError" class="sb-activity-error">{{ store.activitiesError }}</p>

        <div v-if="store.isLoadingActivities" class="sb-activity-empty">載入編輯紀錄…</div>

        <ol v-else-if="store.activities.length > 0" class="sb-activity-list">
          <li v-for="activity in store.activities" :key="activity.id" class="sb-activity-item">
            <div class="sb-activity-dot" />
            <div class="sb-activity-content">
              <div class="sb-activity-row">
                <strong>{{ activity.label }}</strong>
                <time>{{ formatDateTime(activity.created_at) }}</time>
              </div>
              <p>{{ activity.description }}</p>
              <div class="sb-activity-meta">
                <span>{{ activity.causer_name ?? '系統' }}</span>
                <span v-if="activity.version">v{{ activity.version }}</span>
                <span v-if="activity.autosave_count && activity.autosave_count > 1">合併 {{ activity.autosave_count }} 次儲存</span>
              </div>
            </div>
          </li>
        </ol>

        <div v-else class="sb-activity-empty">尚無編輯紀錄。</div>
      </aside>
    </div>
  </Teleport>
</template>
