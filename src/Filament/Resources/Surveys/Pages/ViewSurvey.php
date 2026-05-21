<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\RelationManagers\FieldsRelationManager;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Filament\Widgets\SurveyInvitationEmailStatsWidget;

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
            TextEntry::make('public_key')->label('公開金鑰'),
            TextEntry::make('starts_at')->label('開始時間')->dateTime()->placeholder('—'),
            TextEntry::make('ends_at')->label('結束時間')->dateTime()->placeholder('—'),
        ]);
    }

    public function getRelationManagers(): array
    {
        return [
            FieldsRelationManager::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            SurveyInvitationEmailStatsWidget::class,
        ];
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->title ?? '問卷詳情';
    }
}
