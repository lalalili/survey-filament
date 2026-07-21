// @vitest-environment jsdom

import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { ValidationError } from '../../resources/js/builder/api/builderApi';
import { useSurveyBuilderStore } from '../../resources/js/builder/stores/useSurveyBuilderStore';

function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((promiseResolve) => {
    resolve = promiseResolve;
  });

  return { promise, resolve };
}

function savePayload(content: string, title = '問卷') {
  return {
    schema: {
      title,
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

function questionSchema() {
  return {
    title: '問卷',
    pages: [{
      id: 'page-1',
      kind: 'question',
      title: '第一頁',
      elements: [{
        id: 'question-1',
        type: 'short_text',
        field_key: 'question_1',
        label: '問題一',
        description: '',
        required: false,
        options: [],
        settings: {},
        show_if: null,
      }],
    }],
  };
}

describe('survey builder autosave', () => {
  beforeEach(() => {
    setActivePinia(createPinia());
    vi.useFakeTimers();
  });

  it('hydrates field impacts and explains that answered questions are retired instead of erased', async () => {
    const store = useSurveyBuilderStore();
    store.api = {
      load: vi.fn().mockResolvedValue({
        survey: { id: 1, title: '問卷', status: 'published', version: 2, published_at: null },
        schema: { ...questionSchema(), id: 1, status: 'published', version: 2 },
        field_impacts: {
          'question-1': {
            element_id: 'question-1',
            field_key: 'question_1',
            answer_count: 315,
            response_count: 300,
            locked_properties: ['field_key', 'type', 'used_option_values'],
          },
        },
      }),
    } as typeof store.api;

    await store.loadBuilder();

    expect(store.fieldImpact('question-1')).toMatchObject({ answer_count: 315, response_count: 300 });
    expect(store.questionRemovalMessage('question-1'))
      .toContain('315 筆歷史答案')
      .toContain('發布後此題將退役')
      .toContain('歷史答案會完整保留');
  });

  it('summarizes answered fields before removing a page and keeps normal deletion unchanged', () => {
    const store = useSurveyBuilderStore();
    store.schema = questionSchema() as typeof store.schema;
    store.fieldImpacts = {
      'question-1': {
        element_id: 'question-1',
        field_key: 'question_1',
        answer_count: 120,
        response_count: 100,
        locked_properties: ['field_key', 'type'],
      },
    };

    expect(store.pageRemovalMessage('page-1'))
      .toContain('1 道題目包含歷史答案')
      .toContain('共 120 筆答案')
      .toContain('發布後這些題目將退役');

    store.fieldImpacts = {};
    expect(store.questionRemovalMessage('question-1')).toBeNull();
    expect(store.pageRemovalMessage('page-1')).toBeNull();
  });

  it('normalizes text length limits to non-negative integers', () => {
    const store = useSurveyBuilderStore();
    store.schema = questionSchema() as typeof store.schema;

    store.updateElementValidationRules('question-1', {
      min_length: -3,
      max_length: 12.8,
    });

    expect(store.allElements[0]?.validation_rules).toMatchObject({
      min_length: 0,
      max_length: 12,
    });
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
    await vi.advanceTimersByTimeAsync(2000);

    const imageContent = '<p>準備上傳圖片</p><img src="https://example.test/welcome.jpg">';
    store.updatePage('welcome', {
      welcome_settings: { content: imageContent },
    });
    store.updateSurveyTitle('更新後問卷');

    firstSave.resolve(savePayload('<p>準備上傳圖片</p>'));
    await firstSave.promise;
    await vi.advanceTimersByTimeAsync(0);

    expect(store.welcomePage?.welcome_settings?.content).toBe(imageContent);
    expect(store.surveyTitle).toBe('更新後問卷');
    expect(store.isDirty).toBe(true);

    await vi.advanceTimersByTimeAsync(2000);
    secondSave.resolve(savePayload(imageContent, '更新後問卷'));
    await secondSave.promise;
    await vi.advanceTimersByTimeAsync(0);

    expect(save).toHaveBeenCalledTimes(2);
    expect(store.welcomePage?.welcome_settings?.content).toBe(imageContent);
    expect(store.surveyTitle).toBe('更新後問卷');
    expect(store.isDirty).toBe(false);
  });

  it('loads the editable title from the draft schema', async () => {
    const store = useSurveyBuilderStore();
    store.api = {
      load: vi.fn().mockResolvedValue({
        ...savePayload('<p>草稿內容</p>', '草稿問卷標題'),
        survey: {
          id: 1,
          title: '目前正式標題',
          status: 'published',
          version: 2,
          published_at: null,
        },
      }),
    } as typeof store.api;

    await store.loadBuilder();

    expect(store.surveyTitle).toBe('草稿問卷標題');
  });

  it('hydrates a uniquely matching audience list on load without writing during GET', async () => {
    const store = useSurveyBuilderStore();
    const save = vi.fn();
    const load = vi.fn().mockResolvedValue({
      survey: {
        id: 2,
        title: 'SSI 問卷',
        status: 'draft',
        version: 1,
        published_at: null,
      },
      schema: {
        ...questionSchema(),
        id: 2,
        status: 'draft',
        version: 1,
        settings: { category: 'SSI' },
      },
      audience_lists: [{
        id: 2,
        name: 'SSI 名單',
        schema_profile: 'SSI',
        columns: [
          { key: 'dlr', type: 'string' },
          { key: 'dept', type: 'string' },
          { key: 'regono', type: 'string' },
          { key: 'delivery_date', type: 'date' },
        ],
      }],
    });

    store.api = { load, save } as typeof store.api;
    await store.loadBuilder();
    await vi.advanceTimersByTimeAsync(3000);

    expect(load).toHaveBeenCalledTimes(1);
    expect(save).not.toHaveBeenCalled();
    expect(store.schema?.settings?.personalization).toMatchObject({
      audience_list_id: 2,
      result_context_columns: {
        dealer: 'dlr',
        location: 'dept',
        vehicle_plate: 'regono',
        delivery_date: 'delivery_date',
      },
    });
    expect(store.isDirty).toBe(true);
    expect(store.hasUnpublishedChanges).toBe(true);
  });

  it('keeps an incomplete display condition local without triggering autosave', async () => {
    const store = useSurveyBuilderStore();
    const save = vi.fn();

    store.api = { save } as typeof store.api;
    store.schema = questionSchema() as typeof store.schema;

    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: '' }],
    });
    await vi.advanceTimersByTimeAsync(5000);

    expect(save).not.toHaveBeenCalled();
    expect(store.allElements[0]?.show_if).toBeNull();
    expect(store.showIfEditorValue('question-1')?.conditions[0]?.value).toBe('');
    expect(store.hasPendingShowIfDrafts).toBe(true);
    expect(store.hasUnsavedChanges).toBe(true);
  });

  it('commits a completed display condition and autosaves it after two seconds', async () => {
    const store = useSurveyBuilderStore();
    const save = vi.fn().mockResolvedValue({
      ...savePayload(''),
      schema: questionSchema(),
    });

    store.api = { save } as typeof store.api;
    store.schema = questionSchema() as typeof store.schema;

    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: '' }],
    });
    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: 'yes' }],
    });

    expect(store.hasPendingShowIfDrafts).toBe(false);
    expect(store.allElements[0]?.show_if?.conditions[0]).toMatchObject({ value: 'yes' });

    await vi.advanceTimersByTimeAsync(1999);
    expect(save).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(1);
    expect(save).toHaveBeenCalledTimes(1);
  });

  it('treats empty-value operators as complete without requiring a value', () => {
    const store = useSurveyBuilderStore();

    store.schema = questionSchema() as typeof store.schema;
    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'is_empty', value: '' }],
    });

    expect(store.hasPendingShowIfDrafts).toBe(false);
    expect(store.allElements[0]?.show_if?.conditions[0]).toMatchObject({ op: 'is_empty' });
  });

  it('keeps the saved condition unchanged while an edited value is incomplete', () => {
    const store = useSurveyBuilderStore();
    const schema = questionSchema();
    schema.pages[0].elements[0].show_if = {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: 'old' }],
    };
    store.schema = schema as typeof store.schema;

    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: '' }],
    });

    expect(store.allElements[0]?.show_if?.conditions[0]).toMatchObject({ value: 'old' });
    expect(store.showIfEditorValue('question-1')?.conditions[0]).toMatchObject({ value: '' });

    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: 'new' }],
    });

    expect(store.allElements[0]?.show_if?.conditions[0]).toMatchObject({ value: 'new' });
    expect(store.hasPendingShowIfDrafts).toBe(false);
  });

  it('discards an incomplete new condition without scheduling autosave', async () => {
    const store = useSurveyBuilderStore();
    const save = vi.fn();

    store.api = { save } as typeof store.api;
    store.schema = questionSchema() as typeof store.schema;
    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: '' }],
    });
    store.stageShowIf('question-1', null);
    await vi.advanceTimersByTimeAsync(2000);

    expect(store.hasPendingShowIfDrafts).toBe(false);
    expect(store.hasUnsavedChanges).toBe(false);
    expect(save).not.toHaveBeenCalled();
  });

  it('blocks publishing and focuses the first incomplete display condition', async () => {
    const store = useSurveyBuilderStore();
    const publish = vi.fn();

    store.api = { publish } as typeof store.api;
    store.schema = questionSchema() as typeof store.schema;
    store.stageShowIf('question-1', {
      logic: 'and',
      conditions: [{ field_key: 'source_question', op: 'equals', value: '' }],
    });

    await store.publish();

    expect(publish).not.toHaveBeenCalled();
    expect(store.selectedPageId).toBe('page-1');
    expect(store.selectedElementId).toBe('question-1');
    expect(store.rightPanelTab).toBe('logic');
    expect(store.publishError).toContain('尚有未完成的顯示條件');
  });

  it('reports autosave validation errors without opening a blocking alert', async () => {
    const store = useSurveyBuilderStore();
    const alert = vi.spyOn(window, 'alert').mockImplementation(() => undefined);
    const save = vi.fn().mockRejectedValue(new ValidationError('Validation failed.', {
      'pages.0.elements.0.personalized_key': ['請設定對應名單欄位。'],
    }));

    store.api = { save } as typeof store.api;
    store.schema = questionSchema() as typeof store.schema;
    store.updateQuestion('question-1', { label: '更新後問題' });
    await vi.advanceTimersByTimeAsync(2000);

    expect(alert).not.toHaveBeenCalled();
    expect(store.saveError).toBe('Validation failed.');
    expect(store.validationErrors).toHaveProperty('pages.0.elements.0.personalized_key');
  });
});
