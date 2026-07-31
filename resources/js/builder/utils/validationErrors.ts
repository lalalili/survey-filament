import type { SurveyPage } from '../types/schema';

/**
 * 把伺服器回傳的驗證錯誤 key（例如 `pages.1.elements.3.options`）翻成使用者看得懂的
 * 位置與欄位標籤。
 *
 * 這裡刻意只吃純資料（pages 陣列與題號對照表），不碰 store 也不碰元件狀態，
 * 才能單獨測試——原本這 130 行內嵌在 CanvasArea 的 `<script setup>` 裡，只能透過
 * 渲染整個元件間接驗證。
 */

export interface ParsedError {
  raw: string;
  messages: string[];
  pageIndex: number | null;
  elementIndex: number | null;
  questionPageNumber: number | null;
  pageId: string | null;
  elementId: string | null;
  fieldName: string | null;
  pageLabel: string;
  elementNumber: number | null;
  elementLabel: string;
  fieldLabel: string;
  translatedMessages: string[];
}

const FIELD_LABELS: Record<string, string> = {
  type: '題型', label: '標題', field_key: '欄位代碼', required: '必填',
  options: '選項', description: '說明', settings: '設定', placeholder: '提示文字',
  title: '頁面標題', kind: '頁面類型',
  matrix_rows: '矩陣列', matrix_cols: '矩陣欄',
  cascade_levels: '層級設定', cascade_data: '選項資料',
  personalized_key: '對應名單欄位',
  show_if: '顯示條件',
};

const MSG_MAP: Array<[RegExp | string, string]> = [
  [/顯示條件請填寫輸入值/i, '顯示條件請填寫輸入值'],
  [/顯示條件請選擇目標題目/i, '顯示條件請選擇目標題目'],
  [/^選取的.*type.*無效$/i, '此題型目前不受支援，請改用其他題型'],
  [/^The selected.*type is invalid/i, '此題型目前不受支援，請改用其他題型'],
  [/field key is required/i, '欄位代碼不得為空'],
  [/field key must be unique/i, '欄位代碼不可重複'],
  [/at least one option is required/i, '至少需要一個選項'],
  [/option.*label.*required/i, '選項文字不得為空'],
  [/option.*id.*required/i, '選項代碼不得為空'],
  [/at least one matrix row/i, '矩陣至少需要一列'],
  [/at least one matrix col/i, '矩陣至少需要一欄'],
  [/required/i, '此欄位為必填'],
  [/max.*255/i, '字數超過上限（255 字）'],
  [/invalid jump action/i, '跳題動作設定無效'],
  [/backward jump/i, '不可跳回前面的頁面'],
  [/target page does not exist/i, '跳題目標頁面不存在'],
  [/welcome.*required/i, '歡迎頁不可設定必填題'],
  [/thank.*required/i, '感謝頁不可設定必填題'],
];

export function translateMessage(msg: string): string {
  for (const [pattern, replacement] of MSG_MAP) {
    if (typeof pattern === 'string' ? msg.includes(pattern) : pattern.test(msg)) return replacement;
  }
  return msg;
}

export function readableFieldLabel(fieldName: string | null): string {
  if (!fieldName) return '';

  if (fieldName === 'settings.source_field_key') {
    return '來源題目';
  }

  const showIfCondition = fieldName.match(/^show_if\.conditions\.(\d+)\.(field_key|value)$/);
  if (showIfCondition) {
    const conditionNumber = Number(showIfCondition[1]) + 1;
    const target = showIfCondition[2] === 'field_key' ? '目標題目' : '輸入值';

    return `顯示條件 ${conditionNumber} 的${target}`;
  }

  return FIELD_LABELS[fieldName.split('.')[0]] ?? fieldName;
}

export function parseErrorKey(
  key: string,
  messages: string[],
  pages: SurveyPage[],
  questionNumberMap: Record<string, number>,
): ParsedError {
  const translatedMessages = messages.map(translateMessage);
  const base: ParsedError = {
    raw: key, messages, translatedMessages,
    pageIndex: null, elementIndex: null, questionPageNumber: null,
    pageId: null, elementId: null, fieldName: null,
    pageLabel: '', elementNumber: null, elementLabel: '', fieldLabel: '',
  };

  const m = key.match(/^pages\.(\d+)(?:\.elements\.(\d+))?(?:\.(.+))?$/);
  if (!m) return base;

  const pageIndex = parseInt(m[1]);
  const elementIndex = m[2] !== undefined ? parseInt(m[2]) : null;
  const fieldName = m[3] ?? null;

  const page = pages[pageIndex];
  const element = elementIndex !== null ? page?.elements[elementIndex] : null;
  const pageId = page?.id ?? null;
  const elementId = element?.id ?? null;

  const questionPageNumber = (() => {
    let n = 0;
    for (let i = 0; i <= pageIndex && i < pages.length; i++) {
      if ((pages[i]?.kind ?? 'question') === 'question') n++;
    }
    return (page?.kind ?? 'question') === 'question' ? n : null;
  })();

  const pageKind = page?.kind ?? 'question';
  let pageLabel = '';
  if (pageKind === 'welcome') pageLabel = '歡迎頁';
  else if (pageKind === 'thank_you') pageLabel = '感謝頁';
  else if (questionPageNumber !== null) pageLabel = `第 ${questionPageNumber} 頁`;
  else pageLabel = `頁面 ${pageIndex + 1}`;

  const elementNumber = elementId ? questionNumberMap[elementId] ?? null : null;
  const elementLabel = element?.label ? `「${element.label}」` : elementNumber !== null ? `第 ${elementNumber} 題` : '';

  return {
    raw: key, messages, translatedMessages, pageIndex, elementIndex, questionPageNumber,
    pageId, elementId, fieldName, pageLabel, elementNumber, elementLabel,
    fieldLabel: readableFieldLabel(fieldName),
  };
}
