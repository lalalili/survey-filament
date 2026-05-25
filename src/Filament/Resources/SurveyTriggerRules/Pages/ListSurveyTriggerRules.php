<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;

class ListSurveyTriggerRules extends ListRecords
{
    protected static string $resource = SurveyTriggerRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('新增觸發規則'),
        ];
    }
}
