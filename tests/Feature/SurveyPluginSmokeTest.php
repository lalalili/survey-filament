<?php

use Filament\Actions\DeleteAction;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\AudienceCore\Models\AudienceListRow;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\SurveyAnalytics;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\CollectorsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\SurveyTriggerAllowedHostResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;
use Lalalili\SurveyFilament\SurveyFilamentPlugin;

it('can instantiate the plugin', function () {
    $plugin = SurveyFilamentPlugin::make();

    expect($plugin)->toBeInstanceOf(SurveyFilamentPlugin::class)
        ->and($plugin->getId())->toBe('survey');
});

it('can create and retrieve a survey model', function () {
    $survey = Survey::create([
        'title' => 'Smoke Test Survey',
        'status' => SurveyStatus::Draft,
    ]);

    expect($survey->id)->toBeInt()
        ->and($survey->title)->toBe('Smoke Test Survey')
        ->and($survey->public_key)->toHaveLength(32);
});

it('survey navigation group reads from config', function () {
    config()->set('survey-filament.navigation_group', 'Custom Group');

    expect(SurveyResource::getNavigationGroup())
        ->toBe('Custom Group');
});

it('shows optional survey table columns by default', function () {
    expect(config('survey-filament.survey_table_hidden_columns'))->toBe([])
        ->and(SurveyResource::isSurveyTableColumnHidden('fields_count'))->toBeFalse()
        ->and(SurveyResource::isSurveyTableColumnHidden('recipients_count'))->toBeFalse();
});

it('can hide optional survey table columns through config', function () {
    config()->set('survey-filament.survey_table_hidden_columns', ['fields_count']);

    expect(SurveyResource::isSurveyTableColumnHidden('fields_count'))->toBeTrue()
        ->and(SurveyResource::isSurveyTableColumnHidden('recipients_count'))->toBeFalse();
});

it('defaults resource overrides to null', function () {
    expect(config('survey-filament.survey_resource_class'))->toBeNull()
        ->and(config('survey-filament.response_resource_class'))->toBeNull();
});

it('shows recipient navigation by default', function () {
    config()->set('survey-filament.recipient_navigation_enabled', true);

    expect(RecipientResource::shouldRegisterNavigation())->toBeTrue();
});

it('can hide recipient navigation through config', function () {
    config()->set('survey-filament.recipient_navigation_enabled', false);

    expect(RecipientResource::shouldRegisterNavigation())->toBeFalse();
});

it('restricts audience list deletion when activity dispatches reference its rows', function () {
    Schema::create('activity_dispatches', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('audience_list_row_id')->nullable();
        $table->timestamps();
    });

    $audienceList = AudienceList::create(['name' => 'Referenced List']);
    $referencedRow = AudienceListRow::create([
        'audience_list_id' => $audienceList->id,
        'data_json' => ['email' => 'referenced@example.com'],
        'status' => 'active',
    ]);

    DB::table('activity_dispatches')->insert([
        [
            'audience_list_row_id' => $referencedRow->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'audience_list_row_id' => $referencedRow->id,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    expect(config('survey-filament.recipient_activity_dispatch_delete_strategy'))->toBe('restrict')
        ->and(RecipientResource::deleteAction())->toBeInstanceOf(DeleteAction::class)
        ->and(RecipientResource::activityDispatchReferencesCount($audienceList))->toBe(2);
});

it('shows trigger rule resources in navigation but hides allowed hosts', function () {
    expect(SurveyTriggerRuleResource::shouldRegisterNavigation())->toBeTrue()
        ->and(SurveyTriggerActionPresetResource::shouldRegisterNavigation())->toBeTrue()
        ->and(SurveyTriggerAllowedHostResource::shouldRegisterNavigation())->toBeFalse();
});

it('registers the survey analytics resource page', function () {
    $pages = SurveyResource::getPages();

    expect($pages)->toHaveKey('analytics')
        ->and(SurveyAnalytics::class)->toBeString();
});

it('registers the collectors relation manager', function () {
    expect(SurveyResource::getRelations())->toContain(CollectorsRelationManager::class);
});
