# Changelog

All notable changes to `lalalili/survey-filament` will be documented in this file.

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
