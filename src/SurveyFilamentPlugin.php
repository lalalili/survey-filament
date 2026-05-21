<?php

namespace Lalalili\SurveyFilament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Filament\Widgets\PublishedSurveysWidget;
use Lalalili\SurveyFilament\Filament\Widgets\ResponsesTrendChart;
use Lalalili\SurveyFilament\Filament\Widgets\TotalResponsesWidget;
use Lalalili\SurveyFilament\Filament\Widgets\TotalSurveysWidget;

class SurveyFilamentPlugin implements Plugin
{
    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'survey';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            SurveyResource::class,
            RecipientResource::class,
            ResponseResource::class,
        ]);

        if (config('survey-filament.widgets_enabled', true)) {
            $panel->widgets([
                TotalSurveysWidget::class,
                PublishedSurveysWidget::class,
                TotalResponsesWidget::class,
                ResponsesTrendChart::class,
            ]);
        }
    }

    public function boot(Panel $panel): void {}
}
