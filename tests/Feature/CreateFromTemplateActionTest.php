<?php

use Lalalili\SurveyCore\Support\SurveyBuilderTemplateRegistry;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ListSurveys;

it('offers every registry template as a select option keyed by slug', function (): void {
    $page = new ListSurveys;

    $method = (new ReflectionClass($page))->getMethod('templateOptions');
    $method->setAccessible(true);

    /** @var array<string, string> $options */
    $options = $method->invoke($page);

    $registry = app(SurveyBuilderTemplateRegistry::class)->all();

    expect(array_keys($options))->toBe(array_keys($registry));

    foreach ($registry as $slug => $template) {
        expect($options[$slug])->toContain($template['name'])
            ->and($options[$slug])->toContain($template['category']);
    }
});
