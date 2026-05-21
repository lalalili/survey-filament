import type { SurveyElement } from '../types/schema';

export const JUMP_SUPPORTED_TYPES = ['single_choice', 'select'] as const;

export function elementSupportsJump(el: SurveyElement): boolean {
  return (JUMP_SUPPORTED_TYPES as readonly string[]).includes(el.type);
}

export function hasActiveJumpLogic(el: SurveyElement): boolean {
  return elementSupportsJump(el) && el.options.some((o) => o.action && o.action.type !== 'next_page');
}

export function typeCategory(type: string): string {
  if (['single_choice', 'multiple_choice', 'select', 'cascade_select', 'ranking'].includes(type)) return 'choice';
  if (['rating', 'nps', 'linear_scale'].includes(type)) return 'scale';
  if (['matrix_single', 'matrix_multi'].includes(type)) return 'matrix';
  if (['section_title', 'description_block', 'divider', 'quote_block'].includes(type)) return 'content';
  return 'text';
}
