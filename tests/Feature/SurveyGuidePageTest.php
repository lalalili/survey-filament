<?php

use Lalalili\SurveyFilament\Filament\Pages\SurveyGuide;
use Lalalili\SurveyFilament\Filament\Resources\Surveys\Pages\ListSurveys;

function surveyGuideText(): string
{
    $page = new SurveyGuide;

    $quickSteps = collect($page->guideQuickSteps())
        ->flatMap(fn (array $step): array => [$step['title'], $step['body']]);

    $sections = collect($page->guideSections())
        ->flatMap(fn (array $section): array => [
            $section['title'],
            $section['intro'] ?? '',
            ...collect($section['blocks'])
                ->flatMap(fn (array $block): array => [
                    $block['heading'],
                    ...$block['items'],
                ])
                ->all(),
        ]);

    return $quickSteps
        ->merge($sections)
        ->implode("\n");
}

it('does not register the guide in navigation', function () {
    expect(SurveyGuide::shouldRegisterNavigation())->toBeFalse()
        ->and(SurveyGuide::getNavigationLabel())->toBe('問卷使用說明');
});

it('keeps the guide hidden from navigation when disabled through config', function () {
    config()->set('survey-filament.guide_enabled', false);

    expect(SurveyGuide::shouldRegisterNavigation())->toBeFalse();
});

it('generates a safe guide url', function () {
    expect(SurveyGuide::safeUrl())->toEndWith('/admin/survey-guide');
});

it('exposes well-formed guide sections', function () {
    $page = new SurveyGuide;
    $steps = $page->guideQuickSteps();
    $sections = $page->guideSections();

    expect($steps)->toHaveCount(5);

    foreach ($steps as $step) {
        expect($step)->toHaveKeys(['label', 'title', 'body'])
            ->and($step['label'])->not->toBeEmpty()
            ->and($step['title'])->not->toBeEmpty()
            ->and($step['body'])->not->toBeEmpty();
    }

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
    $text = surveyGuideText();

    expect($text)->toContain('隨機')
        ->and($text)->toContain('檔案上傳')
        ->and($text)->not->toContain('簽名：提供數位畫布供填答者親筆簽名或手繪。')
        ->and($text)->toContain('從範本建立');
});

it('documents the core builder workflow in the guide', function () {
    $text = surveyGuideText();

    foreach (['中央畫布', '題型庫', '屬性', '邏輯', '預覽', '自動儲存', '發佈', '回復'] as $keyword) {
        expect($text)->toContain($keyword);
    }
});

it('documents that question CSV import is paused', function () {
    expect(surveyGuideText())
        ->toContain('匯入題目 CSV')
        ->toContain('暫停提供');
});

it('adds a guide action to the survey list header', function () {
    $page = new ListSurveys;
    $method = (new ReflectionClass($page))->getMethod('getHeaderActions');
    $method->setAccessible(true);

    $actions = collect($method->invoke($page));
    $guideAction = $actions->first(fn ($action): bool => $action->getName() === 'guide');

    expect($guideAction)->not->toBeNull()
        ->and($guideAction->getLabel())->toBe('使用說明')
        ->and($guideAction->getUrl())->toBe(SurveyGuide::safeUrl());
});
