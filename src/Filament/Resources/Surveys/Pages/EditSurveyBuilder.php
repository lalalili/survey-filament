<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Lalalili\SurveyCore\Models\Survey;
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
            'survey'              => $survey,
            'turnstileConfigured' => filled(config('survey-core.turnstile.secret_key')),
            'builderEndpoints'    => [
                'show'         => route('survey-filament.builder.show', $survey),
                'update'       => route('survey-filament.builder.update', $survey),
                'publish'      => route('survey-filament.builder.publish', $survey),
                'upload_image' => route('survey-filament.builder.upload-image', $survey),
            ],
        ];
    }
}
