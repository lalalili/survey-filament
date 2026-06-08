<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Lalalili\SurveyCore\Actions\ComputeSurveyAnalyticsAction;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class SurveyAnalytics extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SurveyResource::class;

    protected string $view = 'survey-filament::survey-analytics';

    /** @var array<string, mixed> */
    public array $analytics = [];

    public function mount(int|string $record, ComputeSurveyAnalyticsAction $computeAnalytics): void
    {
        $this->record = $this->resolveRecord($record);
        $survey = $this->getRecord();

        abort_unless($survey instanceof Survey, 404);
        abort_unless(static::getResource()::canView($survey), 403);

        $this->analytics = $computeAnalytics->execute($survey);
    }

    public function getTitle(): string
    {
        return '問卷分析';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('builder')
                ->label('編輯問卷')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => SurveyResource::getUrl('builder', ['record' => $this->getRecord()])),
        ];
    }
}
