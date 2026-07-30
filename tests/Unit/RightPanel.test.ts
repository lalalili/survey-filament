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

describe('RightPanel Chinese length limit', () => {
  it.each(['short_text', 'long_text'])('renders the limit for %s questions', (type) => {
    const wrapper = mountQuestion(type);

    expect(wrapper.text()).toContain('最少中文字數');
    expect(wrapper.text()).toContain('只計漢字，不含英數、空白與標點。');
  });

  it.each(['phone', 'number', 'multiple_choice'])('hides the limit for %s questions', (type) => {
    const wrapper = mountQuestion(type);

    expect(wrapper.text()).not.toContain('最少中文字數');
  });

  it('writes the input value to min_chinese_length', async () => {
    const wrapper = mountQuestion('short_text');
    const label = wrapper.findAll('label').find((candidate) => candidate.text().includes('最少中文字數'));
    const input = label?.get('input');

    expect(input?.attributes()).toMatchObject({
      type: 'number',
      min: '0',
      step: '1',
      placeholder: '不限制',
    });

    await input?.setValue('6');

    expect(useSurveyBuilderStore().allElements[0]?.validation_rules?.min_chinese_length).toBe(6);
  });

  it.each([
    ['short_text', '最少字數', 'min_length'],
    ['short_text', '最多字數', 'max_length'],
    ['short_text', '最少中文字數', 'min_chinese_length'],
    ['long_text', '最少字數', 'min_length'],
    ['long_text', '最多字數', 'max_length'],
    ['long_text', '最少中文字數', 'min_chinese_length'],
  ])('restores %s %s to unlimited when cleared', async (type, labelText, rule) => {
    const wrapper = mountQuestion(type);
    const label = wrapper.findAll('label').find((candidate) => candidate.text().includes(labelText));
    const input = label?.get('input');

    await input?.setValue('6');
    expect(useSurveyBuilderStore().allElements[0]?.validation_rules?.[rule]).toBe(6);

    await input?.setValue('');

    expect(useSurveyBuilderStore().allElements[0]?.validation_rules?.[rule]).toBeNull();
    expect(input?.element.value).toBe('');
    expect(input?.attributes('placeholder')).toBe('不限制');
  });
});
