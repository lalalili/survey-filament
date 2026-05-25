<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Lalalili\SurveyCore\Actions\Triggers\RetryTriggerDispatchAction;
use Lalalili\SurveyCore\Enums\TriggerDispatchStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerDispatch;

class TriggerDispatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'dispatches';

    protected static ?string $title = '派送記錄';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('survey_response_id')->label('填答 ID'),
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn (TriggerDispatchStatus $state): string => match ($state) {
                        TriggerDispatchStatus::Sent    => 'success',
                        TriggerDispatchStatus::Failed  => 'danger',
                        TriggerDispatchStatus::Pending => 'warning',
                    })
                    ->formatStateUsing(fn (TriggerDispatchStatus $state): string => $state->label()),
                TextColumn::make('attempts')->label('嘗試次數'),
                TextColumn::make('error')->label('錯誤訊息')->limit(60)->placeholder('—'),
                TextColumn::make('dispatched_at')->label('派送時間')->dateTime('Y/m/d H:i')->sortable()->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Action::make('retry')
                    ->label('重送')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('確認重送')
                    ->modalDescription('將此派送記錄重設為等待中，並重新排入佇列。')
                    ->visible(fn (SurveyTriggerDispatch $record): bool => $record->status === TriggerDispatchStatus::Failed)
                    ->action(function (SurveyTriggerDispatch $record): void {
                        app(RetryTriggerDispatchAction::class)->execute($record);
                        Notification::make()->title('已重新排入佇列')->success()->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('retryFailed')
                        ->label('重送失敗筆')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('批次重送')
                        ->modalDescription('將選取的派送記錄全部重設為等待中，並重新排入佇列。')
                        ->action(function (Collection $records): void {
                            $retryAction = app(RetryTriggerDispatchAction::class);
                            $records->each(function ($dispatch) use ($retryAction): void {
                                if ($dispatch instanceof SurveyTriggerDispatch) {
                                    $retryAction->execute($dispatch);
                                }
                            });
                            Notification::make()->title("已重新排入佇列（{$records->count()} 筆）")->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->headerActions([]);
    }
}
