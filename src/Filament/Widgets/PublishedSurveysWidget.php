<?php

namespace Lalalili\SurveyFilament\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Lalalili\SurveyCore\Enums\SurveyStatus;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Support\SurveyQueryScopes;

class PublishedSurveysWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        return [
            Stat::make('Published Surveys', SurveyQueryScopes::surveys(Survey::query())->where('status', SurveyStatus::Published->value)->count())
                ->icon('heroicon-o-rocket-launch')
                ->color('success'),
        ];
    }
}
