<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;

class ListRecipients extends ListRecords
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('匯入名單')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(RecipientResource::getUrl('import')),

            CreateAction::make()->label('新增名單'),
        ];
    }
}
