# Changelog

All notable changes to `lalalili/survey-filament` will be documented in this file.

## [1.4.0] - 2026-07-31

### Changed

- 把預覽執行期從 `CanvasArea.vue` 抽出，該檔由 2056 行降為 919 行。行為不變，
  純結構調整：
  - 新增 `composables/usePreviewRuntime.ts`——預覽的作答狀態、跳題導覽、顯示條件、
    計算變數與各題型輔助函式。由 `CanvasArea` 建立並 provide，編輯面與預覽面共用
    同一份狀態。
  - 新增 `components/preview/PreviewCanvas.vue`——預覽面的呈現，template 逐行原樣搬移。
  - 兩面共用的純函式（`contentBlockText`、`textInputType`、`ratingShapeIcon`、
    `formatSurveyNumber`）移入 `utils/builderHelpers`；題號計算移入新的
    `utils/questionNumbering`。
- 移除 `CanvasArea.vue` 中未使用的 `elementSupportsJump` import（重構前即已無使用）。

### Added

- `tests/Unit/CanvasAreaPreview.test.ts`：18 個預覽行為的 characterization 測試
  （顯示條件、跳題與略過頁清空、返回歷程、進度條、合計狀態、排序、評分、
  計算變數 token、條款同意、檔案格式標示、選項隨機）。全部先寫在重構前並通過，
  再據以驗證重構未改變行為。
- `tests/Unit/PreviewCanvasAllTypes.test.ts`：一次渲染全部 26 個題型分支的 smoke
  測試，攔截預覽面未解析的樣板綁定。

## [1.3.0] - 2026-07-31

### Removed

- 移除路由 `survey-filament.builder.update-current`（`PUT /{survey}/builder`）。
  它只是接住前端反推出來的舊網址的安全網，而前端在送出前早已把該網址改寫成
  `/builder-schema`，實際上從未被呼叫。宿主若曾直接引用此路由名稱，改用
  `survey-filament.builder.update`。

### Changed

- Builder 前端不再從 `window.location.pathname` 反推 API 網址。所有 endpoint
  一律讀取由 `EditSurveyBuilder::getViewData()` 以 `route()` 產生的
  `data-endpoint-*` 屬性；缺少必要屬性時直接拋錯，不再猜測網址。宿主若自訂了
  路由前綴，先前反推會指向不存在的 URL。
- `builderApi` 移除重複的 autosave 網址改寫（`normalizeAutosaveEndpoint`），
  `save()` 直接送往傳入的 `endpoints.update`。

### Fixed

- 修正前端測試套件整檔載入失敗：`@builder-ui-core` 別名原先指向同層
  `../builder-ui-core/src`，`@tiptap/*` 則仰賴向上解析到宿主的 `node_modules`，
  兩者都假設套件仍位於宿主 `packages/` 底下。改為在套件自身宣告 devDependency
  並由套件內解析，`SurveyBuilderApp`、`CanvasArea`、`RightPanel`、
  `SurveyRichEditor` 四個測試檔恢復執行。

## [1.1.1] - 2026-07-28

### Changed

- 將宿主整合範例改為中性描述，避免共用套件文件帶入特定專案識別。

## [1.1.0] - 2026-07-28

### Added

- 問卷 Builder 的單行與多行文字題可設定最少中文字數。

## [1.0.1] - 2026-07-27

### Fixed

- `php` 約束由 `^8.2` 更正為 `^8.4`。相依鏈上的
  `spatie/laravel-activitylog ^5.0` 硬性要求 php `^8.4`,原本的宣告與
  現實不符,在 8.2/8.3 上根本無法安裝。
- `phpstan.neon.dist` 的 `tmpDir` 由 `../../storage/...` 改為套件內的
  `build/phpstan`,不再假設套件位於宿主 `packages/` 底下。

### Added

- 掛上 `lalalili/.github` 的共用 CI 與 Release workflow。此套件先前因為
  相依私有 repo 而無法在 CI 解析依賴,長期沒有自動化測試。

## [1.0.0] - 2026-07-27

### Changed

- 首個穩定版。此後遵循
  [SEMVER.md](https://github.com/lalalili/.github/blob/main/SEMVER.md)
  定義的 public API 契約,宿主可安全使用 `^1.0` 約束。
- 對其他 lalalili 套件的約束一律收斂為 `^1.0`,取代先前 `^0.x`
  與多段 OR 的寫法。
- `repositories` 改用 GitHub VCS,不再依賴宿主 `packages/` 底下的
  兄弟目錄;測試資源改從 `vendor/lalalili/*` 讀取。
- 移除 `minimum-stability` / `prefer-stable` 宣告,授權統一為 MIT。

### 為什麼是 1.0.0

Composer 對 `^0.1.1` 的解讀是 `>=0.1.1 <0.2.0`,0.x 期間每發一個 minor
都需要所有宿主手動改 `composer.json`,否則 `composer update` 永遠拿不到
新版。本套件生態曾因此讓宿主停在數十個 commit 之前而無人察覺。

## v0.2.0 - 2026-07-05

### Added
- 後台問卷使用說明頁（含操作示意、暗色模式）
- 從範本建立問卷入口與問卷列表匯入題目 CSV 動作
- Builder：題型庫新增檔案上傳與簽名題、題組與選項組設定、隨機排序選項、計算與邏輯設定
- 檔案上傳題 Google Drive OAuth 綁定流程與 UI；回應檢視頁檔案答案顯示可點連結
- 問卷列表公開分類欄位與分類篩選

### Changed
- 精簡 builder 題型屬性設定、統一總計題與巢狀選擇題呈現
- 統一 builder 編輯畫布、預覽、正式填寫頁的題號計算邏輯
- Widget 與收件人列表套用租戶隔離查詢範圍

### Fixed
- 修正內容樣式頁 rich editor 初始化錯誤
- 修正 builder 頁面新增題目需捲回頂部才看得到右側面板
- 跳題邏輯預設選項文字改為「無設定」避免誤導
- 題型庫響應式版面與分類色票

## v0.1.1

- 問卷 Filament 後台初版：Builder、名單、回覆與觸發管理
