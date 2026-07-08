<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import type { AudienceListColumn, SurveySettings } from '../types/schema';
import { useSurveyBuilderStore } from '../stores/useSurveyBuilderStore';
import SurveyRichEditor from '../components/SurveyRichEditor.vue';
import { calculationVariableToken } from '../utils/variableTokens';

// 伺服器是否已設定 Turnstile 金鑰（由 app.ts provide）。未設定時停用「我不是機器人」開關。
const turnstileConfigured = inject<boolean>('turnstileConfigured', false);
const languageSettingEnabled = inject<boolean>('languageSettingEnabled', false);
const thankYouRedirectEnabled = inject<boolean>('thankYouRedirectEnabled', false);
const accentColorSettingEnabled = inject<boolean>('accentColorSettingEnabled', false);

function toggleTurnstile() {
  // 僅在伺服器已設定金鑰時允許開啟；未設定時僅允許關閉（避免開了卻無金鑰導致全部送出被擋）。
  const next = !store.schema?.settings?.anomaly?.turnstile;
  if (next && !turnstileConfigured) {
    return;
  }
  store.updateAnomalySettings({ turnstile: next });
}

const props = defineProps<{
  uploadImageUrl: string;
  csrfToken: string;
  categoryOptions: Record<string, string>;
}>();

const show = defineModel<boolean>({ default: false });
const store = useSurveyBuilderStore();
const categoryOptionEntries = computed(() => Object.entries(props.categoryOptions));
const thankYouVariableTokens = computed(() => (store.schema?.calculations ?? []).map(calculationVariableToken));

type SettingsTab = 'basic' | 'welcome' | 'thank_you' | 'display' | 'result' | 'access' | 'personalization' | 'anomaly';
const settingsTab = ref<SettingsTab>('basic');

const selectedAudienceList = computed(() => {
  const audienceListId = store.schema?.settings?.personalization?.audience_list_id;
  if (!audienceListId) return null;
  return store.audienceLists.find((list) => String(list.id) === String(audienceListId)) ?? null;
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

function updatePersonalizationSettings(patch: Partial<NonNullable<SurveySettings['personalization']>>) {
  store.updateSurveySettings({
    personalization: {
      ...(store.schema?.settings?.personalization ?? {}),
      ...(patch ?? {}),
    },
  });
}

function updatePersonalizationAudience(audienceListId: string) {
  updatePersonalizationSettings({
    audience_list_id: audienceListId || null,
    required: audienceListId !== '',
    field_mappings: {},
  });
}
</script>

<template>
  <Teleport to="body">
    <div v-if="show" class="sb-settings-overlay" @click.self="show = false">
      <div class="sb-settings-modal">
        <div class="sb-settings-header">
          <h2>問卷設定</h2>
          <button class="sb-settings-close" type="button" @click="show = false">✕</button>
        </div>
        <div class="sb-settings-body">
          <!-- Left nav -->
          <nav class="sb-settings-nav">
            <button
              v-for="tab in [
                { k: 'basic',           l: '基本資訊' },
                { k: 'welcome',         l: '歡迎頁' },
                { k: 'thank_you',       l: '感謝頁' },
                { k: 'display',         l: '問卷顯示' },
                { k: 'result',          l: '問卷結果' },
                { k: 'access',          l: '問卷存取' },
                { k: 'personalization', l: '個性化名單' },
                { k: 'anomaly',         l: '異常填答偵測' },
              ]"
              :key="tab.k"
              class="sb-settings-nav-item"
              :class="{ active: settingsTab === tab.k }"
              type="button"
              @click="settingsTab = (tab.k as SettingsTab)"
            >{{ tab.l }}</button>
          </nav>

          <!-- Content -->
          <div class="sb-settings-content">

            <!-- ── 基本資訊 ── -->
            <template v-if="settingsTab === 'basic'">
              <div class="sb-set-card">
                <div class="sb-set-field full">
                  <div class="sb-set-field-label">問卷標題</div>
                  <input
                    class="sb-prop-input"
                    type="text"
                    placeholder="未命名問卷"
                    :value="store.surveyTitle"
                    @input="store.updateSurveyTitle(($event.target as HTMLInputElement).value)"
                  />
                </div>
                <div class="sb-set-field full">
                  <div class="sb-set-field-label">問卷描述</div>
                  <textarea
                    class="sb-prop-input"
                    rows="3"
                    placeholder="可選填，說明問卷目的或填寫注意事項"
                    :value="store.schema?.settings?.description ?? ''"
                    @input="store.updateSurveySettings({ description: ($event.target as HTMLTextAreaElement).value || null })"
                  ></textarea>
                  <div class="sb-set-hint" style="margin-top:4px">顯示於填答頁標題下方，適合補充問卷目的或填寫說明</div>
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">分類</div>
                  <select
                    v-if="categoryOptionEntries.length > 0"
                    class="sb-prop-input"
                    style="max-width:260px"
                    :value="store.schema?.settings?.category ?? ''"
                    @change="store.updateSurveySettings({ category: ($event.target as HTMLSelectElement).value || null })"
                  >
                    <option value="">未分類</option>
                    <option v-for="[value, label] in categoryOptionEntries" :key="value" :value="value">{{ label }}</option>
                  </select>
                  <input
                    v-else
                    class="sb-prop-input"
                    type="text"
                    maxlength="10"
                    placeholder="例如 CSI、SSI"
                    :value="store.schema?.settings?.category ?? ''"
                    @input="store.updateSurveySettings({ category: ($event.target as HTMLInputElement).value || null })"
                    style="max-width:160px"
                  />
                </div>
              </div>
            </template>

            <!-- ── 歡迎頁 ── -->
            <template v-if="settingsTab === 'welcome'">
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">啟用歡迎頁</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.welcomePage?.welcome_settings?.enabled !== false }"
                    type="button"
                    @click="store.welcomePage
                      ? store.updatePage(store.welcomePage.id, { welcome_settings: { ...(store.welcomePage.welcome_settings ?? {}), enabled: !(store.welcomePage.welcome_settings?.enabled !== false) } })
                      : store.addSpecialPage('welcome')"
                  ></button>
                </div>
                <template v-if="store.welcomePage && store.welcomePage.welcome_settings?.enabled !== false">
                  <div class="sb-set-field">
                    <div class="sb-set-field-label">開始按鈕文字</div>
                    <input
                      class="sb-prop-input"
                      type="text"
                      placeholder="開始填寫"
                      :value="store.welcomePage.welcome_settings?.cta_label ?? '開始填寫'"
                      @input="store.updatePage(store.welcomePage!.id, { welcome_settings: { ...(store.welcomePage!.welcome_settings ?? {}), cta_label: ($event.target as HTMLInputElement).value } })"
                      style="max-width:180px"
                    />
                  </div>
                  <div class="sb-set-field">
                    <div class="sb-set-field-label">預計填寫時間（分鐘）</div>
                    <div style="display:flex;align-items:center;gap:8px">
                      <button
                        class="sb-set-toggle"
                        :class="{ on: store.schema?.settings?.progress?.show_estimated_time !== false }"
                        type="button"
                        title="是否在歡迎頁顯示這段預計填寫時間文字"
                        @click="store.updateProgressSettings(store.schema?.settings?.progress?.mode ?? 'bar', !(store.schema?.settings?.progress?.show_estimated_time !== false))"
                      ></button>
                      <input
                        class="sb-prop-input"
                        type="number"
                        min="0"
                        max="999"
                        :value="store.welcomePage.welcome_settings?.estimated_time_minutes ?? 5"
                        @input="store.updatePage(store.welcomePage!.id, { welcome_settings: { ...(store.welcomePage!.welcome_settings ?? {}), estimated_time_minutes: Number(($event.target as HTMLInputElement).value) || 0 } })"
                        style="max-width:100px"
                      />
                    </div>
                  </div>
                  <div class="sb-set-field full">
                    <SurveyRichEditor
                      :model-value="store.welcomePage.welcome_settings?.content ?? ''"
                      placeholder="在此輸入歡迎頁說明文字…"
                      :upload-url="props.uploadImageUrl"
                      :csrf-token="props.csrfToken"
                      @update:model-value="store.updatePage(store.welcomePage!.id, { welcome_settings: { ...(store.welcomePage!.welcome_settings ?? {}), content: $event || null } })"
                    />
                  </div>
                </template>
                <div v-if="!store.welcomePage" class="sb-set-hint">尚未新增歡迎頁。啟用後將自動建立。</div>
              </div>
            </template>

            <!-- ── 感謝頁 ── -->
            <template v-if="settingsTab === 'thank_you'">
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">啟用感謝頁</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.thankYouPage != null && store.thankYouPage.thank_you_settings?.enabled !== false }"
                    type="button"
                    @click="store.thankYouPage
                      ? store.updatePage(store.thankYouPage.id, { thank_you_settings: { ...(store.thankYouPage.thank_you_settings ?? {}), enabled: !(store.thankYouPage.thank_you_settings?.enabled !== false) } })
                      : store.addSpecialPage('thank_you')"
                  ></button>
                </div>
                <template v-if="store.thankYouPage && store.thankYouPage.thank_you_settings?.enabled !== false">
                  <div class="sb-set-field full">
                    <div class="sb-set-field-label sb-set-hint-inline">感謝文字支援富文字，可插入 <code v-pre>{{response_number}}</code> 顯示填答追蹤編號；需先在「問卷結果」啟用。</div>
                    <SurveyRichEditor
                      :model-value="store.thankYouPage.thank_you_settings?.message ?? ''"
                      placeholder="感謝您的填寫！"
                      :upload-url="props.uploadImageUrl"
                      :csrf-token="props.csrfToken"
                      :variable-tokens="thankYouVariableTokens"
                      @update:model-value="store.updatePage(store.thankYouPage!.id, { thank_you_settings: { ...(store.thankYouPage!.thank_you_settings ?? {}), message: $event || null } })"
                    />
                  </div>
                </template>
                <div v-if="!store.thankYouPage" class="sb-set-hint">尚未新增感謝頁。啟用後將自動建立。</div>

                <template v-if="thankYouRedirectEnabled">
                  <div class="sb-set-field full">
                    <div class="sb-set-field-label">送出後轉址 URL（留空則不轉址）</div>
                    <input
                      class="sb-prop-input"
                      type="url"
                      placeholder="https://example.com"
                      :value="store.schema?.settings?.redirect?.url ?? ''"
                      @input="store.updateSurveySettings({ redirect: { ...(store.schema?.settings?.redirect ?? {}), url: ($event.target as HTMLInputElement).value || null } })"
                    />
                    <div class="sb-set-hint">僅允許 http(s) 網址；填答送出後依下列方式引導使用者前往。</div>
                  </div>
                  <template v-if="store.schema?.settings?.redirect?.url">
                    <div class="sb-set-field full">
                      <div class="sb-set-field-label">轉址方式</div>
                      <select
                        class="sb-prop-input"
                        style="max-width:200px"
                        :value="store.schema?.settings?.redirect?.mode ?? 'link'"
                        @change="store.updateSurveySettings({ redirect: { ...(store.schema?.settings?.redirect ?? {}), mode: ($event.target as HTMLSelectElement).value } })"
                      >
                        <option value="link">顯示連結（使用者自行點選）</option>
                        <option value="auto">自動跳轉（倒數後前往）</option>
                      </select>
                    </div>
                    <div v-if="(store.schema?.settings?.redirect?.mode ?? 'link') === 'auto'" class="sb-set-field full">
                      <div class="sb-set-field-label">自動跳轉倒數秒數（0–30）</div>
                      <input
                        class="sb-prop-input"
                        type="number"
                        min="0"
                        max="30"
                        style="max-width:120px"
                        :value="store.schema?.settings?.redirect?.delay_seconds ?? 5"
                        @input="store.updateSurveySettings({ redirect: { ...(store.schema?.settings?.redirect ?? {}), delay_seconds: Math.max(0, Math.min(30, Number(($event.target as HTMLInputElement).value) || 0)) } })"
                      />
                    </div>
                  </template>
                </template>
              </div>
            </template>

            <!-- ── 問卷顯示 ── -->
            <template v-if="settingsTab === 'display'">
              <div class="sb-set-card">
                <div v-if="languageSettingEnabled" class="sb-set-field">
                  <div class="sb-set-field-label">問卷語言</div>
                  <select
                    class="sb-prop-input"
                    style="max-width:160px"
                    :value="store.schema?.settings?.language ?? 'zh-TW'"
                    @change="store.updateSurveySettings({ language: ($event.target as HTMLSelectElement).value as 'zh-TW'|'zh-CN'|'en' })"
                  >
                    <option value="zh-TW">繁體中文</option>
                    <option value="zh-CN">简体中文</option>
                    <option value="en">English</option>
                  </select>
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">題號顯示</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.schema?.settings?.show_question_numbers !== false }"
                    type="button"
                    @click="store.updateSurveySettings({ show_question_numbers: !(store.schema?.settings?.show_question_numbers !== false) })"
                  ></button>
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">往前翻頁按鈕</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.schema?.settings?.allow_back !== false }"
                    type="button"
                    @click="store.updateSurveySettings({ allow_back: !(store.schema?.settings?.allow_back !== false) })"
                  ></button>
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">填答進度條</div>
                  <select
                    class="sb-prop-input"
                    style="max-width:160px"
                    :value="store.schema?.settings?.progress?.mode ?? 'bar'"
                    @change="store.updateProgressSettings(($event.target as HTMLSelectElement).value as 'none'|'bar'|'steps'|'percent')"
                  >
                    <option value="none">不顯示</option>
                    <option value="bar">進度條</option>
                    <option value="steps">步驟數（第 X / Y 頁）</option>
                    <option value="percent">百分比</option>
                  </select>
                </div>
              </div>
              <div class="sb-set-section-title">外觀主題</div>
              <div class="sb-set-card">
                <div v-if="store.themes.length > 0" class="sb-set-field">
                  <div class="sb-set-field-label">系統主題</div>
                  <select
                    class="sb-prop-input"
                    style="max-width:160px"
                    :value="store.schema?.theme_id ?? ''"
                    @change="store.updateTheme(($event.target as HTMLSelectElement).value ? Number(($event.target as HTMLSelectElement).value) : null)"
                  >
                    <option value="">預設</option>
                    <option v-for="theme in store.themes" :key="theme.id" :value="theme.id">{{ theme.name }}</option>
                  </select>
                </div>
                <div class="sb-set-field sb-set-color-row">
                  <div class="sb-set-field-label">主要操作色</div>
                  <input type="color" class="sb-set-color-input" :value="store.schema?.theme_overrides?.primary ?? '#6366f1'" @input="store.updateThemeOverride('primary', ($event.target as HTMLInputElement).value)" />
                  <div class="sb-set-color-desc">套用於公開問卷的主要按鈕、選取狀態、滑桿與核取方塊。</div>
                </div>
                <div v-if="accentColorSettingEnabled" class="sb-set-field sb-set-color-row">
                  <div class="sb-set-field-label">輔助強調色</div>
                  <input type="color" class="sb-set-color-input" :value="store.schema?.theme_overrides?.accent ?? '#f59e0b'" @input="store.updateThemeOverride('accent', ($event.target as HTMLInputElement).value)" />
                  <div class="sb-set-color-desc">套用於公開問卷的次要動作（上一頁按鈕、感謝頁的繼續連結），與主要操作色區隔。</div>
                </div>
              </div>
              <div class="sb-set-section-title">使用條款與聲明</div>
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">啟用使用條款勾選框</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: !!(store.schema?.settings?.terms_text) }"
                    type="button"
                    @click="store.updateSurveySettings({ terms_text: store.schema?.settings?.terms_text ? null : '我已閱讀並同意本問卷的隱私政策。' })"
                  ></button>
                </div>
                <div v-if="store.schema?.settings?.terms_text" class="sb-set-field full">
                  <div class="sb-set-field-label">條款文字（填答者須勾選後才能送出）</div>
                  <textarea
                    class="sb-prop-input"
                    rows="3"
                    :value="store.schema.settings.terms_text"
                    @input="store.updateSurveySettings({ terms_text: ($event.target as HTMLTextAreaElement).value || null })"
                  ></textarea>
                </div>
              </div>
            </template>

            <!-- ── 問卷結果 ── -->
            <template v-if="settingsTab === 'result'">
              <div class="sb-set-card">
                <div class="sb-set-field full">
                  <div class="sb-set-field-label">提交成功訊息</div>
                  <textarea
                    class="sb-prop-input"
                    rows="2"
                    placeholder="未填時顯示預設感謝訊息"
                    :value="store.schema?.settings?.submit_success_message ?? ''"
                    @input="store.updateSurveySettings({ submit_success_message: ($event.target as HTMLTextAreaElement).value || null })"
                  ></textarea>
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">
                    自動產生填答追蹤編號
                    <span class="sb-set-hint-inline">格式：SR-YYYYMMDD-XXXXXX</span>
                  </div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.schema?.settings?.response_number }"
                    type="button"
                    @click="store.updateSurveySettings({ response_number: !store.schema?.settings?.response_number })"
                  ></button>
                </div>
                <div class="sb-set-hint">
                  開啟後，每筆完成填答會取得唯一編號，可顯示於感謝頁，並供後台搜尋、客服追蹤與資料匯出使用。
                </div>
                <div v-if="store.schema?.settings?.response_number" class="sb-set-hint">
                  在感謝頁文字插入 <code v-pre>{{response_number}}</code>，送出成功後會自動替換為該筆填答編號。
                </div>
              </div>
              <div class="sb-set-section-title">回應通知</div>
              <div class="sb-set-card">
                <div class="sb-set-field full">
                  <div class="sb-set-field-label">新回應通知 Email</div>
                  <input
                    class="sb-prop-input"
                    type="text"
                    placeholder="多組請以半形逗號分隔，如：a@example.com, b@example.com"
                    :value="store.schema?.settings?.notify_emails ?? ''"
                    @input="store.updateSurveySettings({ notify_emails: ($event.target as HTMLInputElement).value || null })"
                  />
                  <div class="sb-set-hint" style="margin-top:4px">每筆新回應送出後將寄送通知至以上信箱</div>
                </div>
              </div>
            </template>

            <!-- ── 問卷存取 ── -->
            <template v-if="settingsTab === 'access'">
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">開始時間</div>
                  <input
                    class="sb-prop-input"
                    type="datetime-local"
                    :value="store.schema?.settings?.starts_at ?? ''"
                    @input="store.updateSurveySettings({ starts_at: ($event.target as HTMLInputElement).value || null })"
                    style="max-width:220px"
                  />
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">結束時間</div>
                  <input
                    class="sb-prop-input"
                    type="datetime-local"
                    :value="store.schema?.settings?.ends_at ?? ''"
                    @input="store.updateSurveySettings({ ends_at: ($event.target as HTMLInputElement).value || null })"
                    style="max-width:220px"
                  />
                </div>
                <div class="sb-set-field">
                  <div class="sb-set-field-label">回收數量上限</div>
                  <div style="display:flex;align-items:center;gap:8px">
                    <button
                      class="sb-set-toggle"
                      :class="{ on: store.schema?.settings?.max_responses != null }"
                      type="button"
                      @click="store.updateSurveySettings({ max_responses: store.schema?.settings?.max_responses != null ? null : 100 })"
                    ></button>
                    <input
                      v-if="store.schema?.settings?.max_responses != null"
                      class="sb-prop-input"
                      type="number"
                      min="1"
                      :value="store.schema.settings.max_responses"
                      @input="store.updateSurveySettings({ max_responses: Number(($event.target as HTMLInputElement).value) || null })"
                      style="max-width:100px"
                    />
                    <span v-if="store.schema?.settings?.max_responses != null" class="sb-set-unit">份</span>
                  </div>
                </div>
                <div class="sb-set-field full">
                  <div class="sb-set-field-label">額滿訊息</div>
                  <textarea
                    class="sb-prop-input"
                    rows="2"
                    placeholder="未填時使用預設額滿提示"
                    :value="store.schema?.settings?.quota_message ?? ''"
                    @input="store.updateSurveySettings({ quota_message: ($event.target as HTMLTextAreaElement).value || null })"
                  ></textarea>
                </div>
              </div>
              <div class="sb-set-section-title">防重複填寫</div>
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">防重填模式</div>
                  <select
                    class="sb-prop-input"
                    style="max-width:200px"
                    :value="store.schema?.settings?.uniqueness_mode ?? 'none'"
                    @change="store.updateSurveySettings({ uniqueness_mode: ($event.target as HTMLSelectElement).value as 'none'|'email'|'token'|'ip'|'cookie' })"
                  >
                    <option value="none">不限制</option>
                    <option value="cookie">Cookie（同瀏覽器）</option>
                    <option value="ip">IP 位址</option>
                    <option value="token">個性化 Token</option>
                    <option value="email">Email</option>
                  </select>
                </div>
                <div class="sb-set-field full">
                  <div class="sb-set-field-label">重複填寫提示</div>
                  <input
                    class="sb-prop-input"
                    type="text"
                    placeholder="未填時使用預設重複填寫提示"
                    :value="store.schema?.settings?.uniqueness_message ?? ''"
                    @input="store.updateSurveySettings({ uniqueness_message: ($event.target as HTMLInputElement).value || null })"
                  />
                </div>
              </div>
              <div class="sb-set-section-title">私密問卷</div>
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">設定存取密碼</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.schema?.settings?.password != null }"
                    type="button"
                    @click="store.updateSurveySettings({ password: store.schema?.settings?.password != null ? null : '' })"
                  ></button>
                </div>
                <template v-if="store.schema?.settings?.password != null">
                  <div class="sb-set-field">
                    <div class="sb-set-field-label">密碼</div>
                    <input
                      class="sb-prop-input"
                      type="text"
                      placeholder="請輸入密碼"
                      :value="store.schema.settings.password"
                      @input="store.updateSurveySettings({ password: ($event.target as HTMLInputElement).value })"
                      style="max-width:200px"
                    />
                  </div>
                  <div class="sb-set-hint">填答者開啟問卷時需輸入此密碼才能填寫</div>
                </template>
              </div>
            </template>

            <!-- ── 個性化名單 ── -->
            <template v-if="settingsTab === 'personalization'">
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">個性化名單</div>
                  <select
                    class="sb-prop-input"
                    style="max-width:260px"
                    :value="store.schema?.settings?.personalization?.audience_list_id ?? ''"
                    @change="updatePersonalizationAudience(($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">不使用名單</option>
                    <option v-for="list in store.audienceLists" :key="list.id" :value="list.id">{{ list.name }}</option>
                  </select>
                </div>
                <div class="sb-set-hint">選擇個性化名單後，填答者必須使用個性化網址；未選名單時使用一般正式網址。</div>
                <div v-if="selectedAudienceList" class="sb-set-hint" style="margin-bottom:4px">
                  欄位對應：在左側畫布選取隱藏欄位，於右側「個性化」屬性面板選擇對應的名單欄位。
                </div>
                <template v-if="selectedAudienceList">
                  <div class="sb-set-field">
                    <div class="sb-set-field-label">姓名欄位</div>
                    <select
                      class="sb-prop-input"
                      style="max-width:220px"
                      :value="store.schema?.settings?.personalization?.name_column ?? ''"
                      @change="updatePersonalizationSettings({ name_column: ($event.target as HTMLSelectElement).value || null })"
                    >
                      <option value="">未指定</option>
                      <option v-for="column in audienceColumnOptions" :key="column.value" :value="column.value">{{ column.label }}</option>
                    </select>
                    <div class="sb-set-hint" style="margin-top:4px">同步名單時寫入收件人姓名，方便後台辨識、匯出與後續訊息個人化。</div>
                  </div>
                  <div class="sb-set-field">
                    <div class="sb-set-field-label">Email 欄位</div>
                    <select
                      class="sb-prop-input"
                      style="max-width:220px"
                      :value="store.schema?.settings?.personalization?.email_column ?? ''"
                      @change="updatePersonalizationSettings({ email_column: ($event.target as HTMLSelectElement).value || null })"
                    >
                      <option value="">未指定</option>
                      <option v-for="column in audienceColumnOptions" :key="column.value" :value="column.value">{{ column.label }}</option>
                    </select>
                    <div class="sb-set-hint" style="margin-top:4px">同步為收件人 Email，EDM 活動選擇此問卷時可沿用此欄位作為收件地址來源。</div>
                  </div>
                  <div class="sb-set-field">
                    <div class="sb-set-field-label">外部 ID 欄位</div>
                    <select
                      class="sb-prop-input"
                      style="max-width:220px"
                      :value="store.schema?.settings?.personalization?.external_id_column ?? ''"
                      @change="updatePersonalizationSettings({ external_id_column: ($event.target as HTMLSelectElement).value || null })"
                    >
                      <option value="">未指定</option>
                      <option v-for="column in audienceColumnOptions" :key="column.value" :value="column.value">{{ column.label }}</option>
                    </select>
                    <div class="sb-set-hint" style="margin-top:4px">同步 CRM、DMS 或會員系統 ID，便於對帳、去重與跨系統追蹤；未指定時使用名單資料列 ID。</div>
                  </div>
                </template>
              </div>
            </template>

            <!-- ── 異常填答偵測 ── -->
            <template v-if="settingsTab === 'anomaly'">
              <div class="sb-set-hint" style="margin-bottom:12px">偵測到異常時不擋填答，僅在回應資料中標記，分析時可篩選排除。</div>

              <div class="sb-set-card">
                <!-- 最短填答時間 -->
                <div class="sb-set-field">
                  <div class="sb-set-field-label">最短填答時間偵測</div>
                  <div style="display:flex;align-items:center;gap:8px">
                    <button
                      class="sb-set-toggle"
                      :class="{ on: store.schema?.settings?.anomaly?.min_seconds != null }"
                      type="button"
                      @click="store.updateAnomalySettings({ min_seconds: store.schema?.settings?.anomaly?.min_seconds != null ? null : 30 })"
                    ></button>
                    <template v-if="store.schema?.settings?.anomaly?.min_seconds != null">
                      <input
                        class="sb-prop-input"
                        type="number"
                        min="5"
                        max="3600"
                        :value="store.schema.settings.anomaly.min_seconds"
                        @input="store.updateAnomalySettings({ min_seconds: Number(($event.target as HTMLInputElement).value) || null })"
                        style="max-width:90px"
                      />
                      <span class="sb-set-unit">秒內送出視為異常</span>
                    </template>
                  </div>
                </div>

                <!-- 重複填答 -->
                <div class="sb-set-field">
                  <div class="sb-set-field-label">偵測重複填答</div>
                  <select
                    class="sb-prop-input"
                    style="max-width:200px"
                    :value="store.schema?.settings?.anomaly?.detect_duplicate ?? 'none'"
                    @change="store.updateAnomalySettings({ detect_duplicate: ($event.target as HTMLSelectElement).value as 'none'|'cookie'|'ip'|'both' })"
                  >
                    <option value="none">不偵測</option>
                    <option value="cookie">Cookie（同瀏覽器）</option>
                    <option value="ip">IP 位址（跨設備）</option>
                    <option value="both">Cookie + IP（雙重）</option>
                  </select>
                </div>
                <div v-if="(store.schema?.settings?.anomaly?.detect_duplicate ?? 'none') !== 'none'" class="sb-set-hint">
                  重複填答不會被阻擋，僅在回應中標記 <code>duplicate: true</code>
                </div>
              </div>

              <div class="sb-set-section-title">人機驗證</div>
              <div class="sb-set-card">
                <div class="sb-set-field">
                  <div class="sb-set-field-label">我不是機器人</div>
                  <button
                    class="sb-set-toggle"
                    :class="{ on: store.schema?.settings?.anomaly?.turnstile, disabled: !turnstileConfigured && !store.schema?.settings?.anomaly?.turnstile }"
                    :disabled="!turnstileConfigured && !store.schema?.settings?.anomaly?.turnstile"
                    :title="!turnstileConfigured ? '伺服器尚未設定 Turnstile 金鑰，無法啟用' : ''"
                    type="button"
                    @click="toggleTurnstile"
                  ></button>
                </div>
                <div v-if="!turnstileConfigured" class="sb-set-hint" style="color:#b45309">
                  ⚠️ 伺服器尚未設定 Turnstile 金鑰（<code>TURNSTILE_SECRET_KEY</code>），無法啟用人機驗證。請先請系統管理員設定後再開啟。
                </div>
                <div v-else-if="store.schema?.settings?.anomaly?.turnstile" class="sb-set-hint">
                  啟用 Cloudflare Turnstile 驗證，防止機器人灌票與異常填寫。
                </div>
              </div>
            </template>

          </div><!-- /sb-settings-content -->
        </div><!-- /sb-settings-body -->
        <div class="sb-settings-footer">
          <button class="sb-btn" type="button" @click="show = false">關閉</button>
        </div>
      </div><!-- /sb-settings-modal -->
    </div><!-- /sb-settings-overlay -->
  </Teleport>
</template>
