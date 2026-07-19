<?php

use Filament\Actions\DeleteAction;
use Filament\Support\Exceptions\Halt;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

it('allows deleting a draft survey with no trigger rules', function (): void {
    $survey = Survey::create(['title' => '草稿問卷', 'status' => SurveyStatus::Draft]);

    SurveyResource::guardAgainstDeletingActiveSurvey(DeleteAction::make(), $survey);

    expect(true)->toBeTrue();
});

it('halts deletion of a published survey', function (): void {
    $survey = Survey::create(['title' => '已發佈問卷', 'status' => SurveyStatus::Published]);

    expect(fn () => SurveyResource::guardAgainstDeletingActiveSurvey(DeleteAction::make(), $survey))
        ->toThrow(Halt::class);
});

it('halts deletion of a survey with trigger rules', function (): void {
    $survey = Survey::create(['title' => '有發送設定的問卷', 'status' => SurveyStatus::Draft]);
    SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '規則',
        'is_active' => true,
        'rule_tree_json' => ['op' => 'AND', 'children' => []],
        'actions_json' => [],
    ]);

    expect(fn () => SurveyResource::guardAgainstDeletingActiveSurvey(DeleteAction::make(), $survey))
        ->toThrow(Halt::class);
});

it('hides the delete action for published surveys', function (): void {
    $published = Survey::create(['title' => '已發佈', 'status' => SurveyStatus::Published]);
    $draft = Survey::create(['title' => '草稿', 'status' => SurveyStatus::Draft]);

    expect(SurveyResource::deleteAction()->record($published)->isVisible())->toBeFalse()
        ->and(SurveyResource::deleteAction()->record($draft)->isVisible())->toBeTrue();
});
