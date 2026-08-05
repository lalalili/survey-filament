import { createPinia } from 'pinia';
import { createApp } from 'vue';
import SurveyBuilderApp from './SurveyBuilderApp.vue';
import { useSurveyBuilderStore } from './stores/useSurveyBuilderStore';
import { readBuilderEndpoints } from './utils/builderEndpoints';
import { registerBuilderNavigationProtection } from './registerBuilderNavigationProtection';

const root = document.getElementById('survey-builder-app');

function csrfToken(): string {
  return root?.dataset.csrfToken
    ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    ?? '';
}

if (root) {
  const app = createApp(SurveyBuilderApp, {
    endpoints: readBuilderEndpoints(root.dataset),
    csrfToken: csrfToken(),
    guideUrl: root.dataset.guideUrl || undefined,
  });

  // 伺服器是否已設定 Turnstile 金鑰；未設定時建立器停用「我不是機器人」開關。
  app.provide('turnstileConfigured', root.dataset.turnstileConfigured === '1');
  app.provide('languageSettingEnabled', root.dataset.languageSettingEnabled === '1');
  app.provide('thankYouRedirectEnabled', root.dataset.thankYouRedirectEnabled === '1');
  app.provide('accentColorSettingEnabled', root.dataset.accentColorSettingEnabled === '1');

  const pinia = createPinia();

  app.use(pinia);
  app.mount(root);
  registerBuilderNavigationProtection({
    app,
    hasUnsavedChanges: () => useSurveyBuilderStore(pinia).hasUnsavedChanges,
    confirmLeave: () => window.confirm('系統可能不會儲存你所做的變更。確定要離開嗎？'),
    navigate: (url) => window.Livewire.navigate(url),
  });
}
