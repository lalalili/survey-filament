# 升級指南

## 1.2.x → 1.3.0

### 宿主端要做的事

```bash
composer update lalalili/survey-filament
php artisan optimize:clear
php artisan filament:cache-components
npm run build   # 或 pnpm run build，Builder 前端有變更
```

### 破壞性變更

移除路由 `survey-filament.builder.update-current`（`PUT /{survey}/builder`）。
若你曾在宿主端以此名稱產生網址，改用 `survey-filament.builder.update`
（`PUT /{survey}/builder-schema`），兩者對應同一個 controller action。

Builder 前端不再從 `window.location.pathname` 反推 API 網址，改為完全依賴
掛載點的 `data-endpoint-*` 屬性。若你**覆寫**了
`survey-filament::edit-survey-builder` 這個 view，必須確認以下 8 個屬性都有
輸出，否則 Builder 會在啟動時拋錯而非默默打到錯的網址：

`data-endpoint-show`、`data-endpoint-update`、`data-endpoint-publish`、
`data-endpoint-activities`、`data-endpoint-restore-published`、
`data-endpoint-upload-image`、`data-endpoint-cascade-template`、
`data-endpoint-cascade-import`。

未覆寫 view 的宿主不需要任何調整。

## 0.x → 1.0.0

### 宿主端要做的事

1. 把 `composer.json` 的約束改成 `^1.0`：

   ```diff
   -"lalalili/survey-filament": "^0.x"
   +"lalalili/survey-filament": "^1.0"
   ```

2. 確認 `repositories` 是 `vcs` 而非指向本機 `packages/` 的 `path`：

   ```json
   { "type": "vcs", "url": "https://github.com/lalalili/survey-filament.git" }
   ```

3. 更新並清快取：

   ```bash
   composer update lalalili/survey-filament
   php artisan optimize:clear
   composer dump-autoload
   php artisan filament:cache-components   # 有 Filament 後台時必跑
   ```

### 破壞性變更

本次 1.0.0 **沒有移除或變更任何 public API**，純粹是版本契約與
消費模型的正規化。程式碼層面不需要調整。

若你原本用 `path` repository 搭配硬釘 `versions` 消費本套件，請改為
VCS + tag —— 前者會讓宿主停在舊版且完全不會有任何警告。

### 之後

public API 的定義、deprecation 流程與跨套件發版順序見
[SEMVER.md](https://github.com/lalalili/.github/blob/main/SEMVER.md)。
