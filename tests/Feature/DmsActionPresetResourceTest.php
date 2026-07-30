<?php

use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages\EditSurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\RelationManagers\DmsAttemptsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;
use Lalalili\SurveyFilament\Support\DmsAttemptPresenter;
use Lalalili\SurveyFilament\Tests\Fixtures\User;
use Livewire\Component;

function dmsPresetSchema(): Schema
{
    $host = new class extends Component implements HasSchemas
    {
        use InteractsWithSchemas;
    };

    return SurveyTriggerActionPresetResource::form(Schema::make($host));
}

/**
 * @param  array<string, string>  $confirmations
 */
function dmsActivationValidator(Schema $schema, array $confirmations, string $profile = 'qa'): Illuminate\Contracts\Validation\Validator
{
    $schema->fill([
        'is_active' => true,
        'action_json' => [
            'type' => 'dms_soap',
            'profile' => $profile,
            'parameter_confirmations' => $confirmations,
        ],
    ]);
    $toggle = $schema->getFlatFields()['is_active'];

    return Validator::make(
        ['is_active' => true],
        ['is_active' => $toggle->getValidationRules()],
    );
}

function dmsQaActionVisible(SurveyTriggerActionPreset $preset): bool
{
    $page = new EditSurveyTriggerActionPreset;
    $page->record = $preset;
    $method = new ReflectionMethod($page, 'getHeaderActions');
    $method->setAccessible(true);
    $action = collect($method->invoke($page))
        ->first(fn ($action): bool => $action->getName() === 'testDmsQa');

    expect($action)->not->toBeNull();

    return $action->isVisible();
}

it('shows structured DMS fields without exposing a raw XML or secret key field', function (): void {
    $schema = dmsPresetSchema();
    $schema->fill(['action_json' => ['type' => 'dms_soap']]);
    $section = $schema->getComponent('dms_soap_settings', withHidden: true);
    $fields = array_keys($section->getChildSchema()->getFlatFields(withHidden: true));

    expect($fields)
        ->toContain(
            'action_json.profile',
            'action_json.ticket_type_id',
            'action_json.open_department_code',
            'action_json.open_method_id',
            'action_json.category_path',
            'action_json.employee_code',
            'action_json.open_question_keys.CSI',
            'action_json.open_question_keys.SSI',
            'action_json.open_question_keys.IQS',
            'action_json.description_templates.CSI',
            'action_json.description_templates.SSI',
            'action_json.description_templates.IQS',
            'action_json.gender_mapping',
            'action_json.empty_response_is_success',
            'action_json.parameter_confirmations.wsdl_contract',
        )
        ->not->toContain('action_json.raw_xml', 'action_json.sKey', 'action_json.key');
});

it('allows inactive DMS drafts but blocks activation until every item is confirmed on production', function (): void {
    $schema = dmsPresetSchema();
    $pending = collect([
        'ticket_type_id',
        'open_department_code',
        'open_method_id',
        'category_path',
        'employee_code_source',
        'description_format',
        'gender_mapping',
        'ticket_number_strategy',
        'response_semantics',
        'wsdl_contract',
    ])->mapWithKeys(fn (string $key): array => [$key => 'pending'])->all();
    $confirmed = array_fill_keys(array_keys($pending), 'confirmed');

    expect(dmsActivationValidator($schema, $pending)->fails())->toBeTrue()
        ->and(dmsActivationValidator($schema, $confirmed, 'qa')->fails())->toBeTrue()
        ->and(dmsActivationValidator($schema, $confirmed, 'production')->passes())->toBeTrue();

    $draft = SurveyTriggerActionPreset::create([
        'key' => 'dms-draft',
        'name' => 'DMS 草稿',
        'action_json' => [
            'type' => 'dms_soap',
            'profile' => 'qa',
            'parameter_confirmations' => $pending,
        ],
        'is_active' => false,
    ]);

    expect($draft->fresh()->is_active)->toBeFalse();
});

it('shows the manual QA action only when config profile and policy all allow it', function (): void {
    $user = User::create([
        'name' => 'DMS Tester',
        'email' => 'dms-tester@example.com',
        'password' => 'password',
    ]);
    $this->actingAs($user);
    $preset = SurveyTriggerActionPreset::create([
        'key' => 'dms-qa',
        'name' => 'DMS QA',
        'action_json' => ['type' => 'dms_soap', 'profile' => 'qa'],
        'is_active' => false,
    ]);

    config()->set('survey-core.triggers.dms.manual_test_enabled', true);
    Gate::define('testDms', fn (User $user, SurveyTriggerActionPreset $preset): bool => true);
    expect(dmsQaActionVisible($preset))->toBeTrue();

    config()->set('survey-core.triggers.dms.manual_test_enabled', false);
    expect(dmsQaActionVisible($preset))->toBeFalse();

    config()->set('survey-core.triggers.dms.manual_test_enabled', true);
    config()->set('survey-core.triggers.dms.profile', 'production');
    expect(dmsQaActionVisible($preset))->toBeFalse();

    config()->set('survey-core.triggers.dms.profile', 'qa');
    $preset->update(['action_json' => ['type' => 'dms_soap', 'profile' => 'production']]);
    expect(dmsQaActionVisible($preset->fresh()))->toBeFalse();
});

it('registers attempt history and always redacts DMS secrets from copied debug information', function (): void {
    expect(SurveyTriggerActionPresetResource::getRelations())->toContain(DmsAttemptsRelationManager::class);

    $attempt = new SurveyTriggerActionAttempt([
        'action_key' => 'preset:1',
        'action_type' => 'dms_soap',
        'mode' => 'manual_qa',
        'profile' => 'qa',
        'status' => SurveyTriggerActionAttemptStatus::HttpError,
        'ticket_no' => 'QA-1',
        'endpoint' => 'https://qa.example.test',
        'request_parameters' => ['ticketno' => 'QA-1', 'sKey' => 'secret-parameter'],
        'request_body' => '<sKey>secret-request</sKey>',
        'response_headers' => ['Authorization' => ['Bearer secret-header']],
        'response_body' => '<response><sKey>secret-response</sKey></response>',
        'parsed_response' => ['key' => 'secret-parsed'],
        'error' => 'HTTP 500 sKey=query-secret&mode=qa {"sKey":"json-secret"}',
    ]);

    $debug = DmsAttemptPresenter::debugInformation($attempt);

    expect($debug)
        ->toContain('[REDACTED]', 'HTTP 500', 'QA-1')
        ->not->toContain(
            'secret-parameter',
            'secret-request',
            'secret-header',
            'secret-response',
            'secret-parsed',
            'query-secret',
            'json-secret',
        );
});
