import { describe, expect, it } from 'vitest';
import type { SurveyPage } from '../../resources/js/builder/types/schema';
import { findPreviewThankYouPageId } from '../../resources/js/builder/utils/previewSubmission';

function page(id: string, kind: SurveyPage['kind']): SurveyPage {
  return { id, kind, title: id, elements: [] };
}

describe('preview submission', () => {
  it('finds the thank-you page after the current question page', () => {
    const pages = [page('welcome', 'welcome'), page('question', 'question'), page('thanks', 'thank_you')];

    expect(findPreviewThankYouPageId(pages, 'question')).toBe('thanks');
  });

  it('returns null when the survey has no thank-you page', () => {
    const pages = [page('question', 'question')];

    expect(findPreviewThankYouPageId(pages, 'question')).toBeNull();
  });
});
