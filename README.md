# survey-filament

`survey-core` 的 Filament 後台 UI 套件（支援 Filament v4／v5）。提供問卷 / 收件人 / 回應 / 觸發等 Resource、拖曳式問卷 **Builder**、後台使用說明頁與四個
Dashboard Widget，透過 Plugin 模式掛載至 Filament Panel。

## 需求

- PHP 8.2+
- Laravel 12+
- Filament 4.x 或 5.x
- `lalalili/survey-core`

## 安裝

```bash
composer require lalalili/survey-filament
```

確認 `survey-core` 已完成安裝與 migrate：

```bash
php artisan migrate
```

## 啟用 Plugin

在 Host 的 `AdminPanelProvider` 中加入：

```php
use Lalalili\SurveyFilament\SurveyFilamentPlugin;

public function panel(Panel $panel): Panel
{
    return $panel
        // ... 其他設定
        ->plugin(SurveyFilamentPlugin::make());
}
```

## 設定

發布設定檔：

```bash
php artisan vendor:publish --tag=survey-filament-config
```

`config/survey-filament.php` 可調整：

| 鍵 | 說明 | 預設 |
|---|---|---|
| `navigation_group` | 側邊欄群組名稱 | `問卷管理` |
| `navigation_sort` | 群組排序權重 | `50` |

## Resources

### SurveyResource（問卷）
- 列表：搜尋、狀態篩選、**公開分類欄位與分類篩選**、題目數 / 收件人數 / 回應數 count 連結
- 列表 header：**從範本建立問卷**入口、**從 CSV 匯入題目**動作
- 標題欄：點選直接在新分頁開啟公開填寫頁
- 編輯 / 建構：透過拖曳式 **Builder**（見下方）設計題目、頁次、邏輯與內容樣式
- 編輯頁：支援發佈 / 關閉問卷 header actions
- Widget 與收件人查詢皆套用**租戶隔離範圍**，多租戶環境下只會看到自己租戶的資料

### RecipientResource（收件人）
- 列表：依問卷篩選
- CSV 批次匯入（欄位：name、email、external_id、payload 自訂欄）
- 可手動新增 / 編輯

### ResponseResource（回應）
- 唯讀列表：依問卷 / 完成狀態篩選
- 檢視頁：顯示所有答案（含題目類型、隱藏狀態）；**檔案上傳題答案顯示為可點的 Google Drive 連結**
- Header action：匯出此問卷所有回應的 CSV

> 另有 `SurveyTriggerRules` / `SurveyTriggerActionPresets` / `SurveyTriggerAllowedHosts` 三個 Resource 管理問卷觸發派送規則。

## Builder（問卷建構器）

`EditSurveyBuilder` 提供拖曳式問卷設計畫布，取代舊版的題目關係管理器：

- **題型庫**：依分類色票分組，涵蓋 `survey-core` 全部題型，包含**檔案上傳**與**簽名**題
- **題組與選項組**：可將題目編為題組、選項編為選項組，並設定**組內／組間隨機排序**
- **邏輯與計算**：跳題邏輯分頁、總計題與巢狀選擇題設定、計算欄位
- **內容樣式**：welcome / thank-you / terms 等內容以 rich editor 編輯
- **Google Drive 綁定**：檔案上傳題的上傳設定內以 **OAuth 彈窗**綁定 Drive 帳號與資料夾
- **即時預覽**：畫布、預覽、正式填寫頁共用同一套題號計算邏輯，所見即所得

## 使用說明頁（SurveyGuide）

後台內建 `SurveyGuide` 頁，提供含操作示意與暗色模式的問卷使用教學，供後台使用者快速上手 Builder 與派送流程。

## Widgets

加入 Dashboard 方式：在 Panel 的 `->widgets()` 加入：

```php
use Lalalili\SurveyFilament\Filament\Widgets\TotalSurveysWidget;
use Lalalili\SurveyFilament\Filament\Widgets\PublishedSurveysWidget;
use Lalalili\SurveyFilament\Filament\Widgets\TotalResponsesWidget;
use Lalalili\SurveyFilament\Filament\Widgets\ResponsesTrendChart;
```

或透過 `SurveyFilamentPlugin::make()` 自動注入（需確認 Plugin 的 `->widgets()` 已包含）。

## 題目設定說明

### 欄位類型

| 類型 | 說明 |
|---|---|
| short_text | 單行文字；Email、手機、地址單欄皆為此題型的快速預設 |
| long_text | 多行文字 |
| single_choice | 單選（需設定選項） |
| multiple_choice | 多選（需設定選項） |
| select | 下拉選單（需設定選項，支援 optgroup 分組） |
| selection_based | 重複核選題（以先前選擇題作答為選項來源） |
| rating | 評分（1–5 分） |
| nps | NPS 淨推薦值 |
| number | 數字 |
| ranking | 排序題（拖曳排序） |
| matrix_single / matrix_multi | 矩陣單選 / 矩陣多選 |
| cascade_select | 巢狀選擇題（支援 XLSX 匯入） |
| file_upload | 檔案上傳（附件送出後非同步上傳至問卷綁定的 Google Drive） |
| signature | 手寫簽名（canvas 簽名存為圖片） |
| date | 日期 |
| time | 時間 |
| linear_scale | 數字滑桿／線性量表 |
| constant_sum | 總計題 |
| section_title | 標題 |
| description_block | 說明文字 |
| quote_block | 引言 |
| divider | 分隔線 |
| hidden | 個性化欄位（伺服器端自動填入，不顯示給填答者） |

Email 預設會建立 `short_text` 並設定 `settings.input_format=email`。手機預設會建立 `short_text` 並設定 `settings.input_format=mobile_tw`，公開端使用 `tel` / `numeric` 輸入體驗，後端要求 10 碼純數字且以 `09` 開頭。`email`、`phone`、`address` 舊題型仍保留 legacy 讀取與提交相容，但不再作為新題庫核心題型。

### 跳題邏輯（Branching）

在題目編輯 modal 中設定：
- **顯示條件：當欄位** — 選擇觸發欄位
- **顯示條件：答案值為** — 輸入觸發值（對應選項的 key）

例：「請說明原因」只在「是否滿意」答案為 `no` 時顯示。

### 多頁問卷

在題目的「頁次」欄位填入頁碼（1、2、3…）。同一頁的題目依「排序」排列。
前端自動顯示「上一頁 / 下一頁」導覽，每頁切換時驗證必填欄位，所有答案在最後一頁統一提交。

## 測試

```bash
cd packages/survey-filament
composer install
./vendor/bin/pest --compact
```
