// @vitest-environment jsdom

import { afterEach, describe, expect, it, vi } from 'vitest';
import { createBuilderApi, ValidationError } from '../../resources/js/builder/api/builderApi';

const endpoints = {
  show: '/builder',
  update: '/builder',
  publish: '/publish',
} as Parameters<typeof createBuilderApi>[0];

describe('builder API errors', () => {
  afterEach(() => {
    vi.restoreAllMocks();
  });

  it('keeps validation messages and field errors readable', async () => {
    vi.spyOn(window, 'fetch').mockResolvedValue(new Response(JSON.stringify({
      message: '問卷至少需要一個題目頁。',
      errors: {
        pages: ['刪除基本資料分頁後，問卷至少需要一個題目頁。'],
      },
    }), {
      status: 422,
      headers: { 'Content-Type': 'application/json' },
    }));

    const request = createBuilderApi(endpoints, { csrfToken: 'token' }).publish();

    await expect(request).rejects.toMatchObject<ValidationError>({
      message: '問卷至少需要一個題目頁。',
      errors: {
        pages: ['刪除基本資料分頁後，問卷至少需要一個題目頁。'],
      },
    });
  });

  it('does not expose SQL details from server errors', async () => {
    vi.spyOn(window, 'fetch').mockResolvedValue(new Response(JSON.stringify({
      message: 'SQLSTATE[42000]: select * from sensitive_table',
    }), {
      status: 500,
      headers: { 'Content-Type': 'application/json' },
    }));

    const request = createBuilderApi(endpoints, { csrfToken: 'token' }).publish();

    await expect(request).rejects.toThrow('系統暫時無法完成儲存，請稍後再試。');
    await expect(request).rejects.not.toThrow('SQLSTATE');
  });
});
