<?php

namespace Lalalili\SurveyFilament\Tests;

use Lalalili\PackageTestingSupport\PackageTestCase;
use Lalalili\SurveyCore\SurveyCoreServiceProvider;
use Lalalili\SurveyFilament\SurveyFilamentServiceProvider;

abstract class TestCase extends PackageTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            SurveyCoreServiceProvider::class,
            SurveyFilamentServiceProvider::class,
        ];
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../../survey-core/database/migrations');
    }
}
