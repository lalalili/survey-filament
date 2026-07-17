<?php

namespace Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Lalalili\SurveyCore\Actions\CreateBlankSurveyBuilderSurveyAction;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;

class CreateSurvey extends CreateRecord
{
    protected static string $resource = SurveyResource::class;

    public function mount(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);

        $survey = app(CreateBlankSurveyBuilderSurveyAction::class)->execute();

        $this->redirect($this->getResource()::getUrl('builder', ['record' => $survey]));
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('問卷已建立')
            ->body('請在下方新增題目與收件人。');
    }
}
