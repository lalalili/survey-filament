<?php

use Lalalili\SurveyFilament\Filament\Pages\SurveyGuide;

it('registers the guide in navigation by default', function () {
    expect(SurveyGuide::shouldRegisterNavigation())->toBeTrue()
        ->and(SurveyGuide::getNavigationLabel())->toBe('問卷使用說明');
});

it('can hide the guide through config', function () {
    config()->set('survey-filament.guide_enabled', false);

    expect(SurveyGuide::shouldRegisterNavigation())->toBeFalse();
});

it('exposes well-formed guide sections', function () {
    $sections = (new SurveyGuide)->guideSections();

    expect($sections)->not->toBeEmpty();

    $titles = array_column($sections, 'title');
    expect($titles)->toContain('基本操作', '問卷設定', '題型總覽');

    foreach ($sections as $section) {
        expect($section)->toHaveKeys(['title', 'blocks'])
            ->and($section['blocks'])->not->toBeEmpty();

        foreach ($section['blocks'] as $block) {
            expect($block)->toHaveKeys(['heading', 'items'])
                ->and($block['items'])->not->toBeEmpty();
        }
    }
});

it('documents the randomize-options feature in the guide', function () {
    $text = collect((new SurveyGuide)->guideSections())
        ->flatMap(fn (array $section): array => $section['blocks'])
        ->flatMap(fn (array $block): array => $block['items'])
        ->implode("\n");

    expect($text)->toContain('隨機')
        ->and($text)->toContain('檔案上傳')
        ->and($text)->toContain('簽名')
        ->and($text)->toContain('從範本建立');
});
