// @vitest-environment jsdom

import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((promiseResolve) => {
    resolve = promiseResolve;
  });

  return { promise, resolve };
}

function savePayload(content: string) {
  return {
    schema: {
      title: '問卷',
      pages: [{
        id: 'welcome',
        kind: 'welcome',
        title: '',
        elements: [],
        welcome_settings: { content },
      }],
    },
    survey: {
      title: '問卷',
      status: 'draft',
      version: 1,
      published_at: null,
    },
    saved_at: '2026-07-17T17:30:00+08:00',
  };
}

describe('survey builder autosave', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.useFakeTimers();
  });

  it('does not overwrite changes made while an autosave request is in flight', async () => {
    const firstSave = deferred<ReturnType<typeof savePayload>>();
    const secondSave = deferred<ReturnType<typeof savePayload>>();
    const store = useSurveyBuilderStore();
    const save = vi.fn()
      .mockReturnValueOnce(firstSave.promise)
      .mockReturnValueOnce(secondSave.promise);

    store.api = { save } as typeof store.api;
    store.schema = savePayload('<p>原始內容</p>').schema as typeof store.schema;

    store.updatePage('welcome', {
      welcome_settings: { content: '<p>準備上傳圖片</p>' },
    });
    await vi.advanceTimersByTimeAsync(1000);

    const imageContent = '<p>準備上傳圖片</p><img src="https://example.test/welcome.jpg">';
    store.updatePage('welcome', {
      welcome_settings: { content: imageContent },
    });

    firstSave.resolve(savePayload('<p>準備上傳圖片</p>'));
    await firstSave.promise;
    await vi.advanceTimersByTimeAsync(0);

    expect(store.welcomePage?.welcome_settings?.content).toBe(imageContent);
    expect(store.isDirty).toBe(true);

    await vi.advanceTimersByTimeAsync(1000);
    secondSave.resolve(savePayload(imageContent));
    await secondSave.promise;
    await vi.advanceTimersByTimeAsync(0);

    expect(save).toHaveBeenCalledTimes(2);
    expect(store.welcomePage?.welcome_settings?.content).toBe(imageContent);
    expect(store.isDirty).toBe(false);
  });
});
