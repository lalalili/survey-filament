<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Pages\SurveyGuide;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class EditSurveyBuilder extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SurveyResource::class;

    protected string $view = 'survey-filament::edit-survey-builder';

    protected static ?string $title = '設計問卷';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(static::getResource()::canEdit($this->getRecord()), 403);
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var Survey $survey */
        $survey = $this->getRecord();

        return [
            'survey' => $survey,
            'turnstileConfigured' => filled(config('survey-core.turnstile.secret_key')),
            'languageSettingEnabled' => (bool) config('survey-filament.builder_language_setting_enabled', false),
            'thankYouRedirectEnabled' => (bool) config('survey-filament.builder_thank_you_redirect_enabled', false),
            'accentColorSettingEnabled' => (bool) config('survey-filament.builder_accent_color_setting_enabled', false),
            'guideUrl' => config('survey-filament.guide_enabled', true) ? SurveyGuide::safeUrl() : null,
            'builderEndpoints' => [
                'show' => route('survey-filament.builder.show', $survey),
                'update' => route('survey-filament.builder.update', $survey),
                'publish' => route('survey-filament.builder.publish', $survey),
                'activities' => route('survey-filament.builder.activities', $survey),
                'restore_published' => route('survey-filament.builder.restore-published', $survey),
                'upload_image' => route('survey-filament.builder.upload-image', $survey),
                'google_drive_connect' => route('survey-filament.google-drive.connect', $survey),
                'google_drive_status' => route('survey-filament.google-drive.status', $survey),
                'google_drive_disconnect' => route('survey-filament.google-drive.disconnect', $survey),
            ],
        ];
    }
}
