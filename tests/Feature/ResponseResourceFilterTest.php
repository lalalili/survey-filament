<?php

use Lalalili\SurveyCore\Enums\SurveyResponseCompletionStatus;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;

it('does not hide test responses by default when filtering responses by survey', function (): void {
    $targetSurvey = Survey::create([
        'title' => '檔案上傳測試',
        'status' => SurveyStatus::Published,
    ]);
    $otherSurvey = Survey::create([
        'title' => '其他問卷',
        'status' => SurveyStatus::Published,
    ]);

    $testResponse = SurveyResponse::create([
        'survey_id' => $targetSurvey->id,
        'completion_status' => SurveyResponseCompletionStatus::Complete,
        'submitted_at' => now(),
        'is_test' => true,
    ]);
    $otherResponse = SurveyResponse::create([
        'survey_id' => $otherSurvey->id,
        'completion_status' => SurveyResponseCompletionStatus::Complete,
        'submitted_at' => now(),
        'is_test' => false,
    ]);

    $baseQuery = fn () => SurveyResponse::query()->where('survey_id', $targetSurvey->id);

    expect(ResponseResource::scopeIsTestFilter($baseQuery(), null)->pluck('id')->all())
        ->toBe([$testResponse->id])
        ->and(ResponseResource::scopeIsTestFilter($baseQuery(), true)->pluck('id')->all())
        ->toBe([$testResponse->id])
        ->and(ResponseResource::scopeIsTestFilter($baseQuery(), false)->pluck('id')->all())
        ->toBe([])
        ->and(ResponseResource::scopeIsTestFilter(SurveyResponse::query(), null)->pluck('id')->all())
        ->toContain($testResponse->id, $otherResponse->id);
});
