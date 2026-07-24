<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Lalalili\SurveyCore\Actions\ComputeSurveyAnalyticsAction;
use Lalalili\SurveyCore\Enums\SurveyFieldType;
use Lalalili\SurveyCore\Enums\SurveyResponseCompletionStatus;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Models\SurveyAnswer;
use Lalalili\SurveyCore\Models\SurveyField;
use Lalalili\SurveyCore\Models\SurveyPage;
use Lalalili\SurveyCore\Models\SurveyResponse;
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

it('renders the standard NPS score, segments, distribution, and daily trend', function (): void {
    $survey = Survey::create([
        'title' => 'NPS survey',
        'status' => SurveyStatus::Published,
        'allow_anonymous' => true,
    ]);

    $field = SurveyField::create([
        'survey_id' => $survey->id,
        'type' => SurveyFieldType::Nps,
        'label' => '推薦意願',
        'field_key' => 'recommendation',
        'sort_order' => 1,
    ]);

    foreach ([0, 6, 8, 10] as $score) {
        $response = SurveyResponse::create([
            'survey_id' => $survey->id,
            'submitted_at' => now(),
            'completion_status' => SurveyResponseCompletionStatus::Complete,
        ]);

        SurveyAnswer::create([
            'survey_response_id' => $response->id,
            'survey_field_id' => $field->id,
            'answer_text' => (string) $score,
        ]);
    }

    $page = new SurveyAnalytics;
    $page->mount($survey->id, app(ComputeSurveyAnalyticsAction::class));

    $question = $page->analytics['questions'][0];
    $compiledView = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/survey-analytics.blade.php'));

    expect($question['nps'])
        ->toMatchArray([
            'score' => -25.0,
            'respondents' => 4,
            'detractors' => ['count' => 2, 'percentage' => 50.0],
            'passives' => ['count' => 1, 'percentage' => 25.0],
            'promoters' => ['count' => 1, 'percentage' => 25.0],
        ])
        ->and($question['distribution'])->toHaveCount(11)
        ->and($compiledView)->toContain('NPS 分數', '貶損者', '中立者', '推薦者', '分數分布', '每日趨勢');
});
