// @vitest-environment jsdom

import { createPinia, setActivePinia } from 'pinia';
import { createApp } from 'vue';
import { describe, expect, it } from 'vitest';
import { usePreviewRuntime } from '../../resources/js/builder/composables/usePreviewRuntime';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';
import fixtureJson from '../Fixtures/preview-calculation-consistency.json';

/**
 * 計算變數的一致性測試（預覽側）。
 *
 * 與 `tests/Feature/PreviewCalculationConsistencyTest.php` 共用同一份 fixture。
 * `expected` 是 PHP 權威路徑（存草稿 → 發布 → 計算）的結果；這裡檢查建立器預覽
 * 透過 `renderCalculationTokens` 算出來的分數是否一致。跟不上的情境在 fixture 裡
 * 以 `preview_divergence` 記下目前實際值並 pin 住。
 */

type FixtureCase = {
  name: string;
  answers: Record<string, string | string[]>;
  expected: Record<string, string>;
  preview_divergence?: { actual: Record<string, string>; why: string };
};

const fixture = fixtureJson as unknown as {
  schema: Record<string, unknown>;
  answer_types: Record<string, string>;
  cases: FixtureCase[];
};

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

/** fixture 的 schema 就是建立器 schema，直接塞進 store。 */
function buildStore() {
  const pinia = createPinia();
  setActivePinia(pinia);
  const store = useSurveyBuilderStore();

  store.schema = structuredClone(fixture.schema) as typeof store.schema;
  store.selectedPageId = 'page_1';

  return store;
}

function elementIdFor(store: ReturnType<typeof buildStore>, fieldKey: string): string {
  const element = store.allElements.find((candidate) => candidate.field_key === fieldKey);

  if (!element) {
    throw new Error(`fixture schema 缺少 field_key=${fieldKey} 的題目`);
  }

  return element.id;
}

/** 依題型把作答寫進對應的預覽狀態。 */
function scoresFor(testCase: FixtureCase): Record<string, string> {
  const store = buildStore();

  return withPreviewRuntime((runtime) => {
    const selections: Record<string, string | Set<string>> = {};

    for (const [fieldKey, value] of Object.entries(testCase.answers)) {
      const id = elementIdFor(store, fieldKey);
      selections[id] = fixture.answer_types[fieldKey] === 'multiple_choice'
        ? new Set((value as string[]).map(String))
        : String(value);
    }

    runtime.previewSelections.value = selections;

    return Object.fromEntries(
      Object.keys(testCase.expected).map((key) => [
        key,
        runtime.renderCalculationTokens(`{{calc.${key}}}`, true),
      ]),
    );
  });
}

describe('計算變數：預覽 vs PHP 權威實作', () => {
  const agreeing = fixture.cases.filter((testCase) => !testCase.preview_divergence);
  const diverging = fixture.cases.filter((testCase) => testCase.preview_divergence);

  it.each(agreeing.map((testCase) => [testCase.name, testCase] as const))(
    '與 PHP 一致：%s',
    (_name, testCase) => {
      expect(scoresFor(testCase)).toEqual(testCase.expected);
    },
  );

  it.each(diverging.map((testCase) => [testCase.name, testCase] as const))(
    '已知落差：%s',
    (_name, testCase) => {
      const divergence = testCase.preview_divergence!;

      expect(
        divergence.actual,
        `「${testCase.name}」已不再是落差，請從 fixture 移除 preview_divergence`,
      ).not.toEqual(testCase.expected);

      expect(scoresFor(testCase), divergence.why).toEqual(divergence.actual);
    },
  );

  it('目前沒有已記錄的落差', () => {
    expect(diverging).toHaveLength(0);
    expect(agreeing).toHaveLength(fixture.cases.length);
  });
});
