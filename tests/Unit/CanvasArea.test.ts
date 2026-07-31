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

describe('CanvasArea responsive workspace', () => {
  function mountWorkspace(elements: Record<string, unknown>[] = []) {
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();
    store.schema = {
      title: '行動版問卷',
      pages: [{
        id: 'page-1',
        kind: 'question',
        title: '第一頁',
        elements,
      }],
    } as typeof store.schema;
    store.selectedPageId = 'page-1';

    const wrapper = mount(CanvasArea, {
      props: { endpoints, csrfToken: 'token' },
      global: { plugins: [pinia] },
    });

    return { wrapper, store };
  }

  it('switches between the mobile canvas and right-panel workspaces with tab semantics', async () => {
    const { wrapper, store } = mountWorkspace();
    const tabs = wrapper.findAll('.sb-workspace-tab');

    expect(tabs.map(tab => tab.text())).toEqual(['畫布', '題型庫', '屬性', '邏輯']);
    expect(tabs[0].attributes('aria-pressed')).toBe('true');
    expect(wrapper.get('.sb-canvas').classes()).toContain('is-mobile-active');

    await tabs[1].trigger('click');

    expect(tabs[1].attributes('aria-pressed')).toBe('true');
    expect(store.rightPanelTab).toBe('library');
    expect(wrapper.get('.sb-workspace-panel').classes()).toContain('is-mobile-active');

    await tabs[1].trigger('keydown', { key: 'ArrowRight' });

    expect(tabs[2].attributes('aria-pressed')).toBe('true');
    expect(tabs[2].attributes('tabindex')).toBe('0');
    expect(tabs[1].attributes('tabindex')).toBe('-1');
    expect(store.rightPanelTab).toBe('properties');

    await tabs[0].trigger('click');
    store.rightPanelTab = 'logic';
    await wrapper.vm.$nextTick();
    expect(tabs[3].attributes('aria-pressed')).toBe('true');
  });

  it('does not expose an orphaned workspace tabpanel while previewing', async () => {
    const { wrapper, store } = mountWorkspace();

    store.isPreviewMode = true;
    await wrapper.vm.$nextTick();

    expect(wrapper.find('.sb-workspace-nav').exists()).toBe(false);
    expect(wrapper.get('.sb-canvas').attributes('role')).toBeUndefined();
    expect(wrapper.get('.sb-canvas').attributes('aria-label')).toBeUndefined();
  });

  it('opens the library from the empty state and opens properties after adding a question', async () => {
    const { wrapper, store } = mountWorkspace();

    expect(wrapper.text()).toContain('從題型庫加入此頁面');
    expect(wrapper.text()).not.toContain('從右側');

    await wrapper.get('.sb-empty-page .sb-btn').trigger('click');
    expect(store.rightPanelTab).toBe('library');

    await wrapper.get('.sb-qlib-item').trigger('click');
    await wrapper.vm.$nextTick();

    expect(store.selectedElementId).not.toBeNull();
    expect(store.rightPanelTab).toBe('properties');
    expect(wrapper.findAll('.sb-workspace-tab')[2].attributes('aria-pressed')).toBe('true');
  });

  it('uses native page tabs and routes condition controls to the logic workspace', async () => {
    const { wrapper, store } = mountWorkspace([{
      id: 'question-1',
      type: 'single_choice',
      field_key: 'question_1',
      label: '選擇方案',
      description: '',
      required: false,
      options: [{ id: 'option-1', label: '方案 A', value: 'a' }],
      settings: {},
      show_if: { logic: 'and', conditions: [{ field_key: 'other', op: 'equals', value: 'a' }] },
    }]);

    const pageTab = wrapper.get('.sb-page-tab-select');
    expect(pageTab.element.tagName).toBe('BUTTON');
    expect(pageTab.attributes('role')).toBe('tab');
    expect(pageTab.attributes('aria-selected')).toBe('true');
    expect(pageTab.attributes('aria-controls')).toBe('sb-page-panel');
    expect(wrapper.get('#sb-page-panel').attributes('aria-labelledby')).toBe(pageTab.attributes('id'));
    expect(wrapper.get('.sb-page-tab-close').attributes('aria-label')).toContain('刪除頁面');

    await wrapper.get('.sb-badge-btn').trigger('click');

    expect(store.selectedElementId).toBe('question-1');
    expect(store.rightPanelTab).toBe('logic');
    expect(wrapper.findAll('.sb-workspace-tab')[3].attributes('aria-pressed')).toBe('true');

    store.rightPanelTab = 'properties';
    store.rightPanelTab = 'logic';
    await wrapper.vm.$nextTick();
    expect(wrapper.findAll('.sb-workspace-tab')[3].attributes('aria-pressed')).toBe('true');
  });

  it('moves between page tabs with arrow keys', async () => {
    const { wrapper, store } = mountWorkspace();
    store.schema!.pages.push({
      id: 'page-2',
      kind: 'question',
      title: '第二頁',
      elements: [],
    });
    await wrapper.vm.$nextTick();

    const pageTabs = wrapper.findAll('.sb-page-tab-select');
    expect(pageTabs).toHaveLength(2);

    await pageTabs[0].trigger('keydown', { key: 'ArrowRight' });

    expect(store.selectedPageId).toBe('page-2');
    expect(pageTabs[1].attributes('aria-selected')).toBe('true');
    expect(pageTabs[1].attributes('tabindex')).toBe('0');
  });

  it('exposes the settings entry and loading announcement', async () => {
    const { wrapper, store } = mountWorkspace();

    await wrapper.get('.sb-workspace-settings').trigger('click');
    expect(store.showSettingsModal).toBe(true);

    store.isLoading = true;
    await wrapper.vm.$nextTick();
    expect(wrapper.get('.sb-loading').attributes()).toMatchObject({
      role: 'status',
      'aria-live': 'polite',
    });
  });
});
