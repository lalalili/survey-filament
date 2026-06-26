<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\RecipientsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class ViewSurvey extends ViewRecord
{
    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('edit')
                ->label('編輯問卷')
                ->icon('heroicon-o-pencil-square')
                ->url(fn (): string => SurveyResource::getUrl('builder', ['record' => $this->record])),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            TextEntry::make('title')->label('標題'),
            TextEntry::make('status')
                ->label('狀態')
                ->formatStateUsing(fn ($state) => $state instanceof SurveyStatus ? $state->label() : $state),
            TextEntry::make('starts_at')->label('開始時間')->dateTime()->placeholder('—'),
            TextEntry::make('ends_at')->label('結束時間')->dateTime()->placeholder('—'),
        ]);
    }

    /**
     * @var list<class-string>
     */
    private const RELATION_MANAGERS = [
        RecipientsRelationManager::class,
    ];

    public function getRelationManagers(): array
    {
        return self::RELATION_MANAGERS;
    }

    /**
     * 收件人關聯在分頁清單中的 key，供外部產生 ?relation=<key> 深層連結（避免硬編索引）。
     */
    public static function recipientsRelationKey(): int|string
    {
        return array_search(RecipientsRelationManager::class, self::RELATION_MANAGERS, true);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title ?? '問卷詳情';
    }
}
