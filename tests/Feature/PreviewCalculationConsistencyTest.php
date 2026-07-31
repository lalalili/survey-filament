<?php

use Lalalili\SurveyCore\Actions\CalculateSurveyResponseAction;
use Lalalili\SurveyCore\Actions\PublishSurveyAction;
use Lalalili\SurveyCore\Actions\SaveSurveyDraftSchemaAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;

/**
 * 計算變數的一致性測試（PHP 側）。
 *
 * 與 `tests/Unit/previewCalculationConsistency.test.ts` 共用同一份 fixture。這一側跑
 * 完整的權威路徑（存草稿 → 發布 → 計算），fixture 的 `expected` 即以此為準；TS 那一側
 * 再拿同一份 schema 檢查建立器預覽算出來的分數是否一致。
 */

/**
 * @return array{schema: array<string, mixed>, cases: list<array<string, mixed>>}
 */
function previewCalculationFixture(): array
{
    $path = __DIR__.'/../Fixtures/preview-calculation-consistency.json';
    $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    return ['schema' => $decoded['schema'], 'cases' => $decoded['cases']];
}

it('scores every fixture case as the fixture records', function () {
    $fixture = previewCalculationFixture();

    $survey = Survey::create(['title' => '計算變數一致性', 'status' => SurveyStatus::Published]);
    app(SaveSurveyDraftSchemaAction::class)->execute($survey, $fixture['schema']);
    app(PublishSurveyAction::class)->execute($survey);
    $survey->refresh();

    expect($fixture['cases'])->not->toBeEmpty();

    foreach ($fixture['cases'] as $case) {
        $scores = app(CalculateSurveyResponseAction::class)->execute($survey, $case['answers']);

        $actual = array_map(fn (mixed $value): string => (string) $value, $scores);
        $expected = $case['expected'];
        ksort($actual);
        ksort($expected);

        expect($actual)->toBe($expected, "情境「{$case['name']}」的 PHP 計算與 fixture 不符");
    }
});

it('truncates a fractional score delta to an integer', function () {
    // 這是預覽端目前跟不上的一項，單獨釘住以免日後被改掉。
    $fixture = previewCalculationFixture();

    $survey = Survey::create(['title' => '小數 delta', 'status' => SurveyStatus::Published]);
    app(SaveSurveyDraftSchemaAction::class)->execute($survey, $fixture['schema']);
    app(PublishSurveyAction::class)->execute($survey);

    $scores = app(CalculateSurveyResponseAction::class)->execute($survey->refresh(), ['choice' => 'd']);

    expect($scores['score'])->toBe(2);
});
