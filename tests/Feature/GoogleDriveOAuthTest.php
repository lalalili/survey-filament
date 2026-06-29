<?php

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\GoogleDriveAccount;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyCore\Support\GoogleDriveClientFactory;
use Lalalili\SurveyFilament\Tests\Fixtures\FakeGoogleDriveClientFactory;
use Lalalili\SurveyFilament\Tests\Fixtures\User;

beforeEach(function () {
    app()->instance(GoogleDriveClientFactory::class, new FakeGoogleDriveClientFactory);
    Gate::define('update', fn (User $user, Survey $survey): bool => true);
    $this->actingAs(User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('secret')]));
});

it('redirects to google when connecting', function () {
    $survey = Survey::create(['title' => 'Files', 'status' => SurveyStatus::Draft]);

    $this->get(route('survey-filament.google-drive.connect', $survey))
        ->assertRedirect()
        ->assertRedirectContains('accounts.google.com');
});

it('binds the survey to a drive account on callback and returns to the survey page', function () {
    $survey = Survey::create(['title' => 'Files', 'status' => SurveyStatus::Draft]);
    $state = Crypt::encryptString(json_encode([
        'survey_id' => $survey->id,
        'user_id' => 1,
        'return' => 'http://localhost/admin/surveys/'.$survey->id.'/builder',
        'expires_at' => now()->addMinutes(10)->timestamp,
    ]));

    $this->get(route('survey-filament.google-drive.callback', ['code' => 'auth-code', 'state' => $state]))
        ->assertRedirect()
        ->assertRedirectContains('google_drive=connected');

    $survey->refresh();
    $account = GoogleDriveAccount::where('google_user_id', 'sub-123')->first();

    expect($account)->not->toBeNull()
        ->and($account->email)->toBe('creator@example.com')
        ->and($account->refresh_token)->toBe('rt-auth-code')
        ->and($survey->google_drive_account_id)->toBe($account->id)
        ->and($survey->google_drive_folder_id)->toBe('folder-abc');
});

it('reports binding status as json', function () {
    $account = GoogleDriveAccount::create(['google_user_id' => 'sub-s', 'email' => 'bound@example.com']);
    $survey = Survey::create(['title' => 'Files', 'status' => SurveyStatus::Draft, 'google_drive_account_id' => $account->id]);

    $this->getJson(route('survey-filament.google-drive.status', $survey))
        ->assertOk()
        ->assertJson(['connected' => true, 'email' => 'bound@example.com', 'configured' => true]);
});

it('disconnects via json endpoint', function () {
    $account = GoogleDriveAccount::create(['google_user_id' => 'sub-d', 'email' => 'a@b.c']);
    $survey = Survey::create(['title' => 'Files', 'status' => SurveyStatus::Draft, 'google_drive_account_id' => $account->id, 'google_drive_folder_id' => 'f1']);

    $this->postJson(route('survey-filament.google-drive.disconnect', $survey))
        ->assertOk()
        ->assertJson(['connected' => false]);

    $survey->refresh();
    expect($survey->google_drive_account_id)->toBeNull()
        ->and($survey->google_drive_folder_id)->toBeNull();
});

it('returns a self-closing popup page on popup callback', function () {
    $survey = Survey::create(['title' => 'Files', 'status' => SurveyStatus::Draft]);
    $state = Crypt::encryptString(json_encode([
        'survey_id' => $survey->id,
        'user_id' => 1,
        'return' => 'http://localhost/admin',
        'popup' => true,
        'expires_at' => now()->addMinutes(10)->timestamp,
    ]));

    $response = $this->get(route('survey-filament.google-drive.callback', ['code' => 'auth-code', 'state' => $state]));

    $response->assertOk();
    expect($response->getContent())->toContain('survey-google-drive')->toContain('window.close()');
    expect($survey->refresh()->google_drive_account_id)->not->toBeNull();
});

it('rejects a callback with an expired state', function () {
    $state = Crypt::encryptString(json_encode([
        'survey_id' => 999,
        'return' => 'http://localhost/admin',
        'expires_at' => now()->subMinute()->timestamp,
    ]));

    $this->get(route('survey-filament.google-drive.callback', ['code' => 'x', 'state' => $state]))
        ->assertRedirect()
        ->assertRedirectContains('google_drive=expired');

    expect(GoogleDriveAccount::count())->toBe(0);
});
