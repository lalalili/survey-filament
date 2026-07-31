import type { SurveyElement } from '../types/schema';

export const JUMP_SUPPORTED_TYPES = ['single_choice', 'select'] as const;

export function elementSupportsJump(el: SurveyElement): boolean {
  return (JUMP_SUPPORTED_TYPES as readonly string[]).includes(el.type);
}

export function hasActiveJumpLogic(el: SurveyElement): boolean {
  return elementSupportsJump(el) && el.options.some((o) => o.action && o.action.type !== 'next_page');
}

export function elementSupportsLogic(el: SurveyElement): boolean {
  return !isContentBlockType(el.type);
}

export function typeCategory(type: string): string {
  if (['single_choice', 'multiple_choice', 'select', 'cascade_select', 'ranking'].includes(type)) return 'choice';
  if (['rating', 'nps', 'linear_scale'].includes(type)) return 'scale';
  if (['matrix_single', 'matrix_multi'].includes(type)) return 'matrix';
  if (['section_title', 'description_block', 'divider', 'quote_block'].includes(type)) return 'content';
  return 'text';
}

export function isContentBlockType(type: string): boolean {
  return typeCategory(type) === 'content';
}

/** 內容區塊（標題／說明／引言）的顯示文字存在 description 欄位。 */
export function contentBlockText(element: SurveyElement): string {
  return element.description || '';
}

export function textInputType(element: SurveyElement): string {
  const inputFormat = String((element.settings as Record<string, unknown> | undefined)?.input_format ?? '');
  if (element.type === 'email' || inputFormat === 'email') return 'email';
  if (element.type === 'phone' || inputFormat === 'mobile_tw') return 'tel';
  if (element.type === 'date') return 'date';
  if (element.type === 'time') return 'time';
  return 'text';
}

export function ratingShapeIcon(shape: string): string {
  const map: Record<string, string> = { star: '★', heart: '♥', check: '✔', thumb: '👍' };
  return map[shape] ?? '★';
}

/** 去掉浮點誤差尾數的顯示格式（合計、剩餘量等）。 */
export function formatSurveyNumber(value: number): string {
  return Number.isInteger(value) ? String(value) : value.toFixed(2).replace(/\.?0+$/, '');
}
