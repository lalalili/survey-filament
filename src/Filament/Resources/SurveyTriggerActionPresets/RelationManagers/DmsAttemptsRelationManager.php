<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
use Lalalili\SurveyFilament\Support\DmsAttemptDetailSchema;
use Lalalili\SurveyFilament\Support\DmsAttemptPresenter;

class DmsAttemptsRelationManager extends RelationManager
{
    protected static string $relationship = 'attempts';

    protected static ?string $title = 'DMS 測試／傳送紀錄';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        if (! $ownerRecord instanceof SurveyTriggerActionPreset) {
            return false;
        }

        $action = $ownerRecord->action_json;

        return ($action['type'] ?? null) === 'dms_soap';
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->label('狀態')
                    ->badge()
                    ->formatStateUsing(fn (SurveyTriggerActionAttemptStatus $status): string => DmsAttemptPresenter::statusLabel($status))
                    ->color(fn (SurveyTriggerActionAttemptStatus $status): string => DmsAttemptPresenter::statusColor($status)),
                TextColumn::make('ticket_no')
                    ->label('Ticket')
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('profile')
                    ->label('Profile'),
                TextColumn::make('sent_at')
                    ->label('傳送時間')
                    ->dateTime('Y/m/d H:i:s')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('response_http_status')
                    ->label('HTTP')
                    ->placeholder('—'),
                TextColumn::make('duration_ms')
                    ->label('耗時')
                    ->suffix(' ms')
                    ->placeholder('—'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('查看／複製廠商除錯資訊')
                    ->modalHeading('DMS 傳送明細（敏感資訊已遮蔽）')
                    ->schema(fn (Schema $schema): Schema => $schema->components(
                        DmsAttemptDetailSchema::components(),
                    )),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
