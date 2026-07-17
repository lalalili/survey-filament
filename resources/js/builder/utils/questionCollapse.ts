import { reactive } from 'vue';

export function useQuestionCollapse() {
  const collapsedQuestionIds = reactive(new Set<string>());

  return {
    isCollapsed(questionId: string): boolean {
      return collapsedQuestionIds.has(questionId);
    },
    toggle(questionId: string): void {
      if (collapsedQuestionIds.has(questionId)) {
        collapsedQuestionIds.delete(questionId);
      } else {
        collapsedQuestionIds.add(questionId);
      }
    },
    remove(questionId: string): void {
      collapsedQuestionIds.delete(questionId);
    },
  };
}
