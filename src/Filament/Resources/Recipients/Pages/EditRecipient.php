<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Recipients\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;

class EditRecipient extends EditRecord
{
    protected static string $resource = RecipientResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
