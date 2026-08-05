// @vitest-environment jsdom

import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, describe, expect, it } from 'vitest';
import BuilderActivityPanel from '../../resources/js/builder/dialogs/BuilderActivityPanel.vue';
import CascadeDialog from '../../resources/js/builder/dialogs/CascadeDialog.vue';
import MatrixColsDialog from '../../resources/js/builder/dialogs/MatrixColsDialog.vue';
import NpsDialog from '../../resources/js/builder/dialogs/NpsDialog.vue';
import NumberDialog from '../../resources/js/builder/dialogs/NumberDialog.vue';
import RatingDialog from '../../resources/js/builder/dialogs/RatingDialog.vue';
import SettingsModal from '../../resources/js/builder/dialogs/SettingsModal.vue';

type DialogCase = {
  name: string;
  component: object;
  modelValue: unknown;
  expectedTitle: string;
  props?: Record<string, unknown>;
};

const dialogCases: DialogCase[] = [
  { name: '編輯紀錄', component: BuilderActivityPanel, modelValue: true, expectedTitle: '編輯紀錄' },
  {
    name: '巢狀選擇題資料',
    component: CascadeDialog,
    modelValue: { elementId: 'question-1', levels: [], data: [] },
    expectedTitle: '編輯巢狀選擇題資料',
  },
  {
    name: '矩陣答案',
    component: MatrixColsDialog,
    modelValue: { elementId: 'question-1', cols: [{ id: 'col-1', label: '滿意' }] },
    expectedTitle: '矩陣答案設定',
  },
  {
    name: 'NPS',
    component: NpsDialog,
    modelValue: { elementId: 'question-1', low_label: '', high_label: '', color_bands: false },
    expectedTitle: 'NPS 淨推薦值設定',
  },
  {
    name: '數字範圍',
    component: NumberDialog,
    modelValue: { elementId: 'question-1', label: '年齡', min: '', max: '', decimal_places: '', unit: '' },
    expectedTitle: '數字範圍設定',
  },
  {
    name: '評分題',
    component: RatingDialog,
    modelValue: { elementId: 'question-1', count: 5, shape: 'star', show_numbers: false },
    expectedTitle: '評分題設定',
  },
  {
    name: '問卷設定',
    component: SettingsModal,
    modelValue: true,
    expectedTitle: '問卷設定',
    props: { uploadImageUrl: '/images', csrfToken: 'token' },
  },
];

function mountDialog(dialogCase: DialogCase, modelValue: unknown = dialogCase.modelValue): VueWrapper {
  const pinia = createPinia();
  setActivePinia(pinia);

  return mount(dialogCase.component, {
    attachTo: document.body,
    props: {
      modelValue,
      ...dialogCase.props,
    },
    global: { plugins: [pinia] },
  });
}

afterEach(() => {
  document.body.innerHTML = '';
});

describe('Survey Builder dialogs accessibility', () => {
  it.each(dialogCases)('$name dialog has a labelled modal relationship', async (dialogCase) => {
    const wrapper = mountDialog(dialogCase);
    await wrapper.vm.$nextTick();

    const dialog = document.body.querySelector<HTMLElement>('[role="dialog"]');
    const titleId = dialog?.getAttribute('aria-labelledby');

    expect(dialog?.getAttribute('aria-modal')).toBe('true');
    expect(titleId).toBeTruthy();
    expect(document.getElementById(titleId ?? '')?.textContent).toBe(dialogCase.expectedTitle);
    expect(dialog?.querySelector<HTMLButtonElement>('.sb-settings-close, .sb-activity-close')?.getAttribute('aria-label')).toBeTruthy();

    wrapper.unmount();
  });

  it('traps focus, closes on Escape, and returns focus to the opener', async () => {
    const dialogCase = dialogCases.find(({ name }) => name === '數字範圍')!;
    const opener = document.createElement('button');
    opener.textContent = '開啟數字設定';
    document.body.append(opener);

    const wrapper = mountDialog(dialogCase, null);
    opener.focus();
    await wrapper.setProps({ modelValue: dialogCase.modelValue });
    await wrapper.vm.$nextTick();

    const dialog = document.body.querySelector<HTMLElement>('[role="dialog"]')!;
    const focusable = Array.from(dialog.querySelectorAll<HTMLElement>('button:not([disabled]), input:not([disabled]), select:not([disabled])'));
    const first = focusable[0];
    const last = focusable.at(-1)!;

    expect(document.activeElement).toBe(first);

    last.focus();
    last.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true, cancelable: true }));
    expect(document.activeElement).toBe(first);

    first.focus();
    first.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true, cancelable: true }));
    expect(document.activeElement).toBe(last);

    dialog.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true, cancelable: true }));
    await wrapper.setProps({ modelValue: null });
    await wrapper.vm.$nextTick();

    expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);
    expect(document.activeElement).toBe(opener);

    wrapper.unmount();
  });

  it('focuses the activity panel close control instead of its backdrop', async () => {
    const dialogCase = dialogCases.find(({ name }) => name === '編輯紀錄')!;
    const wrapper = mountDialog(dialogCase);
    await wrapper.vm.$nextTick();

    expect(document.activeElement).toBe(document.body.querySelector('.sb-activity-close'));

    wrapper.unmount();
  });
});
