/// <reference types="vitest" />
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    // vue 與 @vue/server-renderer 必須是同一份實例，否則 renderToString 走到
    // 另一份 runtime-core 的內部狀態，slot 渲染會炸在 "Cannot read properties of null"。
    dedupe: ['vue', '@vue/server-renderer'],
    alias: {
      // SurveyBuilderApp.vue 從共用套件取 BuilderShell。`@builder-ui-core` 是主
      // 應用 vite.config 的別名慣例（非套件名），這裡對應到 devDependency 安裝的
      // @lalalili/builder-ui-core；其 exports 直接指向 src/index.ts，不必先 build。
      // 原本寫成 '../builder-ui-core/src'，仰賴套件還住在 lxm-survey/packages/ 底下
      // 的同層 checkout；改用 Satis 獨立 repo 後該路徑不存在，測試會整檔載入失敗。
      '@builder-ui-core': '@lalalili/builder-ui-core',
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['tests/**/*.test.ts'],
  },
});
