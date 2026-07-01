# survey-filament

`survey-core` 的 Filament v4 後台 UI 套件。提供三個 Resource 與四個 Widget，透過 Plugin 模式掛載至 Filament Panel。

## 需求

- PHP 8.4+
- Laravel 12+
- Filament 4.x
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
- 列表：搜尋、狀態篩選、題目數 / 收件人數 / 回應數 count 連結
- 標題欄：點選直接在新分頁開啟公開填寫頁
- 檢視頁：顯示問卷基本資訊 + **題目關係管理器**（可新增 / 排序 / 設定跳題與頁次）
- 編輯頁：支援發佈 / 關閉問卷 header actions

### RecipientResource（收件人）
- 列表：依問卷篩選
- CSV 批次匯入（欄位：name、email、external_id、payload 自訂欄）
- 可手動新增 / 編輯

### ResponseResource（回應）
- 唯讀列表：依問卷 / 完成狀態篩選
- 檢視頁：顯示所有答案（含題目類型、隱藏狀態）
- Header action：匯出此問卷所有回應的 CSV

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
| select | 下拉選單（需設定選項） |
| rating | 評分（1–5 分） |
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
