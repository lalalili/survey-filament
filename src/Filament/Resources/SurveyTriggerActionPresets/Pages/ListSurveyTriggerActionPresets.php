<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;

class ListSurveyTriggerActionPresets extends ListRecords
{
    protected static string $resource = SurveyTriggerActionPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
