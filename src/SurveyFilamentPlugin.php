<?php

namespace Lalalili\SurveyFilament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Lalalili\AudienceCore\Models\AudienceList;
use Lalalili\SurveyFilament\Filament\Resources\Recipients\RecipientResource;
use Lalalili\SurveyFilament\Filament\Resources\Responses\ResponseResource;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\SurveyResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerActionPresets\SurveyTriggerActionPresetResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerAllowedHosts\SurveyTriggerAllowedHostResource;
use Lalalili\SurveyFilament\Filament\Resources\SurveyTriggerRules\SurveyTriggerRuleResource;
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
        $resources = [
            config('survey-filament.survey_resource_class') ?? SurveyResource::class,
            config('survey-filament.response_resource_class') ?? ResponseResource::class,
            SurveyTriggerRuleResource::class,
            SurveyTriggerActionPresetResource::class,
            SurveyTriggerAllowedHostResource::class,
        ];

        // RecipientResource 需要 audience-core，有安裝時才啟用
        if (class_exists(AudienceList::class)) {
            $resources[] = RecipientResource::class;
        }

        $panel->resources($resources);

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
