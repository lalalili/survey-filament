<?php

use Lalalili\SurveyCore\Actions\CreateSurveyFromBuilderTemplateAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Support\SurveyBuilderTemplateRegistry;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Actions\CreateSurveyFromTemplateHeaderAction;

/**
 * @return array<string, string>
 */
function listSurveysTemplateOptions(): array
{
    $method = (new ReflectionClass(CreateSurveyFromTemplateHeaderAction::class))->getMethod('templateOptions');
    $method->setAccessible(true);

    /** @var array<string, string> $options */
    $options = $method->invoke(null);

    return $options;
}

it('offers every registry template as a select option keyed by slug', function (): void {
    $options = listSurveysTemplateOptions();
    $registry = app(SurveyBuilderTemplateRegistry::class)->all();

    expect(array_keys($options))->toBe(array_keys($registry));

    foreach ($registry as $slug => $template) {
        expect($options[$slug])->toContain($template['name'])
            ->and($options[$slug])->toContain($template['category']);
    }
});

/**
 * 草稿的題目只存在 draft_schema：survey_fields 要到發佈時才由
 * SyncSurveyBuilderSchemaToFieldsAction 同步寫入。
 */
function draftQuestionCount(array $draftSchema): int
{
    return collect($draftSchema['pages'] ?? [])
        ->sum(fn (array $page): int => count($page['elements'] ?? []));
}

it('creates a draft survey with questions for every offered template option', function (string $slug): void {
    $survey = app(CreateSurveyFromBuilderTemplateAction::class)->execute($slug);

    expect($survey->exists)->toBeTrue()
        ->and($survey->status)->toBe(SurveyStatus::Draft)
        ->and($survey->title)->not->toBeEmpty()
        ->and(draftQuestionCount($survey->draft_schema ?? []))->toBeGreaterThan(0);
})->with(fn (): array => array_keys(listSurveysTemplateOptions()));
