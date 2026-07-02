<?php

namespace Lalalili\SurveyFilament\Tests;

use BladeUI\Heroicons\BladeHeroiconsServiceProvider;
use BladeUI\Icons\BladeIconsServiceProvider;
use Filament\Actions\ActionsServiceProvider;
use Filament\FilamentServiceProvider;
use Filament\Forms\FormsServiceProvider;
use Filament\Infolists\InfolistsServiceProvider;
use Filament\Notifications\NotificationsServiceProvider;
use Filament\Schemas\SchemasServiceProvider;
use Filament\Support\SupportServiceProvider;
use Filament\Tables\TablesServiceProvider;
use Filament\Widgets\WidgetsServiceProvider;
use Lalalili\SurveyFilament\Tests\Fixtures\TestPanelProvider;
use Livewire\LivewireServiceProvider;

/**
 * 需要真實 Filament panel 的測試（如頁面授權）改用此基底；
 * 一般測試沿用輕量的 TestCase 以維持速度。
 */
abstract class FilamentTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge([
            LivewireServiceProvider::class,
            BladeIconsServiceProvider::class,
            BladeHeroiconsServiceProvider::class,
            SupportServiceProvider::class,
            SchemasServiceProvider::class,
            ActionsServiceProvider::class,
            FormsServiceProvider::class,
            InfolistsServiceProvider::class,
            TablesServiceProvider::class,
            WidgetsServiceProvider::class,
            NotificationsServiceProvider::class,
            FilamentServiceProvider::class,
            TestPanelProvider::class,
        ], parent::getPackageProviders($app));
    }
}
