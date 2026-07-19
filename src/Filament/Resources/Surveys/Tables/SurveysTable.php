<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\RestoreAction;
use Filament\Support\Enums\IconPosition;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Actions\CloseSurveyAction;
use Lalalili\SurveyCore\Actions\DuplicateSurveyAction;
use Lalalili\SurveyCore\Actions\ExportSurveyBuilderSchemaAction;
use Lalalili\SurveyCore\Actions\PublishSurveyAction;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ViewSurvey;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Support\PanelLabel;

class SurveysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('標題')
                    ->searchable()
                    ->sortable()
                    ->url(fn (Survey $record) => route('survey.show', $record->public_key))
                    ->openUrlInNewTab()
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->iconPosition(IconPosition::After)
                    ->iconColor('primary'),

                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        SurveyStatus::Published => 'success',
                        SurveyStatus::Closed => 'warning',
                        SurveyStatus::Archived => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => $state instanceof SurveyStatus ? $state->label() : $state),

                TextColumn::make('category')
                    ->label('分類')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('fields_count')
                    ->counts('fields')
                    ->label('題目數')
                    ->hidden(fn (): bool => SurveyResource::isSurveyTableColumnHidden('fields_count')),

                TextColumn::make('recipients_count')
                    ->counts('recipients')
                    ->label('個性化連結數')
                    ->hidden(fn (): bool => SurveyResource::isSurveyTableColumnHidden('recipients_count'))
                    // edit 頁會轉走 builder，收件人關聯只在 view 頁呈現（見 ViewSurvey::getRelationManagers）。
                    // 導向 view 頁的「收件人」分頁，並僅在有檢視權時才給連結，避免角色看得到列卻無權點出 404。
                    ->url(fn (Survey $record): ?string => SurveyResource::canView($record)
                        ? SurveyResource::getUrl('view', ['record' => $record])
                            .'?relation='.ViewSurvey::recipientsRelationKey()
                        : null)
                    ->color('primary'),

                TextColumn::make('responses_count')
                    ->counts('responses')
                    ->label(PanelLabel::get('response_count') ?? '回應數')
                    // Filament v5 將 table filters 的 query string 別名為 'filters'（#[Url(as: 'filters')]）；
                    // 舊的 'tableFilters' key 不會被還原，會導致連結帶入後過濾失效（顯示全部回應）。
                    ->url(fn (Survey $record) => ResponseResource::getUrl('index').'?'.http_build_query(['filters' => ['survey_id' => ['value' => $record->getKey()]]]))
                    ->color('primary'),

                TextColumn::make('starts_at')
                    ->label('開始時間')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->label('結束時間')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('建立時間')
                    ->dateTime('Y/m/d H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', direction: 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('狀態')
                    ->options(collect(SurveyStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])),
                SelectFilter::make('category')
                    ->label('分類')
                    ->options(fn (): array => SurveyResource::existingCategories()),
                TrashedFilter::make(),
            ])
            ->filtersFormColumns(2)
            ->columnToggleFormColumns(2)
            ->actions([
                ActionGroup::make([
                    Action::make('edit')
                        ->label('編輯')
                        ->icon('heroicon-o-pencil-square')
                        ->url(fn (Survey $record): string => SurveyResource::getUrl('builder', ['record' => $record])),
                    Action::make('analytics')
                        ->label('分析')
                        ->icon('heroicon-o-chart-bar-square')
                        ->url(fn (Survey $record): string => SurveyResource::getUrl('analytics', ['record' => $record])),

                    // Google Drive 綁定改於 Builder 的「上傳設定」內完成（檔案上傳題設定區）。
                    Action::make('export_builder_json')
                        ->label('匯出問卷 JSON')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->visible(fn (Survey $record) => SurveyResource::builderJsonActionsEnabled() && SurveyResource::canView($record))
                        ->action(function (Survey $record) {
                            $exportSchema = app(ExportSurveyBuilderSchemaAction::class);

                            return response()->streamDownload(
                                function () use ($exportSchema, $record): void {
                                    echo $exportSchema->toJson($record);
                                },
                                $exportSchema->filename($record),
                                ['Content-Type' => 'application/json; charset=UTF-8'],
                            );
                        }),

                    Action::make('publish')
                        ->label('發佈')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('success')
                        ->visible(fn (Survey $record) => SurveyResource::canEdit($record) && in_array($record->status, [SurveyStatus::Draft, SurveyStatus::Closed]))
                        ->action(fn (Survey $record) => app(PublishSurveyAction::class)->execute($record))
                        ->requiresConfirmation(),

                    Action::make('close')
                        ->label('關閉')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->visible(fn (Survey $record) => SurveyResource::canEdit($record) && $record->status === SurveyStatus::Published)
                        ->action(fn (Survey $record) => app(CloseSurveyAction::class)->execute($record))
                        ->requiresConfirmation(),

                    Action::make('duplicate')
                        ->label('複製')
                        ->icon('heroicon-o-document-duplicate')
                        ->visible(fn () => SurveyResource::canCreate())
                        ->action(fn (Survey $record) => app(DuplicateSurveyAction::class)->execute($record)),

                    Action::make('clear_responses')
                        ->label(PanelLabel::get('clear_responses') ?? '清除全部回應')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->visible(fn (Survey $record) => SurveyResource::canDelete($record))
                        ->requiresConfirmation()
                        ->modalHeading(PanelLabel::get('clear_responses') ?? '清除全部回應')
                        ->modalDescription(fn (Survey $record): string => '確定要刪除「'.$record->title.'」的所有'.(PanelLabel::get('responses_word') ?? '回應').'嗎？此操作無法復原。')
                        ->modalSubmitActionLabel('確認清除')
                        ->action(fn (Survey $record) => $record->responses()->forceDelete()),

                    SurveyResource::deleteAction(),
                    RestoreAction::make()->label('還原'),
                ]),
            ])
            ->bulkActions([]);
    }
}
