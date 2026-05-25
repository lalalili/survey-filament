<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\SurveyTriggerAllowedHostResource;

class ListSurveyTriggerAllowedHosts extends ListRecords
{
    protected static string $resource = SurveyTriggerAllowedHostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
