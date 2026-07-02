<?php

namespace Lalalili\SurveyFilament\Tests\Fixtures;

use Filament\Panel;
use Filament\PanelProvider;
use Lalalili\SurveyFilament\SurveyFilamentPlugin;

class TestPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->plugin(SurveyFilamentPlugin::make());
    }
}
