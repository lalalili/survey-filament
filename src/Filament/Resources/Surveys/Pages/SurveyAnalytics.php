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

    /** 篩選的 collector id；空字串代表全部（Livewire select 綁定值為字串）。 */
    public string $collectorId = '';

    /** @var array<int, array{id: int, name: string}> */
    public array $collectorOptions = [];

    public function mount(int|string $record, ComputeSurveyAnalyticsAction $computeAnalytics): void
    {
        $this->record = $this->resolveRecord($record);
        $survey = $this->getRecord();

        abort_unless($survey instanceof Survey, 404);
        // 與列表的「分析」action 一致：除了資料範圍（canView）外，還必須具備編輯權限
        // 才能看分析頁；唯讀角色請走問卷結果頁。
        abort_unless(static::getResource()::canView($survey), 403);
        abort_unless(static::getResource()::canEdit($survey), 403);

        $this->collectorOptions = $survey->collectors
            ->map(fn ($collector): array => ['id' => (int) $collector->id, 'name' => (string) $collector->name])
            ->values()
            ->all();

        $this->refreshAnalytics($computeAnalytics, $survey);
    }

    public function updatedCollectorId(): void
    {
        $survey = $this->getRecord();

        abort_unless($survey instanceof Survey, 404);

        $this->refreshAnalytics(app(ComputeSurveyAnalyticsAction::class), $survey);
    }

    public function getTitle(): string
    {
        return '問卷分析';
    }

    private function refreshAnalytics(ComputeSurveyAnalyticsAction $computeAnalytics, Survey $survey): void
    {
        $analytics = $computeAnalytics->execute(
            $survey,
            $this->collectorId === '' ? null : (int) $this->collectorId,
        );

        // 僅供 Action 相容性與測試使用；頁面改用已彙整的 trend，避免把長期逐日資料序列化到 Livewire。
        unset($analytics['daily']);

        $this->analytics = $analytics;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('builder')
                ->label('編輯問卷')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (): bool => SurveyResource::canEdit($this->getRecord()))
                ->url(fn (): string => SurveyResource::getUrl('builder', ['record' => $this->getRecord()])),
        ];
    }
}
