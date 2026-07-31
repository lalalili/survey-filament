import type { SurveyElement, SurveyPage } from '../types/schema';
import { isContentBlockType } from './builderHelpers';
import { isSystemContextField } from './systemContextFields';

/**
 * 題號：只對非內容元件（排除標題／說明文字／分隔線／引言）與非系統情境欄位計數，
 * 並跨頁累加，與正式填寫頁的 $questionNo 邏輯一致。
 */
export function buildQuestionNumberMap(pages: SurveyPage[]): Record<string, number> {
  const map: Record<string, number> = {};
  let questionNumber = 0;

  for (const page of pages) {
    if (page.kind === 'welcome' || page.kind === 'thank_you') continue;

    for (const element of (page.elements ?? []) as SurveyElement[]) {
      if (isContentBlockType(element.type) || isSystemContextField(element)) continue;
      questionNumber += 1;
      map[element.id] = questionNumber;
    }
  }

  return map;
}
