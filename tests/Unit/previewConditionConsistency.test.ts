// @vitest-environment jsdom

import { createPinia, setActivePinia } from 'pinia';
import { createApp } from 'vue';
import { describe, expect, it } from 'vitest';
import { usePreviewRuntime } from '../../resources/js/builder/composables/usePreviewRuntime';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';
import fixtureJson from '../Fixtures/preview-condition-consistency.json';

/**
 * 顯示條件求值的一致性測試（預覽側）。
 *
 * 與 `tests/Feature/PreviewConditionConsistencyTest.php` 共用同一份 fixture。
 * `expected` 是 PHP 權威實作的結果；預覽只要跟得上就直接比對 expected，
 * 跟不上的案例在 fixture 裡以 `preview_divergence` 記錄目前的實際回傳值——
 * 那些是已知落差，pin 住以免無聲擴大。修好預覽後這裡會失敗，屆時移除該標記即可。
 */

type FixtureAnswer = { type: string; value: unknown };

type FixtureCase = {
  name: string;
  group: Record<string, unknown>;
  expected: boolean;
  preview_divergence?: { actual: boolean; why: string };
};

const fixture = fixtureJson as unknown as {
  answers: Record<string, FixtureAnswer>;
  cases: FixtureCase[];
};

/** 在元件 setup 情境中執行 composable，讓 onBeforeUnmount / watch 能正常註冊。 */
function withPreviewRuntime<T>(run: (runtime: ReturnType<typeof usePreviewRuntime>) => T): T {
  let result!: T;

  const app = createApp({
    setup() {
      result = run(usePreviewRuntime());

      return () => null;
    },
  });
  app.mount(document.createElement('div'));
  app.unmount();

  return result;
}

function elementIdFor(fieldKey: string): string {
  return `el-${fieldKey}`;
}

function buildStore() {
  const pinia = createPinia();
  setActivePinia(pinia);
  const store = useSurveyBuilderStore();

  store.schema = {
    title: '一致性測試問卷',
    pages: [{
      id: 'page-1',
      kind: 'question',
      title: '第一頁',
      elements: Object.entries(fixture.answers).map(([fieldKey, answer]) => ({
        id: elementIdFor(fieldKey),
        type: answer.type,
        field_key: fieldKey,
        label: fieldKey,
        description: '',
        required: false,
        options: Array.isArray(answer.value)
          ? answer.value.map((value, index) => ({ id: `${fieldKey}-${index}`, label: String(value), value: String(value) }))
          : [{ id: `${fieldKey}-0`, label: String(answer.value ?? ''), value: String(answer.value ?? '') }],
        settings: {},
      })),
    }],
  } as typeof store.schema;
  store.selectedPageId = 'page-1';

  return store;
}

/** 依題型把 fixture 的答案寫進對應的預覽狀態，模擬使用者已作答。 */
function applyAnswers(runtime: ReturnType<typeof usePreviewRuntime>) {
  const selections: Record<string, string | Set<string>> = {};
  const textValues: Record<string, string> = {};

  for (const [fieldKey, answer] of Object.entries(fixture.answers)) {
    const id = elementIdFor(fieldKey);

    if (answer.value === null) continue;

    if (answer.type === 'multiple_choice') {
      selections[id] = new Set((answer.value as unknown[]).map(String));
    } else if (answer.type === 'single_choice' || answer.type === 'select') {
      selections[id] = String(answer.value);
    } else {
      textValues[id] = String(answer.value);
    }
  }

  runtime.previewSelections.value = selections;
  runtime.previewTextValues.value = textValues;
}

function evaluate(group: Record<string, unknown>): boolean {
  buildStore();

  return withPreviewRuntime((runtime) => {
    applyAnswers(runtime);

    return runtime.previewElementVisible({
      id: 'element-under-test',
      type: 'short_text',
      field_key: 'element_under_test',
      label: '受測題目',
      description: '',
      required: false,
      options: [],
      settings: {},
      show_if: group,
    } as never);
  });
}

describe('顯示條件求值：預覽 vs PHP 權威實作', () => {
  const agreeing = fixture.cases.filter((testCase) => !testCase.preview_divergence);
  const diverging = fixture.cases.filter((testCase) => testCase.preview_divergence);

  it.each(agreeing.map((testCase) => [testCase.name, testCase] as const))(
    '與 PHP 一致：%s',
    (_name, testCase) => {
      expect(evaluate(testCase.group)).toBe(testCase.expected);
    },
  );

  it.each(diverging.map((testCase) => [testCase.name, testCase] as const))(
    '已知落差：%s',
    (_name, testCase) => {
      const divergence = testCase.preview_divergence!;

      // 先確認這真的還是落差——若兩者已一致，代表預覽被修好了，該移除標記。
      expect(
        divergence.actual,
        `「${testCase.name}」已不再是落差，請從 fixture 移除 preview_divergence`,
      ).not.toBe(testCase.expected);

      expect(evaluate(testCase.group), divergence.why).toBe(divergence.actual);
    },
  );

  it('把落差控制在已記錄的範圍內', () => {
    expect(diverging).toHaveLength(6);
    expect(agreeing.length).toBeGreaterThan(diverging.length);
  });
});

/**
 * 跳題側的已知落差。PHP 權威實作是 `JumpLogicResolver`，但它吃 Eloquent 模型，
 * 要做真正的雙向比對得先備 DB fixture；在那之前先把預覽這一側的現況釘住，
 * 讓落差留在測試裡而不是只留在某次對話裡。
 */
describe('跳題：預覽相對 JumpLogicResolver 的已知落差', () => {
  function actionAppliedFor(elementType: 'single_choice' | 'select'): string | null {
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();

    store.schema = {
      title: '跳題問卷',
      pages: [
        {
          id: 'page-1',
          kind: 'question',
          title: '第一頁',
          elements: [{
            id: 'jump-q',
            type: elementType,
            field_key: 'route',
            label: '路線',
            description: '',
            required: false,
            options: [{
              id: 'o1',
              label: '跳到第三頁',
              value: 'skip',
              action: { type: 'go_to_page', target_page_id: 'page-3' },
            }],
            settings: {},
          }],
        },
        { id: 'page-2', kind: 'question', title: '第二頁', elements: [] },
        { id: 'page-3', kind: 'question', title: '第三頁', elements: [] },
      ],
    } as typeof store.schema;
    store.selectedPageId = 'page-1';

    return withPreviewRuntime((runtime) => {
      runtime.previewSelections.value = { 'jump-q': 'skip' };
      runtime.previewGoNext();

      return store.selectedPageId;
    });
  }

  it('honours an option jump on single_choice', () => {
    expect(actionAppliedFor('single_choice')).toBe('page-3');
  });

  it('已知落差：select 題的選項跳題在預覽中不生效', () => {
    // builderHelpers.JUMP_SUPPORTED_TYPES 與 PHP 的 JumpLogicResolver 都含 select，
    // 右側面板也讓使用者替 select 設定跳題；但 previewSelectedPageAction 只掃
    // single_choice，於是預覽照順序走到下一頁。修好後這裡會變成 'page-3'。
    expect(actionAppliedFor('select')).toBe('page-2');
  });

  it('已知落差：預覽完全沒有實作頁面層的 jump_rules', () => {
    const pinia = createPinia();
    setActivePinia(pinia);
    const store = useSurveyBuilderStore();

    store.schema = {
      title: '頁面跳題問卷',
      pages: [
        {
          id: 'page-1',
          kind: 'question',
          title: '第一頁',
          settings: {
            jump_rules: [{
              condition: { logic: 'and', conditions: [{ field_key: 'route', op: 'equals', value: 'skip' }] },
              action: { type: 'go_to_page', target_page_id: 'page-3' },
            }],
          },
          elements: [{
            id: 'jump-q',
            type: 'short_text',
            field_key: 'route',
            label: '路線',
            description: '',
            required: false,
            options: [],
            settings: {},
          }],
        },
        { id: 'page-2', kind: 'question', title: '第二頁', elements: [] },
        { id: 'page-3', kind: 'question', title: '第三頁', elements: [] },
      ],
    } as typeof store.schema;
    store.selectedPageId = 'page-1';

    const landed = withPreviewRuntime((runtime) => {
      runtime.previewTextValues.value = { 'jump-q': 'skip' };
      runtime.previewGoNext();

      return store.selectedPageId;
    });

    // PHP 的 JumpLogicResolver 會依 jump_rules 跳到 page-3；預覽照順序走。
    expect(landed).toBe('page-2');
  });
});
