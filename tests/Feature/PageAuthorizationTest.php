<?php

use Filament\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use Lalalili\SurveyCore\Actions\ComputeSurveyAnalyticsAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\CreateSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\EditSurveyBuilder;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ListSurveys;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\SurveyAnalytics;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Tests\Fixtures\SurveyTestPolicy;
use Lalalili\SurveyFilament\Tests\Fixtures\User;
use Symfony\Component\HttpKernel\Exception\HttpException;

function authTestSurvey(): Survey
{
    return Survey::create(['title' => 'Scoped', 'status' => SurveyStatus::Draft]);
}

/** 展開列表的 ActionGroup，回傳指定 record action 對該問卷是否可見。 */
function surveyTableActionIsVisible(string $name, Survey $survey): bool
{
    $table = SurveyResource::table(Table::make(new ListSurveys));

    $action = collect($table->getRecordActions())
        ->flatMap(fn ($action): array => $action instanceof ActionGroup
            ? $action->record($survey)->getActions()
            : [$action])
        ->first(fn ($action): bool => $action->getName() === $name);

    expect($action)->not->toBeNull();

    return $action->record($survey)->isVisible();
}

function surveyHeaderActionIsVisible(string $name): bool
{
    $page = new ListSurveys;
    $method = (new ReflectionClass($page))->getMethod('getHeaderActions');
    $method->setAccessible(true);

    $action = collect($method->invoke($page))
        ->first(fn ($action): bool => $action->getName() === $name);

    expect($action)->not->toBeNull();

    return $action->isVisible();
}

beforeEach(function (): void {
    SurveyTestPolicy::reset();
    Gate::policy(Survey::class, SurveyTestPolicy::class);

    $this->actingAs(User::create([
        'name' => 'Viewer',
        'email' => 'viewer@example.com',
        'password' => 'password',
    ]));
});

it('blocks the builder page when the user cannot update the survey', function (): void {
    SurveyTestPolicy::$allowUpdate = false;
    $survey = authTestSurvey();

    $page = new EditSurveyBuilder;

    expect(fn () => $page->mount($survey->id))
        ->toThrow(HttpException::class);
});

it('allows the builder page when the user can update the survey', function (): void {
    $survey = authTestSurvey();

    $page = new EditSurveyBuilder;
    $page->mount($survey->id);

    expect($page->getRecord()->getKey())->toBe($survey->getKey());
});

it('blocks the analytics page when the user cannot view the survey', function (): void {
    SurveyTestPolicy::$allowView = false;
    $survey = authTestSurvey();

    $page = new SurveyAnalytics;

    expect(fn () => $page->mount($survey->id, app(ComputeSurveyAnalyticsAction::class)))
        ->toThrow(HttpException::class);
});

it('blocks the analytics page when the user cannot update the survey', function (): void {
    SurveyTestPolicy::$allowUpdate = false;
    $survey = authTestSurvey();

    $page = new SurveyAnalytics;

    expect(fn () => $page->mount($survey->id, app(ComputeSurveyAnalyticsAction::class)))
        ->toThrow(HttpException::class);
});

it('hides the edit and analytics record actions when the user cannot update the survey', function (): void {
    $survey = authTestSurvey();

    expect(surveyTableActionIsVisible('edit', $survey))->toBeTrue()
        ->and(surveyTableActionIsVisible('analytics', $survey))->toBeTrue();

    SurveyTestPolicy::$allowUpdate = false;

    expect(surveyTableActionIsVisible('edit', $survey))->toBeFalse()
        ->and(surveyTableActionIsVisible('analytics', $survey))->toBeFalse();
});

it('hides the survey creation header actions when the user cannot create surveys', function (): void {
    config()->set('survey-filament.builder_json_actions_enabled', true);

    expect(surveyHeaderActionIsVisible('create'))->toBeTrue()
        ->and(surveyHeaderActionIsVisible('create_from_template'))->toBeTrue()
        ->and(surveyHeaderActionIsVisible('import_builder_json'))->toBeTrue();

    SurveyTestPolicy::$allowCreate = false;

    expect(surveyHeaderActionIsVisible('create'))->toBeFalse()
        ->and(surveyHeaderActionIsVisible('create_from_template'))->toBeFalse()
        ->and(surveyHeaderActionIsVisible('import_builder_json'))->toBeFalse();
});

it('blocks the create page when the user cannot create surveys', function (): void {
    SurveyTestPolicy::$allowCreate = false;

    $page = new CreateSurvey;

    expect(fn () => $page->mount())
        ->toThrow(HttpException::class);
});

it('rejects builder schema updates through the API when the user cannot update', function (): void {
    SurveyTestPolicy::$allowUpdate = false;
    $survey = authTestSurvey();

    $this->putJson(route('survey-filament.builder.update', $survey), [
        'schema' => ['id' => $survey->id, 'title' => 'Hacked', 'status' => 'draft', 'version' => 1, 'pages' => []],
    ])->assertForbidden();
});

it('rejects builder data reads through the API when the user cannot update', function (): void {
    SurveyTestPolicy::$allowUpdate = false;
    $survey = authTestSurvey();

    $this->getJson(route('survey-filament.builder.show', $survey))
        ->assertForbidden();
});

it('allows builder data reads through the API when the user can update', function (): void {
    $survey = authTestSurvey();

    $this->getJson(route('survey-filament.builder.show', $survey))
        ->assertOk();
});
