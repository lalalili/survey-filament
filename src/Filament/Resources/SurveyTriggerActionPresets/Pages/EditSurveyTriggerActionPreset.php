<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;

class EditSurveyTriggerActionPreset extends EditRecord
{
    protected static string $resource = SurveyTriggerActionPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
