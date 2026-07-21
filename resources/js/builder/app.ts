import { createPinia } from 'pinia';
import { createApp } from 'vue';
import SurveyBuilderApp from './SurveyBuilderApp.vue';
import { useSurveyBuilderStore } from './stores/useSurveyBuilderStore';
import { registerBuilderNavigationProtection } from './registerBuilderNavigationProtection';

const root = document.getElementById('survey-builder-app');

function csrfToken(): string {
  return root?.dataset.csrfToken
    ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    ?? '';
}

type BuilderEndpointPath = 'builder-data' | 'builder-schema' | 'builder-publish' | 'builder-activities' | 'builder-restore-published' | 'builder-image' | 'builder-cascade-template' | 'builder-cascade-import';

function builderEndpoint(path: BuilderEndpointPath): string {
  return window.location.pathname.replace(/\/builder\/?$/, `/${path}`);
}

function normalizeBuilderEndpoint(endpoint: string | undefined, path: BuilderEndpointPath): string {
  const resolvedEndpoint = endpoint || builderEndpoint(path);

  if (path !== 'builder-schema') {
    return resolvedEndpoint;
  }

  return resolvedEndpoint.replace(/\/builder\/?$/, '/builder-schema');
}

function parseJsonRecord(value: string | undefined): Record<string, string> {
  if (!value) {
    return {};
  }

  try {
    const parsed = JSON.parse(value) as unknown;

    if (!parsed || typeof parsed !== 'object' || Array.isArray(parsed)) {
      return {};
    }

    return Object.fromEntries(Object.entries(parsed).map(([key, label]) => [key, String(label)]));
  } catch {
    return {};
  }
}

if (root) {
  const app = createApp(SurveyBuilderApp, {
    endpoints: {
      show: normalizeBuilderEndpoint(root.dataset.endpointShow, 'builder-data'),
      update: normalizeBuilderEndpoint(root.dataset.endpointUpdate, 'builder-schema'),
      publish: normalizeBuilderEndpoint(root.dataset.endpointPublish, 'builder-publish'),
      activities: normalizeBuilderEndpoint(root.dataset.endpointActivities, 'builder-activities'),
      restorePublished: normalizeBuilderEndpoint(root.dataset.endpointRestorePublished, 'builder-restore-published'),
      uploadImage: normalizeBuilderEndpoint(root.dataset.endpointUploadImage, 'builder-image'),
      cascadeTemplate: normalizeBuilderEndpoint(root.dataset.endpointCascadeTemplate, 'builder-cascade-template'),
      cascadeImport: normalizeBuilderEndpoint(root.dataset.endpointCascadeImport, 'builder-cascade-import'),
      googleDriveConnect: root.dataset.endpointGdConnect || undefined,
      googleDriveStatus: root.dataset.endpointGdStatus || undefined,
      googleDriveDisconnect: root.dataset.endpointGdDisconnect || undefined,
    },
    csrfToken: csrfToken(),
    guideUrl: root.dataset.guideUrl || undefined,
    categoryOptions: parseJsonRecord(root.dataset.surveyCategoryOptions),
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
