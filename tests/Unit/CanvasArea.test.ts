// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CanvasArea from '../../resources/js/builder/components/CanvasArea.vue';
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

describe('CanvasArea select options editor', () => {
  afterEach(() => vi.useRealTimers());

  it('keeps the disabled select preview while allowing options to be renamed, added, and removed', async () => {
    vi.useFakeTimers();
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();
    store.schema = {
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [{
          id: 'question-1',
          type: 'select',
          field_key: 'question_1',
          label: '請選擇方案',
          description: '',
          required: false,
          options: [
            { id: 'option-1', label: '方案 A', value: 'plan_a' },
            { id: 'option-2', label: '方案 B', value: 'plan_b' },
          ],
          settings: {},
        }],
      }],
    } as typeof store.schema;
    store.selectedPageId = 'page-1';

    const wrapper = mount(CanvasArea, {
      props: { endpoints, csrfToken: 'token' },
      global: {
        plugins: [pinia],
        stubs: { RightPanel: true },
      },
    });

    const card = wrapper.get('.sb-card');
    const preview = card.get('select.survey-select');
    expect(preview.attributes('disabled')).toBeDefined();
    expect(preview.text()).toContain('方案 A');
    expect(preview.text()).toContain('方案 B');

    const optionInputs = card.findAll('input.sb-opt-input');
    expect(optionInputs).toHaveLength(2);
    await optionInputs[0].setValue('進階方案');
    expect(store.schema.pages[0].elements[0].options[0].label).toBe('進階方案');
    expect(store.isDirty).toBe(true);

    store.isDirty = false;
    await card.get('button.sb-opt-add').trigger('click');
    expect(store.schema.pages[0].elements[0].options).toHaveLength(3);
    expect(store.isDirty).toBe(true);

    store.isDirty = false;
    await card.findAll('button.sb-opt-act')[2].trigger('click');
    expect(store.schema.pages[0].elements[0].options).toHaveLength(2);
    expect(store.isDirty).toBe(true);
  });
});
