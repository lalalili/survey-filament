<?php

use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;
use Lalalili\SurveyFilament\Filament\Resources\DmsAttempts\DmsAttemptResource;
use Lalalili\SurveyFilament\Support\DmsAttemptPresenter;

function dmsAttempt(SurveyTriggerActionAttemptStatus $status, string $actionType = 'dms_soap'): SurveyTriggerActionAttempt
{
    return SurveyTriggerActionAttempt::create([
        'action_key' => 'preset:1',
        'action_type' => $actionType,
        'mode' => 'automatic',
        'profile' => 'production',
        'status' => $status,
        'ticket_no' => 'CSI20260803000001',
        'endpoint' => 'http://dms.test/ws',
        'request_parameters' => [],
        'request_body' => '',
    ]);
}

it('lists DMS SOAP attempts only', function (): void {
    $configurationError = dmsAttempt(SurveyTriggerActionAttemptStatus::ConfigurationError);
    $success = dmsAttempt(SurveyTriggerActionAttemptStatus::Success);
    dmsAttempt(SurveyTriggerActionAttemptStatus::Success, 'http_post');

    expect(DmsAttemptResource::getEloquentQuery()->pluck('id')->sort()->values()->all())
        ->toBe([$configurationError->id, $success->id]);
});

it('is read only and exposes every attempt status as a filter option', function (): void {
    expect(DmsAttemptResource::canCreate())->toBeFalse()
        ->and(DmsAttemptPresenter::statusOptions())
        ->toHaveKey('configuration_error', '設定錯誤')
        ->toHaveCount(count(SurveyTriggerActionAttemptStatus::cases()));
});

it('marks configuration errors as a danger badge', function (): void {
    expect(DmsAttemptPresenter::statusColor(SurveyTriggerActionAttemptStatus::ConfigurationError))
        ->toBe('danger')
        ->and(DmsAttemptPresenter::modeLabel('automatic'))->toBe('自動觸發')
        ->and(DmsAttemptPresenter::modeLabel('manual_qa'))->toBe('QA 手動測試');
});
