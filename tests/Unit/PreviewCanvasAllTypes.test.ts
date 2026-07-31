// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { describe, expect, it, vi } from 'vitest';
import CanvasArea from '../../resources/js/builder/components/CanvasArea.vue';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

/**
 * 每個題型在預覽面各有一條 `v-else-if` 分支，只有渲染到才會暴露綁定錯誤。
 * 這個 smoke test 把所有分支一次全部渲染出來，作為預覽面重構的安全網。
 */

const endpoints = {
  show: '/builder',
  update: '/builder-schema',
  publish: '/publish',
  activities: '/activities',
  restorePublished: '/restore',
  uploadImage: '/images',
  cascadeTemplate: '/cascade/template',
  cascadeImport: '/cascade/import',
};

type AnyRecord = Record<string, unknown>;

const options = [
  { id: 'o1', label: '選項一', value: 'one' },
  { id: 'o2', label: '選項二', value: 'two' },
];

const matrixRows = [{ id: 'r1', label: '列一' }, { id: 'r2', label: '列二' }];
const matrixCols = [{ id: 'c1', label: '欄一' }, { id: 'c2', label: '欄二' }];

/** 預覽面支援的所有題型；新增題型時這裡要一起加。 */
const PREVIEW_TYPES = [
  'single_choice', 'multiple_choice', 'select', 'short_text', 'long_text',
  'email', 'phone', 'date', 'time', 'number', 'linear_scale', 'rating', 'nps',
  'constant_sum', 'ranking', 'selection_based', 'file_upload', 'signature',
  'address', 'matrix_single', 'matrix_multi', 'cascade_select',
  'section_title', 'description_block', 'quote_block', 'divider',
] as const;

function elementFor(type: string, index: number): AnyRecord {
  const base: AnyRecord = {
    id: `q-${index}`,
    type,
    field_key: `field_${index}`,
    label: `${type} 題`,
    description: `${type} 說明`,
    required: false,
    options: [...options],
    settings: {},
  };

  if (type === 'matrix_single' || type === 'matrix_multi') {
    return { ...base, matrix_rows: matrixRows, matrix_cols: matrixCols };
  }

  if (type === 'cascade_select') {
    return {
      ...base,
      cascade_levels: [{ id: 'l1', label: '縣市' }, { id: 'l2', label: '區域' }],
      cascade_data: [{ id: 'n1', label: '台北市', children: [{ id: 'n1-1', label: '中正區' }] }],
    };
  }

  if (type === 'selection_based') {
    return { ...base, settings: { source_field_key: 'field_0' } };
  }

  if (type === 'constant_sum') {
    return { ...base, settings: { total: 100, unit: '%' } };
  }

  if (type === 'address') {
    return { ...base, settings: { fields_enabled: ['country', 'city'], country_locked: '台灣' } };
  }

  if (type === 'rating') {
    return { ...base, settings: { count: 5, shape: 'heart', show_numbers: true } };
  }

  if (type === 'linear_scale') {
    return { ...base, settings: { min: 1, max: 7, step: 1, unit: '分' } };
  }

  if (type === 'file_upload') {
    return { ...base, settings: { allowed_mimes: ['pdf'], max_size_mb: 5 } };
  }

  return base;
}

describe('PreviewCanvas 全題型渲染', () => {
  it('renders every supported question type without warnings or unresolved bindings', () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {});
    const error = vi.spyOn(console, 'error').mockImplementation(() => {});

    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();
    store.schema = {
      title: '全題型問卷',
      settings: { terms_text: '我同意', progress: { mode: 'bar' } },
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: PREVIEW_TYPES.map((type, index) => elementFor(type, index)),
      }],
    } as typeof store.schema;
    store.selectedPageId = 'page-1';
    store.isPreviewMode = true;

    const wrapper = mount(CanvasArea, {
      props: { endpoints, csrfToken: 'token' },
      global: { plugins: [pinia], stubs: { RightPanel: true } },
    });

    const html = wrapper.html();

    // 每個題型至少留下自己的標記，代表該分支確實渲染過。
    expect(wrapper.findAll('.survey-choice-label').length).toBeGreaterThan(0);
    expect(wrapper.find('select.survey-select').exists()).toBe(true);
    expect(wrapper.find('textarea.survey-textarea').exists()).toBe(true);
    expect(wrapper.find('.survey-rating-stars').exists()).toBe(true);
    expect(wrapper.find('.survey-nps-row').exists()).toBe(true);
    expect(wrapper.find('.survey-constant-sum-summary').exists()).toBe(true);
    expect(wrapper.find('.sb-preview-ranking').exists()).toBe(true);
    expect(wrapper.find('.survey-file-dropzone').exists()).toBe(true);
    expect(wrapper.find('.sb-preview-address').exists()).toBe(true);
    expect(wrapper.find('.survey-matrix').exists()).toBe(true);
    expect(wrapper.find('.survey-cascade-grid').exists()).toBe(true);
    expect(wrapper.find('.survey-linear-scale').exists()).toBe(true);
    expect(wrapper.find('.survey-section-title').exists()).toBe(true);
    expect(wrapper.find('.survey-quote-block').exists()).toBe(true);
    expect(wrapper.find('.survey-divider').exists()).toBe(true);

    // 未解析的綁定會在輸出留下字面的 mustache。
    expect(html).not.toContain('{{');

    expect(error).not.toHaveBeenCalled();
    expect(warn.mock.calls.filter(([message]) => String(message).includes('Property'))).toHaveLength(0);

    warn.mockRestore();
    error.mockRestore();
  });
});
