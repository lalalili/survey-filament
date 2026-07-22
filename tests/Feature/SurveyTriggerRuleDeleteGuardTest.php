<?php

use Filament\Actions\DeleteAction;
use Filament\Support\Exceptions\Halt;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyTriggerRule;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;

it('allows deleting a disabled trigger rule with scheduling disabled', function (): void {
    $survey = Survey::create(['title' => '未排程問卷', 'status' => SurveyStatus::Draft]);
    $rule = SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '規則',
        'is_active' => false,
        'rule_tree_json' => ['op' => 'AND', 'children' => []],
        'actions_json' => [],
        'schedule_enabled' => false,
    ]);

    SurveyTriggerRuleResource::guardAgainstDeletingScheduledTriggerRule(DeleteAction::make(), $rule);

    expect(true)->toBeTrue();
});

it('halts deletion of an active trigger rule', function (): void {
    $survey = Survey::create(['title' => '啟用中問卷', 'status' => SurveyStatus::Draft]);
    $rule = SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '啟用中規則',
        'is_active' => true,
        'rule_tree_json' => ['op' => 'AND', 'children' => []],
        'actions_json' => [],
        'schedule_enabled' => false,
    ]);

    expect(fn () => SurveyTriggerRuleResource::guardAgainstDeletingScheduledTriggerRule(DeleteAction::make(), $rule))
        ->toThrow(Halt::class);

    expect($rule->fresh())->not->toBeNull();
});

it('halts deletion of a trigger rule with scheduling enabled', function (): void {
    $survey = Survey::create(['title' => '已排程問卷', 'status' => SurveyStatus::Draft]);
    $rule = SurveyTriggerRule::create([
        'survey_id' => $survey->id,
        'name' => '已排程規則',
        'is_active' => false,
        'rule_tree_json' => ['op' => 'AND', 'children' => []],
        'actions_json' => [],
        'schedule_enabled' => true,
        'schedule_time' => '09:00',
    ]);

    expect(fn () => SurveyTriggerRuleResource::guardAgainstDeletingScheduledTriggerRule(DeleteAction::make(), $rule))
        ->toThrow(Halt::class);

    expect($rule->fresh())->not->toBeNull();
});
