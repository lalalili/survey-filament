import { defineStore } from 'pinia';
import { ValidationError, createBuilderApi } from '../api/builderApi';
import { getQuestionType } from '../registry/questionTypes';
import type { AudienceListSummary, BuilderActivity, BuilderCapabilities, BuilderEndpoints, Condition, FieldImpact, SurveyBuilderSchema, SurveyElement, SurveyOptionAction, SurveyPage, SurveySettings, SurveyTheme } from '../types/schema';
import { hydratePersonalizationSettings, isSystemContextField, visibleSurveyElements } from '../utils/systemContextFields';

type BuilderApi = ReturnType<typeof createBuilderApi>;

interface QuestionTypeGroup {
  label: string;
  types: string[];
}

interface EditableShowIf {
  logic: 'and' | 'or';
  conditions: Condition[];
}

let autosaveTimer: number | undefined;
let autosavePromise: Promise<void> | undefined;
let schemaRevision = 0;

function isShowIfComplete(showIf: EditableShowIf): boolean {
  return showIf.conditions.length > 0 && showIf.conditions.every((condition) => {
    if (condition.field_key.trim() === '') {
      return false;
    }

    if (condition.op === 'is_empty' || condition.op === 'is_not_empty') {
      return true;
    }

    return String(condition.value ?? '').trim() !== '';
  });
}

function clearAutosaveTimer(): void {
  window.clearTimeout(autosaveTimer);
  autosaveTimer = undefined;
}

function cloneElement(element: SurveyElement): SurveyElement {
  return JSON.parse(JSON.stringify(element)) as SurveyElement;
}

function normalizeLegacyElement(element: SurveyElement): SurveyElement {
  if (element.type === 'email') {
    return {
      ...element,
      type: 'short_text',
      settings: { ...(element.settings ?? {}), input_format: 'email', input_mode: 'email' },
    };
  }

  if (element.type === 'phone') {
    return {
      ...element,
      type: 'short_text',
      settings: {
        ...(element.settings ?? {}),
        input_format: 'mobile_tw',
        input_mode: 'numeric',
        minlength: 10,
        maxlength: 10,
        pattern: '09[0-9]{8}',
      },
    };
  }

  return element;
}

export const useSurveyBuilderStore = defineStore('survey-builder', {
  state: () => ({
    api: null as BuilderApi | null,
    surveyId: null as number | string | null,
    surveyTitle: '',
    status: 'draft',
    version: 1,
    schema: null as SurveyBuilderSchema | null,
    googleDrive: { connected: false, email: null as string | null | undefined, configured: false },
    themes: [] as SurveyTheme[],
    audienceLists: [] as AudienceListSummary[],
    capabilities: {
      can_manage_advanced_fields: false,
      is_super_admin: false,
      question_types: [],
    } as BuilderCapabilities,
    fieldImpacts: {} as Record<string, FieldImpact>,
    selectedPageId: null as string | null,
    selectedElementId: null as string | null,
    isDirty: false,
    hasUnpublishedChanges: false,
    isSaving: false,
    saveError: '',
    publishError: '',
    validationErrors: {} as Record<string, string[]>,
    lastSavedAt: null as string | null,
    isPreviewMode: false,
    isLoading: false,
    isPublishing: false,
    activities: [] as BuilderActivity[],
    canRestorePublished: false,
    publishedAt: null as string | null,
    isLoadingActivities: false,
    activitiesError: '',
    isRestoringPublished: false,
    rightPanelTab: 'library' as 'library' | 'properties' | 'logic',
    jumpLogicOpen: false,
    showSettingsModal: false,
    isMobilePreview: false,
    showIfDrafts: {} as Record<string, EditableShowIf>,
  }),
  getters: {
    selectedPage(state): SurveyPage | null {
      return state.schema?.pages.find((page) => page.id === state.selectedPageId) ?? state.schema?.pages[0] ?? null;
    },
    selectedElement(): SurveyElement | null {
      const page = this.selectedPage;
      return page?.elements.find((element) => element.id === this.selectedElementId && !isSystemContextField(element)) ?? null;
    },
    allElements(state): SurveyElement[] {
      return state.schema?.pages.flatMap((page) => visibleSurveyElements(page.elements)) ?? [];
    },
    questionPages(state): SurveyPage[] {
      return state.schema?.pages.filter((page) => (page.kind ?? 'question') === 'question') ?? [];
    },
    welcomePage(state): SurveyPage | null {
      return state.schema?.pages.find((page) => page.kind === 'welcome') ?? null;
    },
    thankYouPage(state): SurveyPage | null {
      return state.schema?.pages.find((page) => page.kind === 'thank_you') ?? null;
    },
    hasUnsavedChanges(state): boolean {
      return state.isDirty || Object.keys(state.showIfDrafts).length > 0;
    },
    hasPendingShowIfDrafts(state): boolean {
      return Object.keys(state.showIfDrafts).length > 0;
    },
  },
  actions: {
    configure(endpoints: BuilderEndpoints, csrfToken: string) {
      this.api = createBuilderApi(endpoints, { csrfToken });
    },
    async loadBuilder() {
      if (!this.api) {
        throw new Error('Builder API is not configured.');
      }

      this.isLoading = true;
      const payload = await this.api.load();

      this.surveyId = payload.survey.id;
      this.surveyTitle = payload.survey.title;
      this.status = payload.survey.status;
      this.version = payload.survey.version;
      this.publishedAt = payload.survey.published_at ?? null;
      this.googleDrive = {
        connected: payload.survey.google_drive?.connected ?? false,
        email: payload.survey.google_drive?.email ?? null,
        configured: payload.survey.google_drive?.configured ?? false,
      };
      this.schema = payload.schema;
      this.schema.settings ??= { progress: { mode: 'bar', show_estimated_time: true } };
      this.schema.settings.progress ??= { mode: 'bar', show_estimated_time: true };
      this.schema.settings.show_question_numbers ??= true;
      this.schema.settings.allow_back ??= true;
      this.schema.settings.language ??= 'zh-TW';
      this.schema.settings.uniqueness_mode ??= 'none';
      this.schema.settings.anomaly ??= { min_seconds: null, detect_duplicate: 'cookie', turnstile: false };
      if ('close_at' in this.schema.settings && !this.schema.settings.ends_at) {
        this.schema.settings.ends_at = (this.schema.settings as Record<string, unknown>).close_at as string | null;
        delete (this.schema.settings as Record<string, unknown>).close_at;
      }
      this.schema.theme_overrides ??= {};
      this.schema.calculations ??= [];
      this.schema.thank_you_branches ??= [];
      this.schema.pages.forEach((page) => {
        page.kind ??= 'question';
        page.jump_rules ??= [];
        page.elements = page.elements.map((element) => normalizeLegacyElement(element));
      });
      this.themes = payload.themes ?? [];
      this.audienceLists = payload.audience_lists ?? [];
      this.fieldImpacts = payload.field_impacts ?? {};
      const personalizationHydrated = hydratePersonalizationSettings(this.schema.settings, this.audienceLists);
      this.capabilities = {
        can_manage_advanced_fields: payload.capabilities?.can_manage_advanced_fields ?? false,
        is_super_admin: payload.capabilities?.is_super_admin ?? false,
        question_types: payload.capabilities?.question_types ?? [],
      };
      this.selectedPageId = payload.schema.pages[0]?.id ?? null;
      this.selectedElementId = null;
      this.showIfDrafts = {};
      schemaRevision = 0;
      this.isDirty = personalizationHydrated;
      this.hasUnpublishedChanges = personalizationHydrated;
      this.saveError = '';
      this.publishError = '';
      this.validationErrors = {};
      this.isLoading = false;
    },
    updateSurveyTitle(title: string) {
      this.surveyTitle = title;

      if (this.schema) {
        this.schema.title = title;
      }

      this.markDirty();
    },
    addQuestion(questionTypeId: string) {
      const page = this.selectedPage;
      if (!page) {
        return;
      }

      const element = getQuestionType(questionTypeId).createDefault();
      page.elements.push(element);
      this.selectedElementId = element.id;
      this.markDirty();
    },
    replaceQuestionPagesWithAllQuestionTypes(groups: QuestionTypeGroup[]) {
      if (!this.schema) {
        return;
      }

      const newQuestionPages: SurveyPage[] = groups.map((group) => ({
        id: `page_${Math.random().toString(36).slice(2, 9)}`,
        kind: 'question',
        title: group.label,
        elements: group.types.map((type) => getQuestionType(type).createDefault()),
        jump_rules: [],
      }));

      this.schema.pages = [
        ...this.schema.pages.filter((page) => page.kind === 'welcome'),
        ...newQuestionPages,
        ...this.schema.pages.filter((page) => page.kind === 'thank_you'),
      ];
      this.selectedPageId = newQuestionPages[0]?.id ?? this.schema.pages[0]?.id ?? null;
      this.selectedElementId = null;
      this.markDirty();
    },
    updateQuestion(questionId: string, patch: Partial<SurveyElement>) {
      const element = this.allElements.find((candidate) => candidate.id === questionId);
      if (!element) {
        return;
      }

      Object.assign(element, patch);
      this.markDirty();
    },
    duplicateQuestion(questionId: string) {
      const page = this.selectedPage;
      const index = page?.elements.findIndex((element) => element.id === questionId) ?? -1;

      if (!page || index < 0) {
        return;
      }

      const duplicated = cloneElement(page.elements[index]);
      duplicated.id = `q_${Math.random().toString(36).slice(2, 9)}`;

      if (duplicated.field_key) {
        duplicated.field_key = `question_${Math.random().toString(36).slice(2, 9)}`;
      }

      page.elements.splice(index + 1, 0, duplicated);
      this.selectedElementId = duplicated.id;
      this.markDirty();
    },
    removeQuestion(questionId: string) {
      const page = this.selectedPage;
      const index = page?.elements.findIndex((element) => element.id === questionId) ?? -1;

      if (!page || index < 0) {
        return;
      }

      delete this.showIfDrafts[questionId];
      page.elements.splice(index, 1);
      this.selectedElementId = null;
      this.markDirty();
    },
    fieldImpact(elementId: string): FieldImpact | null {
      return this.fieldImpacts[elementId] ?? null;
    },
    questionRemovalMessage(questionId: string): string | null {
      const impact = this.fieldImpact(questionId);

      if (!impact || impact.answer_count <= 0) {
        return null;
      }

      return `此題已有 ${impact.answer_count} 筆歷史答案（${impact.response_count} 份回覆）。刪除後需發布才會生效；發布後此題將退役、不再顯示於新填答，但歷史答案會完整保留。確定刪除？`;
    },
    pageRemovalMessage(pageId: string): string | null {
      const page = this.schema?.pages.find((candidate) => candidate.id === pageId);
      const impacts = (page?.elements ?? [])
        .map((element) => this.fieldImpact(element.id))
        .filter((impact): impact is FieldImpact => impact !== null && impact.answer_count > 0);

      if (impacts.length === 0) {
        return null;
      }

      const answerCount = impacts.reduce((total, impact) => total + impact.answer_count, 0);
      const responseCount = impacts.reduce((highest, impact) => Math.max(highest, impact.response_count), 0);

      return `此頁有 ${impacts.length} 道題目包含歷史答案，共 ${answerCount} 筆答案（涉及至少 ${responseCount} 份回覆）。刪除後需發布才會生效；發布後這些題目將退役、不再顯示於新填答，但歷史答案會完整保留。確定刪除此頁？`;
    },
    selectElement(elementId: string) {
      this.selectedElementId = elementId;
      this.isPreviewMode = false;
    },
    clearSelection() {
      this.selectedElementId = null;
    },
    markDirty() {
      schemaRevision += 1;
      this.isDirty = true;
      this.hasUnpublishedChanges = true;
      this.saveError = '';
      this.publishError = '';
      this.validationErrors = {};
      this.scheduleAutosave();
    },
    scheduleAutosave() {
      window.clearTimeout(autosaveTimer);
      autosaveTimer = window.setTimeout(() => {
        void this.autosave();
      }, 2000);
    },
    async autosave() {
      if (this.isSaving) {
        await autosavePromise;
        return;
      }

      if (!this.api || !this.schema || !this.isDirty) {
        return;
      }

      this.isSaving = true;
      const savingRevision = schemaRevision;
      autosavePromise = (async () => {
        try {
          const payload = await this.api!.save(this.schema!);
          const hasNewerChanges = schemaRevision !== savingRevision;

          if (!hasNewerChanges) {
            this.schema = payload.schema;
          }

          this.surveyTitle = payload.survey.title;
          this.status = payload.survey.status;
          this.version = payload.survey.version;
          this.publishedAt = payload.survey.published_at ?? this.publishedAt;
          this.lastSavedAt = payload.saved_at;
          this.isDirty = hasNewerChanges;
          this.validationErrors = {};

          if (hasNewerChanges) {
            this.scheduleAutosave();
          }
        } catch (error) {
          if (error instanceof ValidationError) {
            this.saveError = error.message;
            this.validationErrors = error.errors;
          } else {
            this.saveError = error instanceof Error ? error.message : 'Save failed.';
            this.validationErrors = {};
          }
        } finally {
          this.isSaving = false;
          autosavePromise = undefined;
        }
      })();

      await autosavePromise;
    },
    async publish() {
      if (!this.api || this.isPublishing) {
        return;
      }

      if (this.hasPendingShowIfDrafts) {
        const elementId = Object.keys(this.showIfDrafts)[0];
        const page = this.schema?.pages.find((candidate) => candidate.elements.some((element) => element.id === elementId));

        this.selectedPageId = page?.id ?? this.selectedPageId;
        this.selectedElementId = elementId ?? this.selectedElementId;
        this.rightPanelTab = 'logic';
        this.publishError = '尚有未完成的顯示條件，請完成或刪除後再發布。';

        return;
      }

      if (this.status === 'published' && !this.isDirty && !this.hasUnpublishedChanges) {
        return;
      }

      if (this.isDirty || this.isSaving) {
        clearAutosaveTimer();
        await this.autosave();
      }

      if (this.saveError || this.isDirty) {
        return;
      }

      this.isPublishing = true;
      this.publishError = '';

      try {
        const payload = await this.api.publish();
        this.schema = payload.schema;
        this.status = payload.survey.status;
        this.version = payload.survey.version;
        this.publishedAt = payload.survey.published_at ?? this.publishedAt;
        this.isDirty = false;
        this.hasUnpublishedChanges = false;
        this.validationErrors = {};
        this.publishError = '';
        await this.loadActivities();
      } catch (error) {
        if (error instanceof ValidationError) {
          this.publishError = error.message;
          this.validationErrors = error.errors;
        } else {
          this.publishError = error instanceof Error ? error.message : 'Publish failed.';
          this.validationErrors = {};
        }
      } finally {
        this.isPublishing = false;
      }
    },
    async loadActivities() {
      if (!this.api) {
        return;
      }

      this.isLoadingActivities = true;
      this.activitiesError = '';

      try {
        const payload = await this.api.listActivities();
        this.activities = payload.items;
        this.canRestorePublished = payload.can_restore_published;
        this.publishedAt = payload.published_at;
        this.version = payload.current_version;
      } catch (error) {
        this.activitiesError = error instanceof Error ? error.message : '載入編輯紀錄失敗。';
      } finally {
        this.isLoadingActivities = false;
      }
    },
    async restorePublished() {
      if (!this.api || this.isRestoringPublished || !this.canRestorePublished) {
        return;
      }

      clearAutosaveTimer();
      this.isRestoringPublished = true;
      this.saveError = '';
      this.publishError = '';
      this.validationErrors = {};

      try {
        const payload = await this.api.restorePublished();
        this.schema = payload.schema;
        this.surveyTitle = payload.survey.title;
        this.status = payload.survey.status;
        this.version = payload.survey.version;
        this.publishedAt = payload.survey.published_at ?? this.publishedAt;
        this.selectedPageId = payload.schema.pages[0]?.id ?? null;
        this.selectedElementId = null;
        this.showIfDrafts = {};
        this.isDirty = false;
        this.hasUnpublishedChanges = false;
        await this.loadActivities();
      } catch (error) {
        this.activitiesError = error instanceof Error ? error.message : '回復至目前發布版本失敗。';
      } finally {
        this.isRestoringPublished = false;
      }
    },

    googleDriveConnectUrl(): string | null {
      return this.api?.googleDriveConnectUrl() ?? null;
    },

    async refreshGoogleDrive() {
      if (!this.api) return;
      try {
        const status = await this.api.googleDriveStatus();
        this.googleDrive = { connected: status.connected, email: status.email ?? null, configured: status.configured };
      } catch {
        // 靜默：保留現有狀態。
      }
    },

    async disconnectGoogleDrive() {
      if (!this.api) return;
      try {
        await this.api.googleDriveDisconnect();
        this.googleDrive = { ...this.googleDrive, connected: false, email: null };
      } catch {
        // 靜默。
      }
    },
    updateOptionAction(elementId: string, optionId: string, action: SurveyOptionAction | null) {
      const element = this.allElements.find((el) => el.id === elementId);
      if (!element) return;
      const option = element.options.find((opt) => opt.id === optionId);
      if (!option) return;
      option.action = action ?? undefined;
      this.markDirty();
    },
    addPage() {
      if (!this.schema) {
        return;
      }

      const newPage = {
        id: `page_${Math.random().toString(36).slice(2, 9)}`,
        kind: 'question' as const,
        title: `第 ${this.questionPages.length + 1} 頁`,
        elements: [],
      };

      const thankYouIndex = this.schema.pages.findIndex((page) => page.kind === 'thank_you');
      if (thankYouIndex >= 0) {
        this.schema.pages.splice(thankYouIndex, 0, newPage);
      } else {
        this.schema.pages.push(newPage);
      }
      this.selectedPageId = newPage.id;
      this.selectedElementId = null;
      this.markDirty();
    },
    duplicatePage(pageId: string) {
      if (!this.schema) {
        return;
      }

      const index = this.schema.pages.findIndex((p) => p.id === pageId);

      if (index < 0) {
        return;
      }

      const source = this.schema.pages[index];
      if ((source.kind ?? 'question') !== 'question') {
        return;
      }
      const copy: typeof source = JSON.parse(JSON.stringify(source));
      copy.id = `page_${Math.random().toString(36).slice(2, 9)}`;
      copy.title = `${source.title}（副本）`;
      // Regenerate element IDs to avoid duplicates
      copy.elements = copy.elements.map((el) => ({
        ...el,
        id: `q_${Math.random().toString(36).slice(2, 9)}`,
        field_key: el.field_key ? `${el.field_key}_copy` : el.field_key,
      }));

      copy.kind = 'question';
      this.schema.pages.splice(index + 1, 0, copy);
      this.selectedPageId = copy.id;
      this.selectedElementId = null;
      this.markDirty();
    },
    removePage(pageId: string) {
      if (!this.schema || this.questionPages.length <= 1) {
        return;
      }

      const index = this.schema.pages.findIndex((page) => page.id === pageId);

      if (index < 0) {
        return;
      }

      if ((this.schema.pages[index].kind ?? 'question') !== 'question') {
        return;
      }

      this.schema.pages.splice(index, 1);
      const newIndex = Math.min(index, this.schema.pages.length - 1);
      this.selectedPageId = this.schema.pages[newIndex]?.id ?? null;
      this.selectedElementId = null;
      this.markDirty();
    },
    updatePageTitle(pageId: string, title: string) {
      const page = this.schema?.pages.find((p) => p.id === pageId);

      if (!page) {
        return;
      }

      page.title = title;
      this.markDirty();
    },
    addSpecialPage(kind: 'welcome' | 'thank_you') {
      if (!this.schema) {
        return;
      }

      if (this.schema.pages.some((page) => page.kind === kind)) {
        return;
      }

      const page: SurveyPage = {
        id: `${kind === 'welcome' ? 'welcome' : 'thanks'}_${Math.random().toString(36).slice(2, 9)}`,
        kind,
        title: '',
        elements: [],
        welcome_settings: kind === 'welcome' ? { enabled: true, cta_label: '開始填寫', estimated_time_minutes: 5 } : null,
        thank_you_settings: kind === 'thank_you' ? { message: '', redirect_url: null } : null,
      };

      if (kind === 'welcome') {
        this.schema.pages.unshift(page);
      } else {
        this.schema.pages.push(page);
      }

      this.selectedPageId = page.id;
      this.selectedElementId = null;
      this.markDirty();
    },
    updatePage(pageId: string, patch: Partial<SurveyPage>) {
      const page = this.schema?.pages.find((candidate) => candidate.id === pageId);

      if (!page) {
        return;
      }

      Object.assign(page, patch);
      this.markDirty();
    },
    moveQuestionPage(pageId: string, targetPageId: string, position: 'before' | 'after') {
      if (!this.schema || pageId === targetPageId) {
        return;
      }

      const sourceIndex = this.schema.pages.findIndex((page) => page.id === pageId);
      const sourcePage = this.schema.pages[sourceIndex];

      if (sourceIndex < 0 || !sourcePage || (sourcePage.kind ?? 'question') !== 'question') {
        return;
      }

      const targetPage = this.schema.pages.find((page) => page.id === targetPageId);

      if (!targetPage || (targetPage.kind ?? 'question') !== 'question') {
        return;
      }

      this.schema.pages.splice(sourceIndex, 1);

      const targetIndex = this.schema.pages.findIndex((page) => page.id === targetPageId);

      if (targetIndex < 0) {
        this.schema.pages.splice(sourceIndex, 0, sourcePage);

        return;
      }

      const welcomeIndex = this.schema.pages.findIndex((page) => page.kind === 'welcome');
      const thankYouIndex = this.schema.pages.findIndex((page) => page.kind === 'thank_you');
      const minIndex = welcomeIndex >= 0 ? welcomeIndex + 1 : 0;
      const maxIndex = thankYouIndex >= 0 ? thankYouIndex : this.schema.pages.length;
      const requestedIndex = targetIndex + (position === 'after' ? 1 : 0);
      const insertIndex = Math.min(Math.max(requestedIndex, minIndex), maxIndex);

      this.schema.pages.splice(insertIndex, 0, sourcePage);
      this.selectedPageId = sourcePage.id;
      this.selectedElementId = null;
      this.markDirty();
    },
    updateProgressSettings(mode: 'none' | 'bar' | 'steps' | 'percent', showEstimatedTime?: boolean) {
      if (!this.schema) {
        return;
      }

      this.schema.settings ??= {};
      this.schema.settings.progress = {
        mode,
        show_estimated_time: showEstimatedTime ?? this.schema.settings.progress?.show_estimated_time ?? true,
      };
      this.markDirty();
    },
    updateSurveySettings(patch: Partial<SurveySettings>) {
      if (!this.schema) return;
      this.schema.settings = { ...(this.schema.settings ?? {}), ...patch };
      this.markDirty();
    },
    updateAnomalySettings(patch: Partial<NonNullable<SurveySettings['anomaly']>>) {
      if (!this.schema) return;
      this.schema.settings ??= {};
      this.schema.settings.anomaly = { ...(this.schema.settings.anomaly ?? {}), ...patch };
      this.markDirty();
    },
    updateTheme(themeId: number | null) {
      if (!this.schema) {
        return;
      }

      this.schema.theme_id = themeId;
      this.markDirty();
    },
    updateThemeOverride(key: string, value: string) {
      if (!this.schema) {
        return;
      }

      this.schema.theme_overrides ??= {};
      this.schema.theme_overrides[key] = value;
      this.markDirty();
    },
    addCalculation() {
      if (!this.schema) {
        return;
      }

      this.schema.calculations ??= [];
      const suffix = Math.random().toString(36).slice(2, 7);
      const index = this.schema.calculations.length + 1;
      const isFirstCalculation = this.schema.calculations.length === 0;

      this.schema.calculations.push({
        id: `calc_${suffix}`,
        key: isFirstCalculation ? 'total_score' : `score_${suffix}`,
        label: isFirstCalculation ? '總分' : `計算 ${index}`,
        initial_value: 0,
        output_format: 'number',
        grade_map_json: [],
      });
      this.markDirty();
    },
    updateCalculation(id: string, updates: Record<string, unknown>) {
      const calculation = this.schema?.calculations?.find((item) => item.id === id);
      if (!calculation) {
        return;
      }

      const previousKey = calculation.key;
      Object.assign(calculation, updates);

      if (typeof updates.key === 'string' && updates.key !== '' && updates.key !== previousKey) {
        this.allElements.forEach((element) => {
          element.options.forEach((option) => {
            if (!option.score_delta_json || !Object.prototype.hasOwnProperty.call(option.score_delta_json, previousKey)) return;
            option.score_delta_json[updates.key as string] = option.score_delta_json[previousKey];
            delete option.score_delta_json[previousKey];
          });
        });
      }

      this.markDirty();
    },
    removeCalculation(id: string) {
      if (!this.schema?.calculations) {
        return;
      }

      const removed = this.schema.calculations.find((item) => item.id === id);
      this.schema.calculations = this.schema.calculations.filter((item) => item.id !== id);
      this.allElements.forEach((element) => {
        element.options.forEach((option) => {
          if (!option.score_delta_json) return;
          if (removed?.key) delete option.score_delta_json[removed.key];
        });
      });
      this.markDirty();
    },
    updateOptionScoreDelta(elementId: string, optionId: string, calculationKey: string, delta: number) {
      const element = this.allElements.find((el) => el.id === elementId);
      const option = element?.options.find((candidate) => candidate.id === optionId);
      if (!option) {
        return;
      }

      option.score_delta_json ??= {};
      option.score_delta_json[calculationKey] = Number.isFinite(delta) ? delta : 0;
      this.markDirty();
    },
    updateShowIf(elementId: string, patch: { logic?: 'and' | 'or'; conditions?: Condition[] } | null) {
      const element = this.allElements.find((el) => el.id === elementId);
      if (!element) {
        return;
      }

      element.show_if = patch === null ? null : {
        logic: patch.logic ?? element.show_if?.logic ?? 'and',
        conditions: patch.conditions ?? element.show_if?.conditions ?? [],
      };
      element.show_if_field_key = null;
      element.show_if_value = null;
      this.markDirty();
    },
    showIfEditorValue(elementId: string): EditableShowIf | null {
      const draft = this.showIfDrafts[elementId];

      if (draft) {
        return draft;
      }

      const element = this.allElements.find((candidate) => candidate.id === elementId);
      const showIf = element?.show_if;

      if (!showIf) {
        return null;
      }

      return {
        logic: showIf.logic,
        conditions: showIf.conditions.filter((condition): condition is Condition => 'field_key' in condition),
      };
    },
    stageShowIf(elementId: string, showIf: EditableShowIf | null) {
      if (showIf === null || showIf.conditions.length === 0) {
        const element = this.allElements.find((candidate) => candidate.id === elementId);
        const hasSavedCondition = element?.show_if !== null && element?.show_if !== undefined;

        delete this.showIfDrafts[elementId];

        if (hasSavedCondition || element?.show_if_field_key) {
          this.updateShowIf(elementId, null);
        }

        return;
      }

      const draft = structuredClone(showIf);

      if (isShowIfComplete(draft)) {
        delete this.showIfDrafts[elementId];
        this.updateShowIf(elementId, draft);

        return;
      }

      this.showIfDrafts[elementId] = draft;
      this.publishError = '';
    },
    updateElementSettings(elementId: string, settings: Record<string, unknown>) {
      const element = this.allElements.find((el) => el.id === elementId);
      if (!element) {
        return;
      }

      element.settings = { ...(element.settings ?? {}), ...settings };
      this.markDirty();
    },
    updateElementValidationRules(elementId: string, validationRules: Record<string, unknown>) {
      const element = this.allElements.find((el) => el.id === elementId);
      if (!element) {
        return;
      }

      element.validation_rules = { ...(element.validation_rules ?? {}), ...validationRules };
      this.markDirty();
    },
    addMatrixRow(elementId: string) {
      const element = this.allElements.find((el) => el.id === elementId);
      if (!element) return;
      element.matrix_rows ??= [];
      element.matrix_rows.push({ id: `row_${Math.random().toString(36).slice(2, 7)}`, label: '新列' });
      this.markDirty();
    },
    addMatrixCol(elementId: string) {
      const element = this.allElements.find((el) => el.id === elementId);
      if (!element) return;
      element.matrix_cols ??= [];
      element.matrix_cols.push({ id: `col_${Math.random().toString(36).slice(2, 7)}`, label: '新欄' });
      this.markDirty();
    },
    togglePreview() {
      this.isPreviewMode = !this.isPreviewMode;
      this.selectedElementId = null;
    },
  },
});
