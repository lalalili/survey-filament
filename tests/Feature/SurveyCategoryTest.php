<?php

use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

it('lists distinct non-empty categories sorted', function () {
    Survey::create(['title' => 'A', 'status' => SurveyStatus::Draft, 'category' => 'SSI']);
    Survey::create(['title' => 'B', 'status' => SurveyStatus::Draft, 'category' => 'CSI']);
    Survey::create(['title' => 'C', 'status' => SurveyStatus::Draft, 'category' => 'CSI']);
    Survey::create(['title' => 'D', 'status' => SurveyStatus::Draft, 'category' => null]);

    expect(SurveyResource::existingCategories())->toBe(['CSI' => 'CSI', 'SSI' => 'SSI']);
});

it('persists a survey category', function () {
    $survey = Survey::create(['title' => 'Cat', 'status' => SurveyStatus::Draft, 'category' => 'IQS']);

    expect($survey->fresh()->category)->toBe('IQS');
});
