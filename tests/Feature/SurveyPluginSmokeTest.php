<?php

use Filament\Actions\DeleteAction;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema as FilamentSchema;
use Illuminate\Contracts\Auth\Authenticatable;
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
use Livewire\Component;

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

it('does not expose a separate personalization required toggle', function () {
    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $fields = SurveyResource::form(FilamentSchema::make($livewire))->getFlatFields();

    expect($fields)->toHaveKey('settings_json.personalization.audience_list_id')
        ->and($fields)->not->toHaveKey('settings_json.personalization.required');
});

it('configures audience list schema profiles', function () {
    config()->set('survey-filament.audience_schema_profile_options', [
        'CSI' => '售後服務滿意度',
        'SSI' => '銷售滿意度',
    ]);

    $livewire = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    $fields = RecipientResource::form(FilamentSchema::make($livewire))->getFlatFields();

    expect($fields)->toHaveKey('schema_profile')
        ->and(RecipientResource::schemaProfileOptions())->toBe([
            'CSI' => '售後服務滿意度',
            'SSI' => '銷售滿意度',
        ]);
});

it('defaults resource overrides to null', function () {
    expect(config('survey-filament.survey_resource_class'))->toBeNull()
        ->and(config('survey-filament.response_resource_class'))->toBeNull();
});

it('shows recipient navigation by default', function () {
    config()->set('survey-filament.recipient_navigation_enabled', true);
    config()->set('survey-filament.recipient_navigation_super_admin_only', false);

    expect(RecipientResource::shouldRegisterNavigation())->toBeTrue();
});

it('can limit recipient navigation to super admins through config', function () {
    config()->set('survey-filament.recipient_navigation_enabled', true);
    config()->set('survey-filament.recipient_navigation_super_admin_only', true);
    auth()->setUser(packageNavigationTestUser(isSuperAdmin: true));

    expect(RecipientResource::shouldRegisterNavigation())->toBeTrue();

    auth()->setUser(packageNavigationTestUser(isSuperAdmin: false));

    expect(RecipientResource::shouldRegisterNavigation())->toBeFalse();

    auth()->forgetGuards();
});

it('can hide recipient navigation through config', function () {
    config()->set('survey-filament.recipient_navigation_enabled', false);

    expect(RecipientResource::shouldRegisterNavigation())->toBeFalse();
});

it('can hide trigger action preset navigation through config', function () {
    config()->set('survey-filament.trigger_action_preset_navigation_enabled', true);

    expect(SurveyTriggerActionPresetResource::shouldRegisterNavigation())->toBeTrue();

    config()->set('survey-filament.trigger_action_preset_navigation_enabled', false);

    expect(SurveyTriggerActionPresetResource::shouldRegisterNavigation())->toBeFalse();
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

function packageNavigationTestUser(bool $isSuperAdmin): Authenticatable
{
    return new class($isSuperAdmin) implements Authenticatable
    {
        public function __construct(public bool $is_super_admin) {}

        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): int
        {
            return 1;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };
}
