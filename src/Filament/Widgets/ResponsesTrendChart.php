<?php

namespace Lalalili\SurveyFilament\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Lalalili\SurveyCore\Models\SurveyResponse;

class ResponsesTrendChart extends ChartWidget
{
    protected ?string $heading = 'Responses (Last 7 Days)';

    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $days = collect(range(6, 0))->map(fn ($d) => now()->subDays($d)->format('Y-m-d'));

        $counts = SurveyResponse::query()
            ->whereDate('submitted_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw('DATE(submitted_at) as day, COUNT(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $data = $days->map(fn ($day) => $counts->get($day, 0))->values()->all();
        $labels = $days->map(fn ($day) => Carbon::parse($day)->format('M d'))->values()->all();

        return [
            'datasets' => [
                [
                    'label' => 'Responses',
                    'data' => $data,
                    'backgroundColor' => '#6366f1',
                ],
            ],
            'labels' => $labels,
        ];
    }
}
