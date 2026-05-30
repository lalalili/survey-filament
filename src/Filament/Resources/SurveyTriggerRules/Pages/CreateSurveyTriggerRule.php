<?php

namespace Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\Pages;

use Filament\Resources\Pages\CreateRecord;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;

class CreateSurveyTriggerRule extends CreateRecord
{
    protected static string $resource = SurveyTriggerRuleResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['actions_json'] = SurveyTriggerRuleResource::presetIdsToActions($data['preset_ids'] ?? []);
        unset($data['preset_ids']);

        return $data;
    }
}
