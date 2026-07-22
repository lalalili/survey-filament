// @vitest-environment jsdom

import { mount, type VueWrapper } from '@vue/test-utils';
import { afterEach, describe, expect, it, vi } from 'vitest';
import SurveyRichEditor from '../../resources/js/builder/components/SurveyRichEditor.vue';

vi.mock('@tiptap/extension-placeholder', async () => {
  const { Extension } = await import('@tiptap/core');

  return { default: Extension.create({ name: 'placeholder' }) };
});

const editorHolder = vi.hoisted(() => ({ current: null as Record<string, any> | null }));

vi.mock('@tiptap/vue-3', async () => {
  const { defineComponent, h, shallowRef } = await import('vue');

  return {
    EditorContent: defineComponent({
      name: 'EditorContent',
      setup: () => () => h('div', { class: 'ProseMirror' }),
    }),
    useEditor: (options: { content?: string; onUpdate?: ({ editor }: { editor: object }) => void }) => {
      let html = options.content ?? '<p></p>';
      const editor: Record<string, any> = {
        getHTML: () => html,
        getAttributes: () => ({}),
        isActive: () => false,
        commands: { setContent: vi.fn((content: string) => { html = content || '<p></p>'; }) },
        destroy: vi.fn(),
      };
      editorHolder.current = editor;
      const emitUpdate = () => options.onUpdate?.({ editor });
      const chain = {
        focus: () => chain,
        toggleBold: () => { html = '<p><strong>測試文字</strong></p>'; return chain; },
        toggleItalic: () => { html = '<p><em>測試文字</em></p>'; return chain; },
        toggleUnderline: () => { html = '<p><u>測試文字</u></p>'; return chain; },
        toggleHeading: ({ level }: { level: number }) => { html = `<h${level}>測試文字</h${level}>`; return chain; },
        setTextAlign: (alignment: string) => { html = `<p style="text-align: ${alignment}">測試文字</p>`; return chain; },
        setColor: (color: string) => { html = `<p><span style="color: ${color}">測試文字</span></p>`; return chain; },
        unsetColor: () => { html = '<p>測試文字</p>'; return chain; },
        run: () => { emitUpdate(); return true; },
      };
      editor.chain = () => chain;

      return shallowRef(editor);
    },
  };
});

const presetColors = [
  '#000000', '#374151', '#6b7280', '#9ca3af',
  '#ef4444', '#f97316', '#eab308', '#22c55e',
  '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6',
];

function mountEditor(): VueWrapper {
  return mount(SurveyRichEditor, { props: { modelValue: '<p>測試文字</p>' } });
}

function latestHtml(wrapper: VueWrapper): string {
  const emissions = wrapper.emitted<string[]>('update:modelValue') ?? [];
  return emissions.at(-1)?.[0] ?? '';
}

afterEach(() => {
  document.body.innerHTML = '';
  editorHolder.current = null;
});

describe('SurveyRichEditor 自動儲存回寫', () => {
  function setContentMock() {
    return editorHolder.current!.commands.setContent as ReturnType<typeof vi.fn>;
  }

  it('編輯中收到自動儲存回傳的內容時不重建文件', async () => {
    const wrapper = mount(SurveyRichEditor, {
      props: { modelValue: '<p>測試文字</p>' },
      attachTo: document.body,
    });

    (wrapper.get('button[title="文字顏色"]').element as HTMLButtonElement).focus();
    await wrapper.setProps({ modelValue: '<p>&#28204;&#35430;&#25991;&#23383;</p>' });

    expect(setContentMock()).not.toHaveBeenCalled();
    wrapper.unmount();
  });

  it('焦點離開編輯器後才套用待處理的外部內容', async () => {
    vi.useFakeTimers();

    const wrapper = mount(SurveyRichEditor, {
      props: { modelValue: '<p>測試文字</p>' },
      attachTo: document.body,
    });

    const colorButton = wrapper.get('button[title="文字顏色"]');
    (colorButton.element as HTMLButtonElement).focus();
    await wrapper.setProps({ modelValue: '<p>伺服器淨化版</p>' });

    (colorButton.element as HTMLButtonElement).blur();
    await colorButton.trigger('focusout');
    vi.runAllTimers();

    expect(setContentMock()).toHaveBeenCalledWith('<p>伺服器淨化版</p>', false);

    vi.useRealTimers();
    wrapper.unmount();
  });

  it('未在編輯時外部內容立即套用', async () => {
    const wrapper = mount(SurveyRichEditor, {
      props: { modelValue: '<p>測試文字</p>' },
      attachTo: document.body,
    });

    await wrapper.setProps({ modelValue: '<p>外部更新</p>' });

    expect(setContentMock()).toHaveBeenCalledWith('<p>外部更新</p>', false);
    wrapper.unmount();
  });
});

describe('SurveyRichEditor toolbar', () => {
  it.each([
    ['粗體', '<strong>測試文字</strong>'],
    ['斜體', '<em>測試文字</em>'],
    ['底線', '<u>測試文字</u>'],
  ])('applies %s and emits formatted HTML', async (title, expectedHtml) => {
    const wrapper = mountEditor();
    await wrapper.get(`button[title="${title}"]`).trigger('click');

    expect(latestHtml(wrapper)).toContain(expectedHtml);
    wrapper.unmount();
  });

  it.each([
    ['標題', '<h2>測試文字</h2>'],
    ['子標題', '<h3>測試文字</h3>'],
  ])('applies %s and emits heading HTML', async (title, expectedHtml) => {
    const wrapper = mountEditor();
    await wrapper.get(`button[title="${title}"]`).trigger('click');

    expect(latestHtml(wrapper)).toBe(expectedHtml);
    wrapper.unmount();
  });

  it.each([
    ['靠左', 'left'],
    ['置中', 'center'],
    ['靠右', 'right'],
  ])('applies %s and emits aligned HTML', async (title, alignment) => {
    const wrapper = mountEditor();
    await wrapper.get(`button[title="${title}"]`).trigger('click');

    expect(latestHtml(wrapper)).toContain(`text-align: ${alignment}`);
    wrapper.unmount();
  });

  it('uses distinct, accessible icons for every alignment action', () => {
    const wrapper = mountEditor();
    const buttons = ['靠左', '置中', '靠右'].map((label) => wrapper.get(`button[aria-label="${label}"]`));
    const paths = buttons.map((button) => button.get('svg path').attributes('d'));

    expect(new Set(paths).size).toBe(3);
    buttons.forEach((button) => {
      expect(button.attributes('title')).toBe(button.attributes('aria-label'));
      expect(button.get('svg').attributes('aria-hidden')).toBe('true');
    });
    wrapper.unmount();
  });

  it.each(presetColors)('applies preset color %s and emits colored HTML', async (color) => {
    const wrapper = mountEditor();
    await wrapper.get('button[title="文字顏色"]').trigger('click');
    await wrapper.get(`button[title="${color}"]`).trigger('click');

    expect(latestHtml(wrapper)).toContain(`color: ${color}`);
    wrapper.unmount();
  });

  it('unsets an applied color and emits HTML without inline color', async () => {
    const wrapper = mountEditor();
    await wrapper.get('button[title="文字顏色"]').trigger('click');
    await wrapper.get('button[title="#ef4444"]').trigger('click');
    await wrapper.get('button[title="文字顏色"]').trigger('click');
    await wrapper.get('button[title="預設色"]').trigger('click');

    expect(latestHtml(wrapper)).toBe('<p>測試文字</p>');
    wrapper.unmount();
  });
});
