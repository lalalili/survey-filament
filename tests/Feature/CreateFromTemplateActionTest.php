<?php

use Lalalili\SurveyCore\Actions\CreateSurveyFromBuilderTemplateAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Support\SurveyBuilderTemplateRegistry;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ListSurveys;

/**
 * @return array<string, string>
 */
function listSurveysTemplateOptions(): array
{
    $page = new ListSurveys;
    $method = (new ReflectionClass($page))->getMethod('templateOptions');
    $method->setAccessible(true);

    /** @var array<string, string> $options */
    $options = $method->invoke($page);

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

it('creates a draft survey with questions for every offered template option', function (string $slug): void {
    $survey = app(CreateSurveyFromBuilderTemplateAction::class)->execute($slug);

    expect($survey->exists)->toBeTrue()
        ->and($survey->status)->toBe(SurveyStatus::Draft)
        ->and($survey->title)->not->toBeEmpty()
        ->and($survey->fields()->count())->toBeGreaterThan(0);
})->with(fn (): array => array_keys(listSurveysTemplateOptions()));
