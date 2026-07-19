<?php

use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Enums\SurveyResponseQualityStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyFilament\Filament\Widgets\PublishedSurveysWidget;
use Lalalili\SurveyFilament\Filament\Widgets\TotalResponsesWidget;
use Lalalili\SurveyFilament\Filament\Widgets\TotalSurveysWidget;
use Lalalili\SurveyFilament\Support\SurveyQueryScopes;

/** @return array{0: Survey, 1: Survey} */
function makeScopedSurveys(): array
{
    $visible = Survey::create(['title' => '可見問卷', 'status' => SurveyStatus::Published]);
    $hidden = Survey::create(['title' => '隱藏問卷', 'status' => SurveyStatus::Published]);

    config([
        'survey-filament.query_scope' => fn ($query, $user) => $query->where('title', 'like', '可見%'),
    ]);

    return [$visible, $hidden];
}

function widgetStatValue(object $widget): mixed
{
    $method = new ReflectionMethod($widget, 'getStats');

    return $method->invoke($widget)[0]->getValue();
}

afterEach(function (): void {
    config(['survey-filament.query_scope' => null, 'survey-filament.response_query_scope' => null]);
});

it('applies the query scope to survey queries', function (): void {
    [$visible] = makeScopedSurveys();

    expect(SurveyQueryScopes::surveys(Survey::query())->pluck('id')->all())
        ->toBe([$visible->id]);
});

it('applies the query scope to response queries through the survey relationship', function (): void {
    [$visible, $hidden] = makeScopedSurveys();

    $visibleResponse = SurveyResponse::create(['survey_id' => $visible->id, 'submitted_at' => now(), 'completion_status' => 'complete']);
    SurveyResponse::create(['survey_id' => $hidden->id, 'submitted_at' => now(), 'completion_status' => 'complete']);

    expect(SurveyQueryScopes::responses(SurveyResponse::query())->pluck('id')->all())
        ->toBe([$visibleResponse->id]);
});

it('applies the response query scope on top of the survey scope', function (): void {
    [$visible] = makeScopedSurveys();

    SurveyResponse::create(['survey_id' => $visible->id, 'submitted_at' => now(), 'completion_status' => 'complete']);
    $testResponse = SurveyResponse::create(['survey_id' => $visible->id, 'submitted_at' => now(), 'completion_status' => 'complete', 'is_test' => true]);

    config([
        'survey-filament.response_query_scope' => fn ($query, $user) => $query->where('is_test', true),
    ]);

    expect(SurveyQueryScopes::responses(SurveyResponse::query())->pluck('id')->all())
        ->toBe([$testResponse->id]);
});

it('limits widget stats to the tenant scope', function (): void {
    [$visible, $hidden] = makeScopedSurveys();

    SurveyResponse::create(['survey_id' => $visible->id, 'submitted_at' => now(), 'completion_status' => 'complete']);
    SurveyResponse::create(['survey_id' => $hidden->id, 'submitted_at' => now(), 'completion_status' => 'complete']);

    expect(widgetStatValue(new TotalSurveysWidget))->toBe(1)
        ->and(widgetStatValue(new PublishedSurveysWidget))->toBe(1)
        ->and(widgetStatValue(new TotalResponsesWidget))->toBe(1);
});

it('only counts accepted formal submitted responses in response widgets', function (): void {
    $survey = Survey::create(['title' => '報表問卷', 'status' => SurveyStatus::Published]);

    SurveyResponse::create(['survey_id' => $survey->id, 'submitted_at' => now(), 'completion_status' => 'complete']);
    SurveyResponse::create(['survey_id' => $survey->id, 'submitted_at' => now(), 'completion_status' => 'complete', 'is_test' => true]);
    SurveyResponse::create(['survey_id' => $survey->id, 'submitted_at' => now(), 'completion_status' => 'complete', 'quality_status' => SurveyResponseQualityStatus::Flagged]);
    SurveyResponse::create(['survey_id' => $survey->id, 'submitted_at' => now(), 'completion_status' => 'complete', 'quality_status' => SurveyResponseQualityStatus::Quarantined]);
    SurveyResponse::create(['survey_id' => $survey->id, 'completion_status' => 'partial']);

    expect(widgetStatValue(new TotalResponsesWidget))->toBe(1);
});

it('keeps widgets unscoped when no query scope is configured', function (): void {
    Survey::create(['title' => 'A', 'status' => SurveyStatus::Published]);
    Survey::create(['title' => 'B', 'status' => SurveyStatus::Draft]);

    expect(widgetStatValue(new TotalSurveysWidget))->toBe(2)
        ->and(widgetStatValue(new PublishedSurveysWidget))->toBe(1);
});
