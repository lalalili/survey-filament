<?php

use Illuminate\Support\Facades\Route;
use Lalalili\SurveyCore\Http\Controllers\SurveyBuilderController;
use Lalalili\SurveyCore\Models\Survey;

Route::middleware(['web', 'auth', 'verified'])
    ->prefix('admin/surveys')
    ->name('survey-filament.builder.')
    ->group(function () {
        Route::get('/{survey}/builder-data', [SurveyBuilderController::class, 'show'])
            ->name('show');

        Route::put('/{survey}/builder-schema', [SurveyBuilderController::class, 'update'])
            ->name('update');

        Route::put('/{survey}/builder', [SurveyBuilderController::class, 'update'])
            ->name('update-current');

        Route::post('/{survey}/builder-publish', [SurveyBuilderController::class, 'publish'])
            ->name('publish');

        Route::get('/{survey}/builder-activities', [SurveyBuilderController::class, 'activities'])
            ->name('activities');

        Route::post('/{survey}/builder-restore-published', [SurveyBuilderController::class, 'restorePublished'])
            ->name('restore-published');

        Route::post('/{survey}/builder-image', [SurveyBuilderController::class, 'uploadImage'])
            ->name('upload-image');
    });

Route::bind('survey', fn (string $value): Survey => Survey::query()->findOrFail($value));
