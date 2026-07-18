<?php

use Illuminate\Support\Facades\Gate;
use Lalalili\SurveyCore\Actions\ComputeSurveyAnalyticsAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyPage;
use Lalalili\SurveyCore\Models\SurveyResponseEvent;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\SurveyAnalytics;
use Lalalili\SurveyFilament\Tests\Fixtures\SurveyTestPolicy;
use Lalalili\SurveyFilament\Tests\Fixtures\User;

beforeEach(function (): void {
    SurveyTestPolicy::reset();
    Gate::policy(Survey::class, SurveyTestPolicy::class);

    $this->actingAs(User::create([
        'name' => 'Viewer',
        'email' => 'viewer@example.com',
        'password' => 'password',
    ]));
});

it('surfaces the drop-off funnel steps on the analytics page', function (): void {
    $survey = Survey::create([
        'title' => 'Funnel survey',
        'status' => SurveyStatus::Published,
        'allow_anonymous' => true,
    ]);

    SurveyPage::create(['survey_id' => $survey->id, 'page_key' => 'p1', 'title' => '基本資料', 'sort_order' => 1]);

    SurveyResponseEvent::create(['survey_id' => $survey->id, 'event' => 'started', 'occurred_at' => now()]);
    SurveyResponseEvent::create(['survey_id' => $survey->id, 'event' => 'page_viewed', 'page_key' => 'p1', 'occurred_at' => now()]);
    SurveyResponseEvent::create(['survey_id' => $survey->id, 'event' => 'page_viewed', 'page_key' => 'p1', 'occurred_at' => now()]);

    $page = new SurveyAnalytics;
    $page->mount($survey->id, app(ComputeSurveyAnalyticsAction::class));

    $labels = array_column($page->analytics['funnel']['steps'], 'label');

    expect($labels)->toBe(['開始填寫', '基本資料', '送出'])
        ->and($page->analytics['funnel']['steps'][1])->toMatchArray(['key' => 'p1', 'count' => 2]);
});
