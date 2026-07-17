import { describe, expect, it } from 'vitest';
import { useQuestionCollapse } from '../../resources/js/builder/utils/questionCollapse';

describe('question card collapse state', () => {
  it('toggles each question independently', () => {
    const collapse = useQuestionCollapse();

    collapse.toggle('question-1');

    expect(collapse.isCollapsed('question-1')).toBe(true);
    expect(collapse.isCollapsed('question-2')).toBe(false);

    collapse.toggle('question-1');

    expect(collapse.isCollapsed('question-1')).toBe(false);
  });

  it('removes collapse state when a question is deleted', () => {
    const collapse = useQuestionCollapse();

    collapse.toggle('question-1');
    collapse.remove('question-1');

    expect(collapse.isCollapsed('question-1')).toBe(false);
  });
});
