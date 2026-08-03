<?php

namespace Lalalili\SurveyFilament\Support;

use Filament\Infolists\Components\TextEntry;
use Lalalili\SurveyCore\Models\SurveyTriggerActionAttempt;

/**
 * DMS 傳送明細的欄位定義，供「DMS動作設定」的關聯清單與 DMS 傳送紀錄總覽共用，
 * 避免兩處各自維護遮蔽邏輯而漏遮。
 */
final class DmsAttemptDetailSchema
{
    /**
     * @return array<TextEntry>
     */
    public static function components(): array
    {
        return [
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
        ];
    }
}
