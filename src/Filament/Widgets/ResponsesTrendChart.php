<?php

namespace Lalalili\SurveyFilament\Filament\Widgets;

use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Lalalili\SurveyCore\Models\SurveyResponse;
use Lalalili\SurveyFilament\Support\SurveyQueryScopes;

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

        // SQL Server 無 DATE() 函式且 GROUP BY 不吃 select 別名，需以完整表達式分組。
        $dateExpr = match (DB::getDriverName()) {
            'sqlite' => 'DATE(submitted_at)',
            'sqlsrv' => 'CAST(submitted_at AS date)',
            default => throw new \RuntimeException('Unsupported database driver ['.DB::getDriverName().'].'),
        };

        $counts = SurveyQueryScopes::responses(SurveyResponse::query())
            ->reportable()
            ->whereDate('submitted_at', '>=', now()->subDays(6)->startOfDay())
            ->selectRaw("{$dateExpr} as day, COUNT(*) as total")
            ->groupByRaw($dateExpr)
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
