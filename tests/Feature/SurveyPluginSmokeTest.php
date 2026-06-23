<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\AudienceCore\Models\AudienceListRow;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages\ImportRecipients;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RelationManagers\RowsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;
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
        ->and(SurveyResource::isSurveyTableColumnHidden('category'))->toBeFalse()
        ->and(SurveyResource::isSurveyTableColumnHidden('fields_count'))->toBeFalse()
        ->and(SurveyResource::isSurveyTableColumnHidden('recipients_count'))->toBeFalse();
});

it('accepts invokable query scopes for survey resources', function () {
    config()->set('survey-filament.query_scope', new class
    {
        public function __invoke($query, $user)
        {
            return $query->whereRaw('1 = 1');
        }
    });

    expect(SurveyResource::getEloquentQuery()->toSql())->toContain('1 = 1')
        ->and(RecipientResource::getEloquentQuery()->toSql())->toContain('1 = 1')
        ->and(ResponseResource::getEloquentQuery()->toSql())->toContain('1 = 1');
});

it('accepts invokable response query scopes', function () {
    config()->set('survey-filament.response_query_scope', new class
    {
        public function __invoke($query, $user)
        {
            return $query->whereRaw('2 = 2');
        }
    });

    expect(ResponseResource::getEloquentQuery()->toSql())->toContain('2 = 2');
});

it('hides builder json actions by default', function () {
    expect(config('survey-filament.builder_json_actions_enabled'))->toBeFalse()
        ->and(SurveyResource::builderJsonActionsEnabled())->toBeFalse();
});

it('keeps builder display setting gates disabled by default', function () {
    expect(config('survey-filament.builder_language_setting_enabled'))->toBeFalse()
        ->and(config('survey-filament.builder_thank_you_redirect_enabled'))->toBeFalse()
        ->and(config('survey-filament.builder_accent_color_setting_enabled'))->toBeFalse();
});

it('defaults resource overrides and async response export to null', function () {
    expect(config('survey-filament.survey_resource_class'))->toBeNull()
        ->and(config('survey-filament.response_resource_class'))->toBeNull()
        ->and(config('survey-filament.response_export_action'))->toBeNull();
});

it('shows recipient navigation by default', function () {
    config()->set('survey-filament.recipient_navigation_enabled', true);

    expect(RecipientResource::shouldRegisterNavigation())->toBeTrue();
});

it('can hide recipient navigation through config', function () {
    config()->set('survey-filament.recipient_navigation_enabled', false);

    expect(RecipientResource::shouldRegisterNavigation())->toBeFalse();
});

it('keeps audience list management available through the survey plugin', function () {
    $pages = RecipientResource::getPages();

    expect(RecipientResource::getModel())->toBe(AudienceList::class)
        ->and(RecipientResource::getNavigationLabel())->toBe('名單')
        ->and(RecipientResource::getRelations())->toContain(RowsRelationManager::class)
        ->and($pages)->toHaveKey('index')
        ->and($pages)->toHaveKey('create')
        ->and($pages)->toHaveKey('edit')
        ->and($pages)->toHaveKey('import')
        ->and(ImportRecipients::class)->toBeString();
});

it('detaches activity dispatch references before deleting an audience list', function () {
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

    AudienceListRow::create([
        'audience_list_id' => $audienceList->id,
        'data_json' => ['email' => 'unreferenced@example.com'],
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

    expect(RecipientResource::activityDispatchReferencesCount($audienceList))->toBe(2)
        ->and(RecipientResource::detachActivityDispatchReferences($audienceList))->toBe(2)
        ->and(RecipientResource::activityDispatchReferencesCount($audienceList))->toBe(0)
        ->and(DB::table('activity_dispatches')->count())->toBe(2);
});

it('hides survey trigger resources from navigation', function () {
    expect(SurveyTriggerRuleResource::shouldRegisterNavigation())->toBeFalse()
        ->and(SurveyTriggerActionPresetResource::shouldRegisterNavigation())->toBeFalse()
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
