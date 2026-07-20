<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Lalalili\SurveyCore\Actions\CreateBlankSurveyBuilderSurveyAction;
use Lalalili\SurveyFilament\Filament\Pages\SurveyGuide;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Actions\CreateSurveyFromTemplateHeaderAction;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Actions\ImportSurveyJsonHeaderAction;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class ListSurveys extends ListRecords
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('guide')
                ->label('使用說明')
                ->icon('heroicon-o-book-open')
                ->visible(fn (): bool => (bool) config('survey-filament.guide_enabled', true))
                ->url(fn (): string => SurveyGuide::safeUrl()),
            ImportSurveyJsonHeaderAction::make(),
            CreateSurveyFromTemplateHeaderAction::make(),
            Action::make('create')
                ->label('新增問卷')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => SurveyResource::canCreate())
                ->action(function () {
                    abort_unless(SurveyResource::canCreate(), 403);

                    $survey = app(CreateBlankSurveyBuilderSurveyAction::class)->execute();

                    Notification::make()
                        ->success()
                        ->title('問卷已建立')
                        ->body('請在設計問卷頁完成題目與問卷設定。')
                        ->send();

                    return redirect(SurveyResource::getUrl('builder', ['record' => $survey]));
                }),
        ];
    }
}
