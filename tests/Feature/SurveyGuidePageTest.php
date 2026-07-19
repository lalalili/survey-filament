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
                ->flatMap(fn (array $block): array => array_merge(
                    [$block['heading']],
                    filled($block['body'] ?? null) ? [$block['body']] : [],
                    $block['items'] ?? [],
                    $block['steps'] ?? [],
                    $block['headers'] ?? [],
                    collect($block['rows'] ?? [])->flatten()->all(),
                ))
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
    expect($titles)->toContain('Builder 介面導覽', '問卷設定與填答體驗', '題型總覽', '題型功能對照', '進階功能操作', '發佈與問題排除');

    foreach ($sections as $section) {
        expect($section)->toHaveKeys(['title', 'blocks'])
            ->and($section['blocks'])->not->toBeEmpty();

        foreach ($section['blocks'] as $block) {
            expect($block)->toHaveKey('heading');

            if (($block['type'] ?? 'list') === 'table') {
                expect($block)->toHaveKeys(['headers', 'rows'])
                    ->and($block['headers'])->not->toBeEmpty()
                    ->and($block['rows'])->not->toBeEmpty();
            } elseif (($block['type'] ?? 'list') === 'screenshot') {
                expect($block)->toHaveKeys(['body', 'variant', 'steps'])
                    ->and($block['body'])->not->toBeEmpty()
                    ->and($block['variant'])->not->toBeEmpty()
                    ->and($block['steps'])->not->toBeEmpty();
            } else {
                expect($block)->toHaveKey('items')
                    ->and($block['items'])->not->toBeEmpty();
            }
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

it('explains safe changes to published surveys in user-friendly language', function () {
    expect(surveyGuideText())
        ->toContain('修改已發佈問卷與刪除題目')
        ->toContain('只會更新草稿')
        ->toContain('受影響的歷史答案數量')
        ->toContain('改為退役')
        ->toContain('部分系統識別設定、題型及曾被使用的選項會被鎖定')
        ->not->toContain('欄位代碼')
        ->toContain('重新整理提示')
        ->toContain('不能永久刪除');
});

it('documents cascade select xlsx import instead of paused csv import', function () {
    expect(surveyGuideText())
        ->toContain('下載範例檔')
        ->toContain('上傳資料')
        ->toContain('XLSX')
        ->not->toContain('問卷 JSON 匯出與匯入')
        ->not->toContain('匯入題目 CSV')
        ->not->toContain('暫停提供');
});

it('only documents builder json import and export when enabled', function () {
    config()->set('survey-filament.builder_json_actions_enabled', true);

    expect(surveyGuideText())
        ->toContain('需要備份或複製整份問卷時')
        ->toContain('問卷 JSON 匯出與匯入')
        ->toContain('保留題目、頁面與設定內容')
        ->not->toContain('若系統設定開啟 Builder JSON 功能');
});

it('documents supported advanced builder settings', function () {
    $text = surveyGuideText();

    foreach ([
        '問卷計算變數',
        '{{ calc.total_score }}',
        '選項名額',
        '隨機排列選項',
        '分數設定',
        '顯示條件',
        '跳題邏輯',
        '發佈失敗',
    ] as $keyword) {
        expect($text)->toContain($keyword);
    }
});

it('documents focused operation screenshot blocks', function () {
    $text = surveyGuideText();

    foreach ([
        '操作截圖：巢狀選擇題資料',
        '操作截圖：問卷計算變數',
        '操作截圖：顯示條件與跳題',
        '操作截圖：發佈錯誤提示',
        '下載範例檔後填好 XLSX 再上傳',
        '在分數設定中為各選項填入加分或扣分',
        '依頁面、題號與分類回到對應設定',
    ] as $keyword) {
        expect($text)->toContain($keyword);
    }
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
