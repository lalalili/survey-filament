<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Lalalili\SurveyCore\Enums\SurveyTriggerActionAttemptStatus;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;
use Lalalili\SurveyCore\Models\SurveyTriggerActionPreset;
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
                    ->formatStateUsing(fn (SurveyTriggerActionAttemptStatus $status): string => self::statusLabel($status))
                    ->color(fn (SurveyTriggerActionAttemptStatus $status): string => self::statusColor($status)),
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
                    ->schema(fn (Schema $schema): Schema => $schema->components([
                        TextEntry::make('request_parameters')
                            ->label('Request parameters')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::prettyJson($record->request_parameters))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('request_body')
                            ->label('SOAP request（sKey 已遮蔽）')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::redactXml($record->request_body))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('response_headers')
                            ->label('Response headers')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::prettyJson($record->response_headers))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('response_body')
                            ->label('完整 Response')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::redactXml($record->response_body))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('parsed_response')
                            ->label('解析結果')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::prettyJson($record->parsed_response))
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('error')
                            ->label('錯誤／例外')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::redactXml($record->error))
                            ->placeholder('—')
                            ->copyable()
                            ->columnSpanFull(),
                        TextEntry::make('debug_information')
                            ->label('複製廠商除錯資訊')
                            ->state(fn (SurveyTriggerActionAttempt $record): string => DmsAttemptPresenter::debugInformation($record))
                            ->copyable()
                            ->columnSpanFull(),
                    ])),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function statusLabel(SurveyTriggerActionAttemptStatus $status): string
    {
        return match ($status) {
            SurveyTriggerActionAttemptStatus::PendingReview => '待判讀',
            SurveyTriggerActionAttemptStatus::Skipped => '已略過',
            SurveyTriggerActionAttemptStatus::Success => '成功',
            SurveyTriggerActionAttemptStatus::BusinessError => '業務錯誤',
            SurveyTriggerActionAttemptStatus::SoapFault => 'SOAP 錯誤',
            SurveyTriggerActionAttemptStatus::HttpError => 'HTTP 錯誤',
            SurveyTriggerActionAttemptStatus::ConnectionError => '連線失敗',
        };
    }

    private static function statusColor(SurveyTriggerActionAttemptStatus $status): string
    {
        return match ($status) {
            SurveyTriggerActionAttemptStatus::Success => 'success',
            SurveyTriggerActionAttemptStatus::PendingReview => 'warning',
            SurveyTriggerActionAttemptStatus::Skipped => 'gray',
            default => 'danger',
        };
    }
}
