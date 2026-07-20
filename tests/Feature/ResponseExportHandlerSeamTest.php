<?php

use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;

afterEach(function (): void {
    config(['survey-filament.response_export_action' => null]);
    unset(app()['survey-filament.response_export_action']);
});

it('returns null when no async export handler is bound or configured', function (): void {
    expect(ResponseResource::resolveResponseExportHandler())->toBeNull();
});

it('falls back to the config handler when no container binding is present', function (): void {
    $called = false;

    config(['survey-filament.response_export_action' => function (Survey $survey, $records) use (&$called): void {
        $called = true;
    }]);

    $handler = ResponseResource::resolveResponseExportHandler();

    expect($handler)->not->toBeNull();

    $handler(Survey::create(['title' => '設定檔覆寫']), collect());

    expect($called)->toBeTrue();
});

it('prefers the container binding over the config handler', function (): void {
    $configCalled = false;
    $boundCalled = false;

    config(['survey-filament.response_export_action' => function (Survey $survey, $records) use (&$configCalled): void {
        $configCalled = true;
    }]);

    app()->bind('survey-filament.response_export_action', function () use (&$boundCalled) {
        return function (Survey $survey, $records) use (&$boundCalled): void {
            $boundCalled = true;
        };
    });

    $handler = ResponseResource::resolveResponseExportHandler();
    $handler(Survey::create(['title' => '容器綁定優先']), collect());

    expect($boundCalled)->toBeTrue()
        ->and($configCalled)->toBeFalse();
});
