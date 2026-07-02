<?php

namespace Lalalili\SurveyFilament\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Lalalili\SurveyCore\Models\Survey;
use Lalalili\SurveyFilament\Support\SurveyQueryScopes;

class TotalSurveysWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Surveys', SurveyQueryScopes::surveys(Survey::query())->count())
                ->icon('heroicon-o-clipboard-document-list'),
        ];
    }
}
