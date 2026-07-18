<?php

use Lalalili\SurveyFilament\Tests\FilamentTestCase;
use Lalalili\SurveyFilament\Tests\TestCase;

$filamentTestFiles = [
    'Feature/PageAuthorizationTest.php',
    'Feature/SurveyAnalyticsFunnelTest.php',
];

uses(FilamentTestCase::class)->in(...$filamentTestFiles);
uses(TestCase::class)->in('Unit');
uses(TestCase::class)
    ->in(...array_values(array_diff(
        array_map(
            fn (string $path): string => 'Feature/'.basename($path),
            glob(__DIR__.'/Feature/*Test.php') ?: [],
        ),
        $filamentTestFiles,
    )));
