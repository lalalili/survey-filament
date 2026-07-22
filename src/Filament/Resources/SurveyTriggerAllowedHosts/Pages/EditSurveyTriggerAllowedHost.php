<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\Pages;

use Filament\Resources\Pages\EditRecord;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\SurveyTriggerAllowedHostResource;

class EditSurveyTriggerAllowedHost extends EditRecord
{
    protected static string $resource = SurveyTriggerAllowedHostResource::class;

    protected function getHeaderActions(): array
    {
        return [SurveyTriggerAllowedHostResource::deleteAction()];
    }
}
