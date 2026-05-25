<?php

use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\CollectorsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\SurveyAnalytics;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\SurveyFilamentPlugin;

it('can instantiate the plugin', function () {
    $plugin = SurveyFilamentPlugin::make();

    expect($plugin)->toBeInstanceOf(SurveyFilamentPlugin::class)
        ->and($plugin->getId())->toBe('survey');
});

it('can create and retrieve a survey model', function () {
    $survey = Survey::create([
        'title'  => 'Smoke Test Survey',
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

it('shows recipient navigation by default', function () {
    config()->set('survey-filament.recipient_navigation_enabled', true);

    expect(RecipientResource::shouldRegisterNavigation())->toBeTrue();
});

it('can hide recipient navigation through config', function () {
    config()->set('survey-filament.recipient_navigation_enabled', false);

    expect(RecipientResource::shouldRegisterNavigation())->toBeFalse();
});

it('registers the survey analytics resource page', function () {
    $pages = SurveyResource::getPages();

    expect($pages)->toHaveKey('analytics')
        ->and(SurveyAnalytics::class)->toBeString();
});

it('registers the collectors relation manager', function () {
    expect(SurveyResource::getRelations())->toContain(CollectorsRelationManager::class);
});
