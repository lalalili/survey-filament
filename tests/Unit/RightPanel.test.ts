// @vitest-environment jsdom

import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import RightPanel from '../../resources/js/builder/components/RightPanel.vue';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

function mountQuestion(type = 'short_text', guideUrl?: string) {
  const pinia = createPinia();
  setActivePinia(pinia);

  const store = useSurveyBuilderStore();
  store.capabilities.can_manage_advanced_fields = true;
  store.schema = {
    title: '問卷',
    pages: [{
      id: 'page-1',
      kind: 'question',
      title: '第一頁',
      elements: [{
        id: 'question-1',
        type,
        field_key: 'question_1',
        label: '姓名',
        description: '',
        required: false,
        options: [],
        settings: {},
        validation_rules: {},
      }],
    }],
  } as typeof store.schema;
  store.selectedPageId = 'page-1';
  store.selectedElementId = 'question-1';
  store.rightPanelTab = 'properties';

  return mount(RightPanel, {
    props: { guideUrl },
    global: { plugins: [pinia] },
  });
}

describe('RightPanel format rule help', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('links text questions to the format rule examples in the configured guide', () => {
    const wrapper = mountQuestion('short_text', '/admin/survey-guide');
    const link = wrapper.get('a[href="/admin/survey-guide"]');

    expect(wrapper.text()).toContain('不確定如何填寫時，可參考文件中的');
    expect(link.text()).toBe('常用格式規則範例');
    expect(link.attributes('target')).toBe('_blank');
    expect(link.attributes('rel')).toBe('noopener noreferrer');
  });

  it('does not render the guide link when no guide URL is configured', () => {
    const wrapper = mountQuestion();

    expect(wrapper.text()).toContain('使用正規表示式限制輸入格式');
    expect(wrapper.find('a').exists()).toBe(false);
  });

  it.each([
    'cascade_select',
    'matrix_single',
    'matrix_multi',
    'selection_based',
    'number',
    'date',
    'constant_sum',
    'rating',
    'nps',
    'linear_scale',
  ])('hides the empty advanced settings section for %s questions', (type) => {
    const wrapper = mountQuestion(type, '/admin/survey-guide');

    expect(wrapper.text()).not.toContain('進階設定');
  });
});
