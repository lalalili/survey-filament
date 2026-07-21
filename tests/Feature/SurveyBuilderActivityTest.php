<?php

use Illuminate\Support\Facades\Gate;
use Lalalili\SurveyCore\Actions\PublishSurveyAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Tests\Fixtures\User;
use Spatie\Activitylog\Models\Activity;

function builderActivitySchema(array $overrides = []): array
{
    return array_replace_recursive([
        'id' => 1,
        'title' => 'Customer Survey',
        'status' => 'draft',
        'version' => 1,
        'pages' => [
            [
                'id' => 'page_1',
                'title' => 'Page 1',
                'elements' => [
                    [
                        'id' => 'q_1',
                        'type' => 'single_choice',
                        'field_key' => 'purchase_status',
                        'label' => 'Have you purchased?',
                        'description' => '',
                        'required' => true,
                        'placeholder' => null,
                        'options' => [
                            ['id' => 'opt_1', 'label' => 'Yes', 'value' => 'yes'],
                            ['id' => 'opt_2', 'label' => 'No', 'value' => 'no'],
                        ],
                        'settings' => [],
                    ],
                ],
            ],
        ],
    ], $overrides);
}

beforeEach(function (): void {
    Gate::define('update', fn (User $user, Survey $survey): bool => true);
});

it('reports whether a published survey has unpublished builder changes', function (): void {
    $user = User::create([
        'name' => 'Editor',
        'email' => 'editor-unpublished@example.com',
        'password' => 'password',
    ]);
    $publishedSchema = builderActivitySchema(['title' => 'Published title']);
    $survey = Survey::create([
        'title' => 'Published title',
        'status' => SurveyStatus::Published,
        'draft_schema' => builderActivitySchema(['title' => 'Builder draft title']),
        'published_schema' => $publishedSchema,
    ]);

    $this->actingAs($user)
        ->getJson(route('survey-filament.builder.show', $survey))
        ->assertOk()
        ->assertJsonPath('survey.has_unpublished_changes', true);

    $survey->update(['draft_schema' => $publishedSchema]);

    $this->getJson(route('survey-filament.builder.show', $survey->refresh()))
        ->assertOk()
        ->assertJsonPath('survey.has_unpublished_changes', false);
});

it('merges autosave activity records within the same editing window', function (): void {
    $user = User::create([
        'name' => 'Editor',
        'email' => 'editor@example.com',
        'password' => 'password',
    ]);
    $survey = Survey::create(['title' => 'Draft', 'status' => SurveyStatus::Draft]);

    $this->actingAs($user)
        ->putJson(route('survey-filament.builder.update', $survey), [
            'schema' => builderActivitySchema(['title' => 'First autosave']),
        ])
        ->assertOk();

    $this->actingAs($user)
        ->putJson(route('survey-filament.builder.update', $survey), [
            'schema' => builderActivitySchema(['title' => 'Second autosave']),
        ])
        ->assertOk();

    $activity = Activity::query()
        ->where('log_name', 'survey_builder')
        ->where('event', 'autosaved')
        ->sole();

    expect($activity->getProperty('autosave_count'))->toBe(2);
});

it('round trips welcome and thank-you rich text styles through autosave and publish', function (): void {
    config()->set('survey-core.security.sanitize_html', true);

    $user = User::create([
        'name' => 'Style Editor',
        'email' => 'style-editor@example.com',
        'password' => 'password',
    ]);
    $survey = Survey::create(['title' => 'Styled Draft', 'status' => SurveyStatus::Draft]);
    $richHtml = '<h2 style="text-align: center"><strong>標題</strong></h2><h3 style="text-align: right"><em><u>副標題</u></em></h3><p style="text-align: left"><span style="color: rgb(239, 68, 68)">彩色文字</span> <a href="https://example.com" target="_blank">連結</a></p><img src="https://example.com/image.jpg" alt="圖片"><div class="survey-video"><iframe src="https://www.youtube.com/embed/abc123"></iframe></div><p><span class="survey-variable-token" data-variable-token="{{ calc.score }}" data-variable-label="總分">總分<code>calc.score</code></span></p>';
    $schema = builderActivitySchema();
    $schema['pages'] = [
        [
            'id' => 'welcome',
            'kind' => 'welcome',
            'title' => 'Welcome',
            'welcome_settings' => ['enabled' => true, 'content' => $richHtml],
            'elements' => [],
        ],
        $schema['pages'][0],
        [
            'id' => 'thanks',
            'kind' => 'thank_you',
            'title' => 'Thanks',
            'thank_you_settings' => ['enabled' => true, 'message' => $richHtml],
            'elements' => [],
        ],
    ];

    $response = $this->actingAs($user)
        ->putJson(route('survey-filament.builder.update', $survey), ['schema' => $schema])
        ->assertOk();

    $autosavedWelcome = $response->json('schema.pages.0.welcome_settings.content');
    $autosavedThankYou = $response->json('schema.pages.2.thank_you_settings.message');
    $stored = $survey->refresh();

    foreach ([$autosavedWelcome, $autosavedThankYou, $stored->draft_schema['pages'][0]['welcome_settings']['content'], $stored->draft_schema['pages'][2]['thank_you_settings']['message']] as $html) {
        expect($html)
            ->toContain('<h2 style="text-align: center"><strong>標題</strong></h2>')
            ->toContain('<h3 style="text-align: right"><em><u>副標題</u></em></h3>')
            ->toContain('<p style="text-align: left"><span style="color: #ef4444">彩色文字</span>')
            ->toContain('href="https://example.com"')
            ->toContain('<img src="https://example.com/image.jpg"')
            ->toContain('class="survey-video"')
            ->toContain('class="survey-variable-token"');
    }

    $published = app(PublishSurveyAction::class)->execute($stored);

    expect($published->published_schema['pages'][0]['welcome_settings']['content'])
        ->toBe($autosavedWelcome)
        ->and($published->pages()->where('page_key', 'thanks')->sole()->settings_json['thank_you']['message'])
        ->toBe($autosavedThankYou);
});

it('lists builder activities for a survey', function (): void {
    $user = User::create([
        'name' => 'Editor',
        'email' => 'editor-list@example.com',
        'password' => 'password',
    ]);
    $survey = Survey::create(['title' => 'Draft', 'status' => SurveyStatus::Draft]);

    activity('survey_builder')
        ->event('published')
        ->performedOn($survey)
        ->causedBy($user)
        ->withProperties(['version' => 2])
        ->log('發布問卷');

    $this->actingAs($user)
        ->getJson(route('survey-filament.builder.activities', $survey))
        ->assertOk()
        ->assertJsonFragment([
            'event' => 'published',
            'label' => '發布問卷',
            'causer_name' => 'Editor',
        ]);
});

it('restores the draft schema from the current published version without bumping version', function (): void {
    $user = User::create([
        'name' => 'Editor',
        'email' => 'editor-restore@example.com',
        'password' => 'password',
    ]);
    $publishedSchema = builderActivitySchema(['title' => 'Published']);
    $draftSchema = builderActivitySchema(['title' => 'Unsaved draft']);
    $survey = Survey::create([
        'title' => 'Unsaved draft',
        'status' => SurveyStatus::Published,
        'version' => 3,
        'draft_schema' => $draftSchema,
        'published_schema' => $publishedSchema,
        'published_at' => now(),
    ]);

    $this->actingAs($user)
        ->postJson(route('survey-filament.builder.restore-published', $survey))
        ->assertOk()
        ->assertJsonPath('survey.version', 3)
        ->assertJsonPath('schema.title', 'Published');

    expect($survey->refresh()->draft_schema['title'])->toBe('Published')
        ->and($survey->version)->toBe(3)
        ->and(Activity::query()->where('event', 'restored_published')->exists())->toBeTrue();
});

it('rejects restoring a survey without a published schema', function (): void {
    $user = User::create([
        'name' => 'Editor',
        'email' => 'editor-conflict@example.com',
        'password' => 'password',
    ]);
    $survey = Survey::create([
        'title' => 'Draft',
        'status' => SurveyStatus::Draft,
        'draft_schema' => builderActivitySchema(),
    ]);

    $this->actingAs($user)
        ->postJson(route('survey-filament.builder.restore-published', $survey))
        ->assertConflict();
});
