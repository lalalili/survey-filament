import { describe, expect, it } from 'vitest';
import { parseErrorKey, readableFieldLabel, translateMessage } from '../../resources/js/builder/utils/validationErrors';
import type { SurveyPage } from '../../resources/js/builder/types/schema';

const pages = [
  { id: 'welcome', kind: 'welcome', title: '歡迎', elements: [] },
  {
    id: 'page-1',
    kind: 'question',
    title: '第一頁',
    elements: [
      { id: 'q1', label: '姓名' },
      { id: 'q2', label: '' },
    ],
  },
  { id: 'page-2', kind: 'question', title: '第二頁', elements: [{ id: 'q3', label: '意見' }] },
  { id: 'thanks', kind: 'thank_you', title: '感謝', elements: [] },
] as unknown as SurveyPage[];

const questionNumberMap = { q1: 1, q2: 2, q3: 3 };

const parse = (key: string, messages: string[] = ['required']) =>
  parseErrorKey(key, messages, pages, questionNumberMap);

describe('translateMessage', () => {
  it('maps known server messages to Chinese', () => {
    expect(translateMessage('field key must be unique')).toBe('欄位代碼不可重複');
    expect(translateMessage('At least one option is required')).toBe('至少需要一個選項');
    expect(translateMessage('backward jump is not allowed')).toBe('不可跳回前面的頁面');
  });

  it('falls back to the original message when nothing matches', () => {
    expect(translateMessage('something entirely unexpected')).toBe('something entirely unexpected');
  });

  it('applies the first matching pattern, so specific rules must precede generic ones', () => {
    // 'at least one option is required' 同時符合 /required/i，順序決定結果。
    expect(translateMessage('at least one option is required')).toBe('至少需要一個選項');
  });
});

describe('readableFieldLabel', () => {
  it('labels plain fields and nested settings keys', () => {
    expect(readableFieldLabel('field_key')).toBe('欄位代碼');
    expect(readableFieldLabel('settings.source_field_key')).toBe('來源題目');
    expect(readableFieldLabel('matrix_cols')).toBe('矩陣欄');
  });

  it('numbers show_if conditions from one', () => {
    expect(readableFieldLabel('show_if.conditions.0.field_key')).toBe('顯示條件 1 的目標題目');
    expect(readableFieldLabel('show_if.conditions.2.value')).toBe('顯示條件 3 的輸入值');
  });

  it('returns the raw name when unknown, and empty for null', () => {
    expect(readableFieldLabel('mystery_field')).toBe('mystery_field');
    expect(readableFieldLabel(null)).toBe('');
  });
});

describe('parseErrorKey', () => {
  it('returns a neutral result for a key that is not page-scoped', () => {
    const parsed = parse('title');

    expect(parsed.pageIndex).toBeNull();
    expect(parsed.pageLabel).toBe('');
    expect(parsed.translatedMessages).toEqual(['此欄位為必填']);
  });

  it('numbers question pages while skipping welcome and thank-you pages', () => {
    expect(parse('pages.1.title').pageLabel).toBe('第 1 頁');
    expect(parse('pages.2.title').pageLabel).toBe('第 2 頁');
    expect(parse('pages.0.title').pageLabel).toBe('歡迎頁');
    expect(parse('pages.3.title').pageLabel).toBe('感謝頁');
  });

  it('prefers the element label and falls back to its question number', () => {
    expect(parse('pages.1.elements.0.label').elementLabel).toBe('「姓名」');
    // q2 沒有標題，退回題號。
    expect(parse('pages.1.elements.1.label').elementLabel).toBe('第 2 題');
  });

  it('resolves ids so the UI can highlight the offending page and element', () => {
    const parsed = parse('pages.2.elements.0.options');

    expect(parsed.pageId).toBe('page-2');
    expect(parsed.elementId).toBe('q3');
    expect(parsed.elementNumber).toBe(3);
    expect(parsed.fieldLabel).toBe('選項');
  });

  it('degrades gracefully when the key points outside the schema', () => {
    const parsed = parse('pages.99.elements.5.label');

    expect(parsed.pageId).toBeNull();
    expect(parsed.elementId).toBeNull();
    expect(parsed.elementLabel).toBe('');
    // 既有行為（非理想）：頁面不存在時 kind 退回 'question'，題頁編號因此變成
    // 「schema 裡所有題頁的總數」而非該 key 的真實位置——這裡是 2。標籤會指向一個
    // 存在但不相干的頁面。伺服器不會回傳超出範圍的 key，所以先以測試釘住現況。
    expect(parsed.pageLabel).toBe('第 2 頁');
  });
});
