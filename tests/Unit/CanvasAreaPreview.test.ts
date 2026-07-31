// @vitest-environment jsdom

import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { nextTick } from 'vue';
import { afterEach, describe, expect, it, vi } from 'vitest';
import CanvasArea from '../../resources/js/builder/components/CanvasArea.vue';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

/**
 * Characterization tests for the builder preview runtime.
 *
 * 這些測試刻意只透過 DOM 互動斷言「預覽在使用者眼中的行為」，不碰任何內部
 * 識別字，因此預覽邏輯搬進 composable / 子元件後仍應原封不動通過。
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

function element(overrides: AnyRecord): AnyRecord {
  return {
    description: '',
    required: false,
    options: [],
    settings: {},
    ...overrides,
  };
}

function option(id: string, label: string, value: string, overrides: AnyRecord = {}): AnyRecord {
  return { id, label, value, ...overrides };
}

function mountPreview(schema: AnyRecord, selectedPageId: string) {
  const pinia = createPinia();
  setActivePinia(pinia);
  const store = useSurveyBuilderStore();
  store.schema = schema as typeof store.schema;
  store.selectedPageId = selectedPageId;
  store.isPreviewMode = true;

  const wrapper = mount(CanvasArea, {
    props: { endpoints, csrfToken: 'token' },
    global: { plugins: [pinia], stubs: { RightPanel: true } },
  });

  return { wrapper, store };
}

function labelsOf(wrapper: VueWrapper, selector: string): string[] {
  return wrapper.findAll(selector).map((node) => node.text());
}

describe('preview: 進入預覽時重置作答', () => {
  it('clears answers collected before preview mode was re-entered', async () => {
    const { wrapper, store } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({ id: 'q1', type: 'short_text', field_key: 'q_1', label: '姓名' })],
      }],
    }, 'page-1');

    await wrapper.get('input.survey-input').setValue('王小明');
    expect((wrapper.get('input.survey-input').element as HTMLInputElement).value).toBe('王小明');

    store.isPreviewMode = false;
    await nextTick();
    store.isPreviewMode = true;
    await nextTick();

    expect((wrapper.get('input.survey-input').element as HTMLInputElement).value).toBe('');
  });
});

describe('preview: 顯示條件', () => {
  const conditionalSchema = (logic: 'and' | 'or') => ({
    title: '問卷',
    pages: [{
      id: 'page-1',
      kind: 'question',
      title: '第一頁',
      elements: [
        element({
          id: 'q1',
          type: 'single_choice',
          field_key: 'plan',
          label: '方案',
          options: [option('o1', '基本', 'basic'), option('o2', '進階', 'pro')],
        }),
        element({ id: 'q2', type: 'short_text', field_key: 'note', label: '備註' }),
        element({
          id: 'q3',
          type: 'short_text',
          field_key: 'followup',
          label: '追問',
          show_if: {
            logic,
            conditions: [
              { field_key: 'plan', op: 'equals', value: 'pro' },
              { field_key: 'note', op: 'is_not_empty', value: null },
            ],
          },
        }),
      ],
    }],
  });

  it('hides the dependent question until an AND condition set is fully satisfied', async () => {
    const { wrapper } = mountPreview(conditionalSchema('and'), 'page-1');

    expect(labelsOf(wrapper, '.survey-field-label').join()).not.toContain('追問');
    expect(wrapper.findAll('.survey-field-card')).toHaveLength(2);

    await wrapper.findAll('input[type="radio"]')[1].trigger('change');
    expect(wrapper.findAll('.survey-field-card')).toHaveLength(2);

    await wrapper.get('input.survey-input').setValue('備註內容');
    expect(wrapper.findAll('.survey-field-card')).toHaveLength(3);
  });

  it('shows the dependent question as soon as one OR condition matches', async () => {
    const { wrapper } = mountPreview(conditionalSchema('or'), 'page-1');

    expect(wrapper.findAll('.survey-field-card')).toHaveLength(2);

    await wrapper.findAll('input[type="radio"]')[1].trigger('change');
    expect(wrapper.findAll('.survey-field-card')).toHaveLength(3);
  });
});

describe('preview: 選項動作與導覽', () => {
  const jumpSchema = {
    title: '問卷',
    pages: [
      {
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({
          id: 'q1',
          type: 'single_choice',
          field_key: 'route',
          label: '路線',
          options: [
            option('o1', '跳到第三頁', 'skip', { action: { type: 'go_to_page', target_page_id: 'page-3' } }),
            option('o2', '結束問卷', 'end', { action: { type: 'end_survey' } }),
            option('o3', '照順序', 'next'),
          ],
        })],
      },
      {
        id: 'page-2',
        kind: 'question',
        title: '第二頁',
        elements: [element({ id: 'q2', type: 'short_text', field_key: 'skipped', label: '被跳過的題' })],
      },
      {
        id: 'page-3',
        kind: 'question',
        title: '第三頁',
        elements: [element({ id: 'q3', type: 'short_text', field_key: 'final', label: '最後一題' })],
      },
    ],
  };

  it('jumps straight to the target page when an option carries go_to_page', async () => {
    const { wrapper, store } = mountPreview(structuredClone(jumpSchema), 'page-1');

    await wrapper.findAll('input[type="radio"]')[0].trigger('change');

    expect(store.selectedPageId).toBe('page-3');
    expect(wrapper.get('.survey-field-label').text()).toContain('最後一題');
  });

  it('ends the survey and can be reset when an option carries end_survey', async () => {
    const { wrapper } = mountPreview(structuredClone(jumpSchema), 'page-1');

    await wrapper.findAll('input[type="radio"]')[1].trigger('change');

    expect(wrapper.find('.sb-preview-end').exists()).toBe(true);
    expect(wrapper.get('.sb-preview-end-title').text()).toBe('問卷已結束');

    await wrapper.get('.sb-preview-end .sb-btn').trigger('click');
    expect(wrapper.find('.sb-preview-end').exists()).toBe(false);
  });

  it('discards answers on pages that a jump skipped over', async () => {
    const { wrapper, store } = mountPreview(structuredClone(jumpSchema), 'page-2');

    await wrapper.get('input.survey-input').setValue('第二頁的答案');

    store.selectedPageId = 'page-1';
    await nextTick();
    await wrapper.findAll('input[type="radio"]')[0].trigger('change');
    expect(store.selectedPageId).toBe('page-3');

    store.selectedPageId = 'page-2';
    await nextTick();
    expect((wrapper.get('input.survey-input').element as HTMLInputElement).value).toBe('');
  });

  it('returns through the jump history rather than the page order', async () => {
    const { wrapper, store } = mountPreview(structuredClone(jumpSchema), 'page-1');

    await wrapper.findAll('input[type="radio"]')[0].trigger('change');
    expect(store.selectedPageId).toBe('page-3');

    await wrapper.get('.sb-preview-nav-btn--secondary').trigger('click');
    expect(store.selectedPageId).toBe('page-1');
  });

  it('shows the submit notice and lands on the thank-you page from the last question page', async () => {
    const { wrapper, store } = mountPreview({
      title: '問卷',
      pages: [
        {
          id: 'page-1',
          kind: 'question',
          title: '第一頁',
          elements: [element({ id: 'q1', type: 'short_text', field_key: 'q_1', label: '姓名' })],
        },
        { id: 'page-thanks', kind: 'thank_you', title: '感謝頁', elements: [], thank_you_settings: { message: '' } },
      ],
    }, 'page-1');

    expect(wrapper.get('.sb-preview-nav-btn--primary').text()).toBe('提交');

    await wrapper.get('.sb-preview-nav-btn--primary').trigger('click');

    expect(store.selectedPageId).toBe('page-thanks');
    expect(wrapper.find('.sb-preview-submit-notice').exists()).toBe(true);
    expect(wrapper.get('.sb-preview-submit-notice').text()).toContain('預覽模式下不會真的送出填答');
  });
});

describe('preview: 進度條', () => {
  it('sizes the bar by question-page position and ignores welcome/thank-you pages', async () => {
    const pages = [
      { id: 'welcome', kind: 'welcome', title: '歡迎', elements: [], welcome_settings: {} },
      { id: 'page-1', kind: 'question', title: '第一頁', elements: [] },
      { id: 'page-2', kind: 'question', title: '第二頁', elements: [] },
      { id: 'page-3', kind: 'question', title: '第三頁', elements: [] },
      { id: 'thanks', kind: 'thank_you', title: '感謝', elements: [], thank_you_settings: {} },
    ];

    const { wrapper, store } = mountPreview({ title: '問卷', pages }, 'page-1');
    expect(wrapper.get('.sb-preview-progress div').attributes('style')).toContain('width: 33.3');

    store.selectedPageId = 'page-3';
    await nextTick();
    expect(wrapper.get('.sb-preview-progress div').attributes('style')).toContain('width: 100%');
  });
});

describe('preview: constant_sum 合計狀態', () => {
  it('reports under, matched and over against the configured total', async () => {
    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({
          id: 'q1',
          type: 'constant_sum',
          field_key: 'budget',
          label: '預算分配',
          options: [option('o1', '行銷', 'marketing'), option('o2', '研發', 'rd')],
          settings: { total: 100 },
        })],
      }],
    }, 'page-1');

    const summary = () => wrapper.get('.survey-constant-sum-summary');
    expect(summary().attributes('data-status')).toBe('under');
    expect(summary().text()).toContain('剩餘 100');

    const inputs = wrapper.findAll('.survey-constant-sum-row input');
    await inputs[0].setValue('60');
    await inputs[1].setValue('40');
    expect(summary().attributes('data-status')).toBe('matched');
    expect(summary().text()).toContain('合計符合目標');

    await inputs[1].setValue('50');
    expect(summary().attributes('data-status')).toBe('over');
    expect(summary().text()).toContain('超出 10');
  });
});

describe('preview: ranking 排序', () => {
  it('moves an option up and disables the arrows at the ends', async () => {
    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({
          id: 'q1',
          type: 'ranking',
          field_key: 'rank',
          label: '請排序',
          options: [option('o1', '甲', 'a'), option('o2', '乙', 'b'), option('o3', '丙', 'c')],
        })],
      }],
    }, 'page-1');

    expect(labelsOf(wrapper, '.sb-preview-ranking-label')).toEqual(['甲', '乙', '丙']);

    const upButtons = () => wrapper.findAll('.sb-preview-ranking-item').map((item) => item.findAll('button')[0]);
    expect(upButtons()[0].attributes('disabled')).toBeDefined();

    await upButtons()[2].trigger('click');
    expect(labelsOf(wrapper, '.sb-preview-ranking-label')).toEqual(['甲', '丙', '乙']);
    expect(labelsOf(wrapper, '.sb-preview-ranking-position')).toEqual(['1', '2', '3']);
  });
});

describe('preview: rating', () => {
  it('selects a score and clears it when the same score is clicked again', async () => {
    vi.useFakeTimers();
    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({ id: 'q1', type: 'rating', field_key: 'score', label: '評分', settings: { count: 5 } })],
      }],
    }, 'page-1');

    const stars = wrapper.findAll('.survey-rating-star-label');
    expect(stars).toHaveLength(5);

    await stars[2].trigger('click');
    expect(wrapper.findAll('.survey-rating-star-label.filled')).toHaveLength(3);

    await stars[2].trigger('click');
    expect(wrapper.findAll('.survey-rating-star-label.filled')).toHaveLength(0);
    vi.useRealTimers();
  });
});

describe('preview: linear_scale', () => {
  it('derives the fill percentage from min/max and the current value', async () => {
    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({
          id: 'q1',
          type: 'linear_scale',
          field_key: 'scale',
          label: '滿意度',
          settings: { min: 0, max: 10 },
        })],
      }],
    }, 'page-1');

    const slider = wrapper.get('input.survey-linear-scale-input');
    expect(slider.attributes('style')).toContain('0%');

    await slider.setValue('5');
    expect(wrapper.get('input.survey-linear-scale-input').attributes('style')).toContain('50%');
    expect(wrapper.get('.survey-linear-scale-value').text()).toBe('5');
  });
});

describe('preview: 計算變數 token', () => {
  it('renders {{calc.*}} on the thank-you page using the answers given in preview', async () => {
    const { wrapper, store } = mountPreview({
      title: '問卷',
      calculations: [{ key: 'total', initial_value: 0, output_format: 'number' }],
      pages: [
        {
          id: 'page-1',
          kind: 'question',
          title: '第一頁',
          elements: [element({
            id: 'q1',
            type: 'single_choice',
            field_key: 'plan',
            label: '方案',
            options: [
              option('o1', '基本', 'basic', { score_delta_json: { total: 3 } }),
              option('o2', '進階', 'pro', { score_delta_json: { total: 8 } }),
            ],
          })],
        },
        {
          id: 'thanks',
          kind: 'thank_you',
          title: '感謝頁',
          elements: [],
          thank_you_settings: { message: '<p>您的分數是 {{calc.total}} 分</p>' },
        },
      ],
    }, 'page-1');

    await wrapper.findAll('input[type="radio"]')[1].trigger('change');
    await wrapper.get('.sb-preview-nav-btn--primary').trigger('click');

    expect(store.selectedPageId).toBe('thanks');
    expect(wrapper.get('.sb-preview-rich').text()).toContain('您的分數是 8 分');
  });
});

describe('preview: 條款同意', () => {
  it('keeps submit disabled on the last page until the terms checkbox is ticked', async () => {
    const { wrapper } = mountPreview({
      title: '問卷',
      settings: { terms_text: '我同意隱私權政策' },
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({ id: 'q1', type: 'short_text', field_key: 'q_1', label: '姓名' })],
      }],
    }, 'page-1');

    const submit = () => wrapper.get('.sb-preview-nav-btn--primary');
    expect(submit().attributes('disabled')).toBeDefined();

    await wrapper.get('.sb-preview-terms input').setValue(true);
    expect(submit().attributes('disabled')).toBeUndefined();
  });
});

describe('preview: 檔案上傳說明', () => {
  it('summarises the allowed formats by group and keeps unknown extensions verbatim', () => {
    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({
          id: 'q1',
          type: 'file_upload',
          field_key: 'attachment',
          label: '附件',
          settings: { allowed_mimes: ['pdf', 'png', 'dwg'], max_size_mb: 25 },
        })],
      }],
    }, 'page-1');

    expect(wrapper.get('.survey-file-format').text()).toBe('檔案格式：文件、圖片、dwg');
    expect(wrapper.get('.survey-file-limit').text()).toBe('25 MB以下');
  });

  it('falls back to 不限 when no extension is configured', () => {
    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({ id: 'q1', type: 'file_upload', field_key: 'attachment', label: '附件' })],
      }],
    }, 'page-1');

    expect(wrapper.get('.survey-file-format').text()).toBe('檔案格式：不限');
  });
});

describe('preview: 選項隨機', () => {
  afterEach(() => vi.restoreAllMocks());

  it('keeps a randomised option order stable while the preview session lasts', async () => {
    vi.spyOn(Math, 'random').mockReturnValue(0.42);

    const { wrapper } = mountPreview({
      title: '問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements: [element({
          id: 'q1',
          type: 'single_choice',
          field_key: 'plan',
          label: '方案',
          options: [option('o1', 'A', 'a'), option('o2', 'B', 'b'), option('o3', 'C', 'c'), option('o4', 'D', 'd')],
        }), element({
          id: 'q2',
          type: 'single_choice',
          field_key: 'plan_random',
          label: '隨機方案',
          options: [option('r1', 'A', 'a'), option('r2', 'B', 'b'), option('r3', 'C', 'c'), option('r4', 'D', 'd')],
          settings: { randomize_options: true },
        })],
      }],
    }, 'page-1');

    const orderOf = (cardIndex: number) => wrapper.findAll('.survey-field-card')[cardIndex]
      .findAll('.survey-choice-label').map((node) => node.text());

    // 未開啟隨機的題目維持原始順序。
    expect(orderOf(0)).toEqual(['A', 'B', 'C', 'D']);

    const randomised = orderOf(1);
    expect(randomised).toHaveLength(4);
    expect([...randomised].sort()).toEqual(['A', 'B', 'C', 'D']);

    // 同一次預覽期間重新渲染不會換順序。
    await wrapper.findAll('.survey-field-card')[0].findAll('input[type="radio"]')[0].trigger('change');
    expect(orderOf(1)).toEqual(randomised);
  });
});
