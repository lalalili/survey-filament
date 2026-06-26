<?php

use Illuminate\Support\Facades\Gate;
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
