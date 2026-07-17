import type { SurveyPage } from '../types/schema';

export function findPreviewThankYouPageId(pages: SurveyPage[], currentPageId: string | null): string | null {
  const currentPageIndex = pages.findIndex((page) => page.id === currentPageId);
  if (currentPageIndex === -1) return null;

  return pages.slice(currentPageIndex + 1).find((page) => page.kind === 'thank_you')?.id ?? null;
}
