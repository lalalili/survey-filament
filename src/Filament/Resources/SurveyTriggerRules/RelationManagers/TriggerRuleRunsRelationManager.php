<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Lalalili\SurveyCore\Enums\TriggerRunStatus;
use Lalalili\SurveyCore\Enums\TriggerRunType;

class TriggerRuleRunsRelationManager extends RelationManager
{
    protected static string $relationship = 'runs';

    protected static ?string $title = '排程執行紀錄';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('執行時間')
                    ->dateTime('Y/m/d H:i')
                    ->sortable(),

                TextColumn::make('trigger_type')
                    ->label('觸發方式')
                    ->badge()
                    ->color(fn (TriggerRunType $state): string => match ($state) {
                        TriggerRunType::Scheduled => 'info',
                        TriggerRunType::Manual => 'gray',
                    })
                    ->formatStateUsing(fn (TriggerRunType $state): string => $state->label()),

                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->color(fn (TriggerRunStatus $state): string => match ($state) {
                        TriggerRunStatus::Completed => 'success',
                        TriggerRunStatus::Failed => 'danger',
                        TriggerRunStatus::Running => 'warning',
                    })
                    ->formatStateUsing(fn (TriggerRunStatus $state): string => $state->label()),

                TextColumn::make('scanned_count')->label('掃描')->numeric(),
                TextColumn::make('matched_count')->label('符合')->numeric(),
                TextColumn::make('dispatched_count')->label('派送')->numeric(),

                TextColumn::make('error')->label('錯誤訊息')->limit(60)->placeholder('—'),

                TextColumn::make('finished_at')
                    ->label('完成時間')
                    ->dateTime('Y/m/d H:i')
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([])
            ->bulkActions([]);
    }
}
