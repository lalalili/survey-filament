<?php

namespace Lalalili\SurveyFilament\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;

class SurveyGuide extends Page
{
    protected string $view = 'survey-filament::survey-guide';

    public static function getNavigationIcon(): string|BackedEnum|null
    {
        return 'heroicon-o-book-open';
    }

    public static function getNavigationLabel(): string
    {
        return '問卷使用說明';
    }

    public function getTitle(): string
    {
        return '問卷使用說明';
    }

    public static function getNavigationGroup(): ?string
    {
        return config('survey-filament.navigation_group', '活動自動化');
    }

    public static function getNavigationSort(): ?int
    {
        return 90;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('survey-filament.guide_enabled', true);
    }

    public static function safeUrl(): string
    {
        $routeName = 'filament.admin.pages.'.static::getDefaultSlug();

        if (Route::has($routeName)) {
            return route($routeName);
        }

        return url('/admin/'.static::getDefaultSlug());
    }

    /**
     * @return list<array{label: string, title: string, body: string}>
     */
    public function guideQuickSteps(): array
    {
        return [
            [
                'label' => '1',
                'title' => '建立或開啟問卷',
                'body' => '從問卷列表點「新增問卷」建立空白問卷，或用「從範本建立」快速產生常用情境，再進入「編輯」開始設計。',
            ],
            [
                'label' => '2',
                'title' => '加入題目與頁面',
                'body' => '在 Builder 右側「題型庫」選擇題型加入目前頁面；需要分段時用上方頁籤新增頁面，把相關題目放在同一頁。',
            ],
            [
                'label' => '3',
                'title' => '設定屬性與邏輯',
                'body' => '選取題目後到「屬性」調整題目文字、提示、必填與選項；到「邏輯」設定顯示條件或跳題。',
            ],
            [
                'label' => '4',
                'title' => '預覽並修正',
                'body' => '用上方「預覽」檢查填答流程、手機版呈現與跳題結果；若看到儲存錯誤，依錯誤提示回到對應頁面與題目修正。',
            ],
            [
                'label' => '5',
                'title' => '發佈正式版本',
                'body' => '自動儲存只會保存草稿，不會影響填答者看到的版本；確認完成後點「發佈問卷」才會更新正式問卷。',
            ],
        ];
    }

    /**
     * 說明內容（以資料驅動，便於維護）。每個章節含標題與若干區塊，
     * 每個區塊有小標題與條列內容；描述對象為 Vue 拖拉式問卷編輯器（Builder）。
     *
     * @return list<array{title: string, intro?: string, blocks: list<array{heading: string, items: list<string>}>}>
     */
    public function guideSections(): array
    {
        return [
            [
                'title' => '基本操作',
                'intro' => '問卷以「拖拉式編輯器（Builder）」設計。從問卷列表點「新增問卷」「從範本建立」，或在既有問卷列動作點「編輯」即可進入。',
                'blocks' => [
                    [
                        'heading' => '從問卷列表開始',
                        'items' => [
                            '「新增問卷」會建立空白問卷並直接進入 Builder，適合從零設計。',
                            '「從範本建立」可選活動報名、滿意度調查、NPS、課程回饋、名單蒐集、售後追蹤等範本，建立後再依需求調整。',
                            '既有問卷列動作中的「編輯」會開啟 Builder；「分析」可查看回覆統計。',
                        ],
                    ],
                    [
                        'heading' => '編輯器版面',
                        'items' => [
                            '中央畫布顯示目前頁面的題目，可拖曳題目卡片調整順序，也可用題目右上角快捷功能複製、開啟邏輯或刪除。',
                            '右側面板有三個分頁：「題型庫」加入新題目、「屬性」設定內容與選項、「邏輯」設定顯示條件與跳題。',
                            '上方工具列可編輯問卷標題、查看儲存狀態、開啟「編輯紀錄」、切換「預覽」並發佈問卷。',
                        ],
                    ],
                    [
                        'heading' => '新增與編輯題目',
                        'items' => [
                            '在「題型庫」分頁點選題型即可加入到目前頁面；題型依「選擇題／輸入題／評分題／上傳題／內容與樣式」分類。',
                            '點選畫布上的題目後，於「屬性」分頁編輯題目文字、說明、填答提示、是否必填與選項。',
                            '不同題型會出現專屬設定，例如數字區間、星級級數、NPS 標籤、矩陣列與答案、巢狀選擇資料、檔案上傳設定。',
                        ],
                    ],
                    [
                        'heading' => '頁面、預覽與版本',
                        'items' => [
                            '問卷可分為多個頁面；未選取題目時，右側「屬性」分頁可編輯頁面標題、複製或刪除頁面。',
                            '歡迎頁與感謝頁為特殊頁面，分別顯示於問卷開頭與送出後。',
                            '「預覽」可檢查填答者實際流程，並可切換行動版檢視手機版面；跳題與顯示條件會依設定運作。',
                            '編輯內容會自動儲存為草稿；填答者只會看到最後一次「發佈問卷」後的正式版本。',
                            '「編輯紀錄」可查看自動儲存、發佈與回復紀錄；執行回復會清除目前尚未發佈的草稿變更。',
                        ],
                    ],
                    [
                        'heading' => '匯入與匯出',
                        'items' => [
                            '「匯入題目 CSV」目前暫停提供；請先在 Builder 內新增題目，或使用「從範本建立」後再調整題目內容。',
                            '若系統設定開啟 Builder JSON 功能，可匯出問卷 JSON 備份，或用「匯入問卷 JSON」還原完整問卷結構。',
                        ],
                    ],
                ],
            ],
            [
                'title' => '問卷設定',
                'intro' => '於 Builder 開啟「問卷設定」可調整全域行為。',
                'blocks' => [
                    [
                        'heading' => '外觀與填答體驗',
                        'items' => [
                            '進度顯示方式（無／進度條／步驟／百分比）與是否顯示預估填答時間。',
                            '是否顯示題號、是否允許返回上一頁、介面語言。',
                            '可設定歡迎頁內容、感謝頁訊息，以及填答完成後的導向網址與導向方式。',
                        ],
                    ],
                    [
                        'heading' => '開放與回收控制',
                        'items' => [
                            '開始／結束時間、回收份數上限與額滿提示訊息。',
                            '防止重複填答模式（無／Token／Email／IP／Cookie）與重複填答提示。',
                            '密碼保護、人機驗證與最小填答秒數等異常偵測設定。',
                            '含檔案上傳題的問卷需先完成 Google Drive 綁定，才能發佈並接收上傳檔案。',
                        ],
                    ],
                    [
                        'heading' => '個性化與完成導向',
                        'items' => [
                            '綁定個性化名單後，可將隱藏題對應名單欄位自動帶入填答者資料。',
                            '可指定名單中的姓名、Email 與外部 ID 欄位，方便後台辨識、匯出與後續訊息個人化。',
                        ],
                    ],
                ],
            ],
            [
                'title' => '題型總覽',
                'intro' => '以下為目前支援的題型，依用途分類。',
                'blocks' => [
                    [
                        'heading' => '選擇題',
                        'items' => [
                            '單選題：填答者僅能選擇一個選項，可設定跳題邏輯與選項名額。',
                            '複選題：可選一至多個選項，可限制最少／最多選取數。',
                            '下拉選單：以下拉方式呈現的單選，支援跳題邏輯。',
                            '單選矩陣／複選矩陣：以相同基準評估多個項目；可設定列與答案，並可隨機排列列。',
                            '巢狀選擇題：多層級篩選（如縣市→鄉鎮區），可自訂層級與選項資料。',
                            '重複核選題：選項會帶入填答者在「來源題目」中所選的答案，讓填答者再從中複選。',
                            '日期：以標準格式蒐集日期。',
                        ],
                    ],
                    [
                        'heading' => '輸入題',
                        'items' => [
                            '單行文字／多行文字：純文字輸入，可設定字數限制與格式規則。',
                            '數字：僅能輸入數字，可設定範圍、間距、小數位數與單位。',
                            '總計題：統計各選項填入數值是否符合指定總和。',
                        ],
                    ],
                    [
                        'heading' => '評分題',
                        'items' => [
                            '排序題：拖曳排列多個選項以了解優先順序。',
                            '星級評分：圖像式評分，可設定級數與圖案（星／心／勾／讚）。',
                            'NPS 淨推薦值：0–10 量表，可自訂兩端標籤與色彩分段。',
                            '數字滑桿：以拖曳滑桿於指定區間選取數值。',
                        ],
                    ],
                    [
                        'heading' => '上傳題',
                        'items' => [
                            '檔案上傳：讓填答者上傳檔案，可限制最大檔案大小與允許的檔案格式。',
                            '簽名：提供數位畫布供填答者親筆簽名或手繪。',
                        ],
                    ],
                    [
                        'heading' => '內容與樣式',
                        'items' => [
                            '區段標題：純文字標題，用於歸類題目（不需作答）。',
                            '說明文字／引言：純文字區塊，用於引導或美化問卷（不需作答）。',
                            '分隔線：在問卷中加入分隔。',
                        ],
                    ],
                    [
                        'heading' => '進階設定',
                        'items' => [
                            '選項名額：選擇題的每個選項可設定名額上限，適合限量、先到先得的情境；達上限時填答頁會顯示「已額滿」並停用該選項，送出時亦會再次驗證以避免超賣。留空代表不限名額。',
                            '選項隨機排序：選擇題與矩陣題可開啟隨機排列，避免位置偏誤；同一位填答者於填答過程中順序維持一致。',
                            '選項組：為選項填寫「組別」可分組顯示，並可進一步隨機各組的呈現順序與組內順序。',
                            '題組：為多個題目填寫相同「題組名稱」可歸為一組，並可隨機排列同題組題目的出現順序。',
                            '顯示條件：依先前題目的答案決定是否顯示某題（支援多條件 AND／OR）。',
                            '跳題邏輯：單選與下拉題可依選項決定前往下一頁、指定頁或結束問卷。',
                            '計算變數：選項可加減分，用於計分型問卷與報表。',
                        ],
                    ],
                ],
            ],
        ];
    }
}
