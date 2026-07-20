/// <reference types="vitest" />
import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
  plugins: [vue()],
  resolve: {
    // vue 與 @vue/server-renderer 必須是同一份實例，否則 renderToString 走到
    // 另一份 runtime-core 的內部狀態，slot 渲染會炸在 "Cannot read properties of null"。
    dedupe: ['vue', '@vue/server-renderer'],
    alias: {
      // SurveyBuilderApp.vue 從共用套件取 BuilderShell；測試直接指到原始碼，
      // 不必先 build builder-ui-core。
      '@builder-ui-core': path.resolve(__dirname, '../builder-ui-core/src'),
    },
  },
  test: {
    environment: 'jsdom',
    globals: true,
    include: ['tests/**/*.test.ts'],
  },
});
