<?php

use Lalalili\SurveyCore\Support\ConditionGroupEvaluator;

/**
 * 顯示條件求值的一致性測試（PHP 側）。
 *
 * 與 `tests/Unit/previewConditionConsistency.test.ts` 共用同一份 fixture。
 * 這一側斷言權威實作 `ConditionGroupEvaluator` 的行為，`expected` 欄位即以此為準；
 * TS 那一側再拿同一份 fixture 檢查建立器預覽是否跟得上。
 */

/**
 * @return array{answers: array<string, mixed>, cases: list<array<string, mixed>>}
 */
function previewConditionFixture(): array
{
    $path = __DIR__.'/../Fixtures/preview-condition-consistency.json';
    $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    $answers = [];
    foreach ($decoded['answers'] as $fieldKey => $definition) {
        $answers[$fieldKey] = $definition['value'];
    }

    return ['answers' => $answers, 'cases' => $decoded['cases']];
}

it('has a fixture that exercises every operator the evaluator supports', function () {
    $fixture = previewConditionFixture();

    $covered = [];
    foreach ($fixture['cases'] as $case) {
        foreach ($case['group']['conditions'] ?? [] as $node) {
            $leaves = isset($node['conditions']) ? $node['conditions'] : [$node];

            foreach ($leaves as $leaf) {
                $covered[] = $leaf['op'] ?? 'equals';
            }
        }
    }

    expect(array_values(array_unique($covered)))->toContain(
        'equals', 'not_equals', 'contains', 'not_contains',
        'is_empty', 'is_not_empty', 'greater_than', 'greater_than_or_equal', 'between',
    );
});

it('evaluates every fixture case as the fixture records', function () {
    $fixture = previewConditionFixture();

    expect($fixture['cases'])->not->toBeEmpty();

    foreach ($fixture['cases'] as $case) {
        expect(ConditionGroupEvaluator::passes($case['group'], $fixture['answers']))
            ->toBe($case['expected'], "案例「{$case['name']}」的 PHP 求值與 fixture 不符");
    }
});

it('treats an unanswered target as failing every operator except the emptiness checks', function () {
    $answers = ['target' => null];

    $group = fn (string $op, mixed $value = 'x'): array => [
        'logic' => 'and',
        'conditions' => [['field_key' => 'target', 'op' => $op, 'value' => $value]],
    ];

    // 這條守衛是預覽端目前沒有的，單獨釘住以免日後被改掉。
    expect(ConditionGroupEvaluator::passes($group('not_equals'), $answers))->toBeFalse();
    expect(ConditionGroupEvaluator::passes($group('not_contains'), $answers))->toBeFalse();
    expect(ConditionGroupEvaluator::passes($group('greater_than', -1), $answers))->toBeFalse();
    expect(ConditionGroupEvaluator::passes($group('is_empty'), $answers))->toBeTrue();
    expect(ConditionGroupEvaluator::passes($group('is_not_empty'), $answers))->toBeFalse();
});

it('does not treat zero as an unanswered value', function () {
    $answers = ['score' => 0];

    $group = [
        'logic' => 'and',
        'conditions' => [['field_key' => 'score', 'op' => 'greater_than', 'value' => -1]],
    ];

    expect(ConditionGroupEvaluator::passes($group, $answers))->toBeTrue();
});
