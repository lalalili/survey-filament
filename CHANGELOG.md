# Changelog

All notable changes to `lalalili/survey-filament` will be documented in this file.

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
