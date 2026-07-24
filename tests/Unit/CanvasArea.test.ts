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

describe('CanvasArea NPS preview', () => {
  it('renders all 11 scores in one response group and supports selecting zero and ten', async () => {
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();
    store.schema = {
      title: 'NPS 問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '推薦意願',
        elements: [{
          id: 'nps-1',
          type: 'nps',
          field_key: 'nps_1',
          label: '您有多大可能推薦我們？',
          description: '',
          required: true,
          options: [],
          settings: {
            color_bands: true,
            low_label: '完全不可能',
            high_label: '非常可能',
          },
        }],
      }],
    } as typeof store.schema;
    store.selectedPageId = 'page-1';
    store.isPreviewMode = true;

    const wrapper = mount(CanvasArea, {
      props: { endpoints, csrfToken: 'token' },
      global: {
        plugins: [pinia],
        stubs: { RightPanel: true },
      },
    });

    const scores = wrapper.findAll('.survey-nps-row .survey-nps-pip');
    expect(scores).toHaveLength(11);
    expect(scores.every(score => score.element.tagName === 'BUTTON')).toBe(true);
    expect(scores.every(score => score.attributes('type') === 'button')).toBe(true);
    expect(scores[0].text()).toBe('0');
    expect(scores[10].text()).toBe('10');

    await scores[0].trigger('click');
    expect(scores[0].classes()).toContain('selected');
    expect(scores[0].attributes('aria-pressed')).toBe('true');

    await scores[10].trigger('click');
    expect(scores[0].classes()).not.toContain('selected');
    expect(scores[0].attributes('aria-pressed')).toBe('false');
    expect(scores[10].classes()).toContain('selected');
    expect(scores[10].attributes('aria-pressed')).toBe('true');
  });
});
