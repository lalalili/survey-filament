// @vitest-environment jsdom

import { createPinia, setActivePinia } from 'pinia';
import { createSSRApp } from 'vue';
import { renderToString } from '@vue/server-renderer';
import { beforeEach, describe, expect, it } from 'vitest';
import SurveyBuilderApp from '../../resources/js/builder/SurveyBuilderApp.vue';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

const endpoints = {
  show: '/builder',
  update: '/builder',
  publish: '/publish',
  activities: '/activities',
  restorePublished: '/restore',
  uploadImage: '/images',
  cascadeTemplate: '/cascade/template',
  cascadeImport: '/cascade/import',
};

describe('SurveyBuilderApp errors', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('renders the complete save error in a visible alert', async () => {
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();
    store.saveError = '問卷至少需要一個題目頁。';

    const app = createSSRApp(SurveyBuilderApp, {
      endpoints,
      csrfToken: 'token',
    });
    app.use(pinia);

    const html = await renderToString(app);

    expect(html).toContain('role="alert"');
    expect(html).toContain('儲存失敗');
    expect(html).toContain('問卷至少需要一個題目頁。');
  });
});
