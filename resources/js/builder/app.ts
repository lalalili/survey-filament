import { createPinia } from 'pinia';
import { createApp } from 'vue';
import SurveyBuilderApp from './SurveyBuilderApp.vue';

const root = document.getElementById('survey-builder-app');

function csrfToken(): string {
  return root?.dataset.csrfToken
    ?? document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
    ?? '';
}

function builderEndpoint(path: 'builder-data' | 'builder-schema' | 'builder-publish' | 'builder-image'): string {
  return window.location.pathname.replace(/\/builder\/?$/, `/${path}`);
}

function normalizeBuilderEndpoint(endpoint: string | undefined, path: 'builder-data' | 'builder-schema' | 'builder-publish' | 'builder-image'): string {
  const resolvedEndpoint = endpoint || builderEndpoint(path);

  if (path !== 'builder-schema') {
    return resolvedEndpoint;
  }

  return resolvedEndpoint.replace(/\/builder\/?$/, '/builder-schema');
}

if (root) {
  const app = createApp(SurveyBuilderApp, {
    endpoints: {
      show: normalizeBuilderEndpoint(root.dataset.endpointShow, 'builder-data'),
      update: normalizeBuilderEndpoint(root.dataset.endpointUpdate, 'builder-schema'),
      publish: normalizeBuilderEndpoint(root.dataset.endpointPublish, 'builder-publish'),
      uploadImage: normalizeBuilderEndpoint(root.dataset.endpointUploadImage, 'builder-image'),
    },
    csrfToken: csrfToken(),
  });

  app.use(createPinia());
  app.mount(root);
}
