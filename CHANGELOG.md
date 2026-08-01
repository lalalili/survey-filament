# Changelog

All notable changes to `lalalili/survey-filament` will be documented in this file.

## [1.7.1] - 2026-08-01

### Changed

- 問卷設定的個性化名單欄位，統一為「欄位、值、備註說明」版型，讓收件人欄位與問卷結果固定欄位更容易對照。
- 小螢幕問卷設定改為全螢幕內容與頂部橫向導覽，欄位對應在窄空間改為單欄排列。

### Accessibility

- 個性化名單的 7 個欄位選單補齊標籤與備註關聯，並維持至少 44px 的行動版操作高度。

## [1.7.0] - 2026-08-01

### Added

- 行動版建立器新增「畫布、題型庫、屬性、邏輯」工作區導覽，窄螢幕可在完整寬度的內容面板間切換。
- 對話框加入焦點圈選、關閉後還原焦點，以及 `Escape` 關閉支援。

### Changed

- 重整小螢幕工具列、題頁導覽、空白狀態與觸控目標，並保留桌面版既有三欄工作流程。
- 題頁與工作區頁籤改用原生按鈕及方向鍵導覽，儲存狀態改為即時區域公告。

### Accessibility

- 補齊建立器對話框的名稱、模態語意與焦點管理，並改善頁籤、提示文字及減少動態效果偏好的支援。

## [1.6.1] - 2026-08-01

### Changed

- 驗證錯誤解析從 `CanvasArea.vue` 抽成 `utils/validationErrors`（純函式，吃 pages
  陣列與題號對照表，不碰 store）。`CanvasArea` 的 `<script setup>` 由 414 行降為
  301 行。行為不變，目的是讓這 130 行原本只能透過渲染整個元件間接驗證的邏輯
  可以單獨測試。

### Added

- `tests/Unit/validationErrors.test.ts`：11 個測試涵蓋訊息翻譯的比對順序、
  `show_if` 條件編號、題頁編號跳過歡迎／感謝頁、標題與題號的退回順序，
  以及 key 超出 schema 範圍時的既有行為。

## [1.6.0] - 2026-08-01

### Fixed

- **預覽的計算變數改為與伺服器端相同的整數運算。** 權威實作
  `CalculateSurveyResponseAction` 全程以 `(int)` 累加分數，預覽先前用 `Number()`
  保留小數：`score_delta` 設成 2.6 時，設計者在預覽看到 2.6，受訪者實際拿到 2。
  對測驗／診斷型問卷來說這代表整份產出的分數是錯的，而且兩邊都不會報錯。
  分級門檻（`grade_map_json` 的 min/max）也一併對齊整數轉型。

### Added

- `tests/Fixtures/preview-calculation-consistency.json`：計算變數的跨實作共用
  fixture。PHP 側跑完整權威路徑（存草稿 → 發布 → 計算），預覽側透過
  `renderCalculationTokens` 取值，同一份 schema 與作答情境比對兩邊結果。

## [1.5.1] - 2026-08-01

### Added

- CI 新增 JS tests job。共用的 `php-package-ci` 只跑 PHP，Builder 前端的 138 個
  vitest 測試先前完全不在 CI 覆蓋範圍內——曾有四個測試檔長期整檔載入失敗而無人察覺。

### Changed

- `@lalalili/builder-ui-core` 的 specifier 由 `git+ssh` 改為 `git+https`。該 repo
  是公開的，ssh 只是本機習慣，CI 沒有金鑰會裝不起來。
- 把頁面層 `jump_rules` 的測試標示為「刻意未實作」而非「已知落差」：建立器沒有
  任何 UI 能建立這種資料，維持現況是產品決策，不是待辦。

## [1.5.0] - 2026-08-01

### Fixed

建立器預覽的顯示條件與跳題判定原本與伺服器端不一致，導致「預覽看到的」與
「受訪者實際看到的」不同。以下全部改為對齊權威實作
`Lalalili\SurveyCore\Support\ConditionGroupEvaluator` 與 `JumpLogicResolver`：

- **未作答的目標題目不再讓否定運算子提前成立。** 先前 `not_equals`、
  `not_contains` 在目標題目尚未作答時就會成立，`greater_than` 也會把未作答當成
  0 來比較，使被條件控制的題目在預覽中提前出現。
- **巢狀條件群組不再被整組略過。** 先前預覽把 `conditions` 當成扁平陣列，巢狀
  群組節點沒有 `field_key`，取值後以空字串比對而恆為真，等於該群組不存在。
- **支援 `greater_than_or_equal` / `less_than_or_equal`（含 `>=`、`<=`）。**
  先前這些運算子會掉進 default 而被當成 `equals` 比較。
- **`contains` 不再把陣列 join 後當字串比對。** 先前 `['a','b']` 會誤中 `'a,b'`。
- **`is_empty` 改為先 trim。** 純空白字串先前被當成已作答。
- **`select` 題的選項跳題在預覽中生效。** 先前只掃描 `single_choice`，但
  `JUMP_SUPPORTED_TYPES`、右側面板與伺服器端都支援 `select`。

### Added

- `tests/Fixtures/preview-condition-consistency.json`：條件求值的跨實作共用
  fixture，同時餵給 `tests/Feature/PreviewConditionConsistencyTest.php`（PHP，
  斷言權威行為）與 `tests/Unit/previewConditionConsistency.test.ts`（預覽側）。
  日後兩邊再度分歧會被這組測試擋下。

### Known limitations

- 預覽仍未實作**頁面層的 `jump_rules`**（伺服器端的 `JumpLogicResolver` 有支援，
  含條件群組）。已在 `previewConditionConsistency.test.ts` 以測試釘住現況。

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
