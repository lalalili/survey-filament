<?php

namespace Lalalili\SurveyFilament;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class SurveyFilamentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('survey-filament')
            ->hasConfigFile('survey-filament')
            ->hasViews('survey-filament')
            ->hasRoutes(['web']);
    }
}
