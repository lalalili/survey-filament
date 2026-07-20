// @vitest-environment jsdom

import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it } from 'vitest';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

/**
 * 複製頁面時，副本頁內的參照必須指向副本自己的題目。
 *
 * 原本 duplicatePage() 只重新產生 element 的 id 與 field_key，show_if 的
 * field_key 沒有跟著改寫，於是副本頁的追問題仍指向「原始頁」的題目——線上
 * 問卷 13 就出現「座椅」的追問題跟著「空調」的答案顯示。
 */
function branchingPageSchema() {
  return {
    title: '問卷',
    pages: [
      {
        id: 'page-1',
        kind: 'question',
        title: '外觀',
        jump_rules: [
          {
            condition: {
              logic: 'and',
              conditions: [{ field_key: 'has_issue', op: 'equals', value: 'yes' }],
            },
            action: { type: 'go_to_page', target_page_id: 'page-2' },
          },
        ],
        elements: [
          {
            id: 'q-1',
            type: 'single_choice',
            field_key: 'has_issue',
            label: '是否有遇到問題',
            required: false,
            options: [
              { id: 'o1', label: '有問題', value: 'yes', action: { type: 'go_to_page', target_page_id: 'page-1' } },
              { id: 'o2', label: '沒問題', value: 'no' },
            ],
            settings: {},
            show_if: null,
          },
          {
            id: 'q-2',
            type: 'long_text',
            field_key: 'issue_detail',
            label: '請描述問題',
            required: false,
            options: [],
            settings: {},
            show_if: {
              logic: 'and',
              conditions: [{ field_key: 'has_issue', op: 'equals', value: 'yes' }],
            },
          },
        ],
      },
      { id: 'page-2', kind: 'question', title: '其他', elements: [] },
    ],
  };
}

function setupStore() {
  const store = useSurveyBuilderStore();
  store.schema = branchingPageSchema() as never;
  store.selectedPageId = 'page-1';

  return store;
}

describe('duplicatePage', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
  });

  it('re-points the copied show_if condition to the copied question', () => {
    const store = setupStore();
    store.duplicatePage('page-1');

    const original = store.schema!.pages[0];
    const copy = store.schema!.pages[1];

    const originalSourceKey = original.elements[0].field_key;
    const copySourceKey = copy.elements[0].field_key;
    const copyCondition = copy.elements[1].show_if!.conditions[0] as { field_key: string };

    expect(copySourceKey).not.toBe(originalSourceKey);
    expect(copyCondition.field_key).toBe(copySourceKey);
    expect(copyCondition.field_key).not.toBe(originalSourceKey);
  });

  it('leaves the original page untouched', () => {
    const store = setupStore();
    store.duplicatePage('page-1');

    const original = store.schema!.pages[0];
    const originalCondition = original.elements[1].show_if!.conditions[0] as { field_key: string };

    expect(original.elements[0].field_key).toBe('has_issue');
    expect(originalCondition.field_key).toBe('has_issue');
  });

  it('re-points jump rule conditions and self-referencing jump targets', () => {
    const store = setupStore();
    store.duplicatePage('page-1');

    const copy = store.schema!.pages[1];
    const ruleCondition = copy.jump_rules![0].condition.conditions[0] as { field_key: string };

    expect(ruleCondition.field_key).toBe(copy.elements[0].field_key);

    // 指向原始頁自己的跳轉目標要跟著改成副本頁；指向其他頁的維持不變。
    expect(copy.elements[0].options![0].action!.target_page_id).toBe(copy.id);
    expect(copy.jump_rules![0].action.target_page_id).toBe('page-2');
  });

  it('gives copied questions collision-free field keys', () => {
    const store = setupStore();
    store.duplicatePage('page-1');
    store.duplicatePage(store.schema!.pages[1].id);

    const keys = store.schema!.pages.flatMap((page) => page.elements.map((el) => el.field_key));
    const definedKeys = keys.filter((key): key is string => Boolean(key));

    expect(new Set(definedKeys).size).toBe(definedKeys.length);
    expect(definedKeys.every((key) => !key.includes('_copy_copy'))).toBe(true);
  });
});
