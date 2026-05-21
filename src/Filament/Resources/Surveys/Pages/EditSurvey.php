<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Lalalili\SurveyCore\Actions\CloseSurveyAction;
use Lalalili\SurveyCore\Actions\PublishSurveyAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

/**
 * @property Survey $record
 */
class EditSurvey extends EditRecord
{
    protected static string $resource = SurveyResource::class;

    public function mount(int|string $record): void
    {
        $this->redirect(static::getResource()::getUrl('builder', ['record' => $record]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('發佈')
                ->color('success')
                ->icon('heroicon-o-rocket-launch')
                ->visible(fn () => in_array($this->record->status, [SurveyStatus::Draft, SurveyStatus::Closed]))
                ->action(fn () => app(PublishSurveyAction::class)->execute($this->record))
                ->requiresConfirmation()
                ->modalHeading('確認發佈問卷')
                ->modalDescription('發佈後收件人即可填寫，確認嗎？'),

            Action::make('close')
                ->label('關閉問卷')
                ->color('warning')
                ->icon('heroicon-o-x-circle')
                ->visible(fn () => $this->record->status === SurveyStatus::Published)
                ->action(fn () => app(CloseSurveyAction::class)->execute($this->record))
                ->requiresConfirmation()
                ->modalHeading('確認關閉問卷')
                ->modalDescription('關閉後將停止接受新的填寫，確認嗎？'),

            DeleteAction::make()->label('刪除'),
        ];
    }
}
