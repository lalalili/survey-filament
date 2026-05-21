<?php

namespace Lalalili\SurveyFilament\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Lalalili\SurveyCore\Models\SurveyResponse;

class TotalResponsesWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Responses', SurveyResponse::count())
                ->icon('heroicon-o-inbox-stack'),
        ];
    }
}
