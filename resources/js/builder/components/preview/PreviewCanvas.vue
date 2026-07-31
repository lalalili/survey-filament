<script setup lang="ts">
import { computed } from 'vue';
import { injectPreviewRuntime } from '../../composables/usePreviewRuntime';
import { useSurveyBuilderStore } from '../../stores/useSurveyBuilderStore';
import { contentBlockText, formatSurveyNumber, ratingShapeIcon, textInputType } from '../../utils/builderHelpers';
import { buildQuestionNumberMap } from '../../utils/questionNumbering';

/**
 * 預覽面：把建立器目前的 schema 以受訪者看到的樣子渲染出來。
 *
 * 作答狀態與導覽邏輯不屬於這裡——它們住在 `usePreviewRuntime`，由 CanvasArea 建立
 * 後 provide，編輯面與預覽面共用同一份。這個元件只負責呈現。
 */

const store = useSurveyBuilderStore();

const {
  cascadePreviewLevelOptions,
  cascadePreviewSelect,
  constantSumCurrent,
  constantSumStatus,
  constantSumStatusText,
  constantSumTotal,
  constantSumValue,
  dismissPreviewSubmitNotice,
  linearScaleFillPercent,
  previewAddressValues,
  previewCascade,
  previewChooseFile,
  previewElementVisible,
  previewEnded,
  previewFileAccept,
  previewFileDragOver,
  previewFileDropped,
  previewFileFormatLabel,
  previewFileNames,
  previewFileSelected,
  previewFileSizeLabel,
  previewGoNext,
  previewGoPrev,
  previewHasTerms,
  previewIsLastPage,
  previewLinearScaleValue,
  previewMatrixMultiSelected,
  previewMatrixSingleSelected,
  previewMoveRanking,
  previewNps,
  previewOptions,
  previewPageElements,
  previewProgressWidth,
  previewRankingOrder,
  previewRatingDisplayValue,
  previewRatingHover,
  previewRatingIsHovered,
  previewRatingIsPopping,
  previewSelectMatrixSingle,
  previewSelectNps,
  previewSelectOption,
  previewSelectRating,
  previewSelectionBasedOptions,
  previewSelections,
  previewShowsProgress,
  previewSignatures,
  previewSubmitDisabled,
  previewSubmitNoticeVisible,
  previewTermsAccepted,
  previewTextValues,
  previewThemeVars,
  previewToggleCheckbox,
  previewToggleMatrixMulti,
  previewUpdateAddress,
  previewUpdateConstantSumValue,
  previewUpdateTextValue,
  renderCalculationTokens,
  resetPreview,
  selectionBasedSourceElement,
} = injectPreviewRuntime();

const questionNumberMap = computed(() => buildQuestionNumberMap(store.schema?.pages ?? []));
</script>

<template>
  <div class="sb-preview survey-preview-surface" :class="store.isMobilePreview ? 'mobile' : ''" :style="previewThemeVars">
    <Transition name="sb-preview-submit-notice">
      <div v-if="previewSubmitNoticeVisible" class="sb-preview-submit-notice" role="status" aria-live="polite">
        <span class="sb-preview-submit-notice-icon" aria-hidden="true">i</span>
        <span>預覽模式下不會真的送出填答，直接跳轉至感謝頁。</span>
        <button type="button" class="sb-preview-submit-notice-close" aria-label="關閉提示" @click="dismissPreviewSubmitNotice()">×</button>
      </div>
    </Transition>
    <div class="sb-preview-survey-header">
      <h1 class="sb-preview-survey-title">{{ store.surveyTitle }}</h1>
      <p v-if="store.schema?.settings?.description" class="sb-preview-survey-desc">{{ store.schema.settings.description }}</p>
    </div>
    <div v-if="previewEnded" class="sb-preview-end">
      <p class="sb-preview-end-title">問卷已結束</p>
      <p class="sb-preview-end-sub">感謝您的填答</p>
      <button type="button" class="sb-btn" @click="resetPreview()">重置預覽</button>
    </div>
    <template v-else>
      <div
        v-if="previewShowsProgress"
        class="sb-preview-progress"
      >
        <div :style="{ width: previewProgressWidth }" />
      </div>
      <div class="sb-preview-card">
        <!-- Welcome page rich content + CTA -->
        <template v-if="store.selectedPage?.kind === 'welcome'">
          <div
            v-if="store.selectedPage.welcome_settings?.content"
            class="sb-preview-rich survey-rich-content"
            v-html="store.selectedPage.welcome_settings.content"
          ></div>
          <p
            v-if="store.schema?.settings?.progress?.show_estimated_time !== false && Number(store.selectedPage.welcome_settings?.estimated_time_minutes ?? 0) > 0"
            class="sb-preview-estimated-time"
          >預估填寫時間：約 {{ store.selectedPage.welcome_settings?.estimated_time_minutes }} 分鐘</p>
          <div class="sb-preview-footer" style="margin-top:24px">
            <div style="flex:1" />
            <button type="button" class="sb-preview-nav-btn sb-preview-nav-btn--primary" @click="previewGoNext()">
              {{ store.selectedPage.welcome_settings?.cta_label || '開始填寫' }}
            </button>
          </div>
        </template>
  
        <!-- Thank you page rich content -->
        <template v-else-if="store.selectedPage?.kind === 'thank_you'">
          <div style="text-align:center;padding:24px 0">
            <div
              v-if="store.selectedPage.thank_you_settings?.message"
              class="sb-preview-rich survey-rich-content"
              v-html="renderCalculationTokens(store.selectedPage.thank_you_settings.message, true)"
            ></div>
            <p v-else class="sb-preview-q-desc">感謝您的填寫！</p>
          </div>
        </template>
  
        <template v-else v-for="element in previewPageElements" :key="element.id">
          <template v-if="previewElementVisible(element)">
            <div v-if="element.is_hidden" class="sb-preview-hidden">🔒 {{ element.label }}</div>
            <section v-else-if="element.type === 'section_title'" class="survey-field">
              <h3 class="survey-section-title">{{ contentBlockText(element) }}</h3>
            </section>
            <div v-else-if="element.type === 'description_block'" class="survey-field">
              <div class="survey-description-block survey-rich-content" v-html="contentBlockText(element)"></div>
            </div>
            <div v-else-if="element.type === 'quote_block'" class="survey-field survey-quote-block">
              <blockquote>{{ contentBlockText(element) }}</blockquote>
            </div>
            <div v-else-if="element.type === 'divider'" class="survey-field survey-divider"><hr></div>
            <div v-else class="survey-field survey-field-card">
              <p class="survey-field-label"><span v-if="store.schema?.settings?.show_question_numbers !== false && questionNumberMap[element.id] !== undefined" class="sb-preview-q-num">{{ questionNumberMap[element.id] }}. </span>{{ element.label }}<span v-if="element.required" class="survey-field-required">*</span></p>
              <p v-if="element.description" class="survey-field-description">{{ element.description }}</p>
              <div v-if="element.type === 'single_choice'" class="survey-choices">
                <label v-for="opt in previewOptions(element)" :key="opt.id"
                  class="survey-choice-label"
                  :class="{ selected: previewSelections[element.id] === opt.value }"
                >
                  <input class="survey-choice-input" type="radio" :name="element.id" :value="opt.value" :checked="previewSelections[element.id] === opt.value" @change="previewSelectOption(element, opt.value)" />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
              <div v-else-if="element.type === 'multiple_choice'" class="survey-choices">
                <label v-for="opt in previewOptions(element)" :key="opt.id"
                  class="survey-choice-label"
                  :class="{ selected: previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value) }"
                  @click.prevent="previewToggleCheckbox(element.id, opt.value)"
                >
                  <input class="survey-choice-input" type="checkbox" :name="element.id" :checked="previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value)" @change.prevent />
                  <span>{{ opt.label }}</span>
                </label>
              </div>
              <select
                v-else-if="element.type === 'select'"
                :value="previewSelections[element.id] ?? ''"
                class="survey-select"
                @change="previewSelectOption(element, ($event.target as HTMLSelectElement).value)"
              >
                <option value="">請選擇</option>
                <option v-for="opt in previewOptions(element)" :key="opt.id" :value="opt.value">{{ opt.label }}</option>
              </select>
              <input
                v-else-if="element.type === 'short_text' || element.type === 'email' || element.type === 'phone' || element.type === 'date' || element.type === 'time'"
                :type="textInputType(element)"
                :inputmode="(element.settings as any)?.input_mode ?? (((element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone') ? 'numeric' : undefined)"
                :minlength="(element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone' ? 10 : undefined"
                :maxlength="(element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone' ? 10 : undefined"
                :pattern="(element.settings as any)?.input_format === 'mobile_tw' || element.type === 'phone' ? '09[0-9]{8}' : undefined"
                :placeholder="element.placeholder ?? ''"
                :value="previewTextValues[element.id] ?? ''"
                class="survey-input"
                @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
              />
              <textarea
                v-else-if="element.type === 'long_text'"
                :placeholder="element.placeholder ?? ''"
                :value="previewTextValues[element.id] ?? ''"
                rows="4"
                class="survey-textarea"
                @input="previewUpdateTextValue(element.id, ($event.target as HTMLTextAreaElement).value)"
              />
              <div v-else-if="element.type === 'rating'" class="survey-rating-stars" :style="{ '--rating-count': Number((element.settings as any)?.count ?? 5) }">
                <button
                  v-for="n in Number((element.settings as any)?.count ?? 5)"
                  :key="n"
                  type="button"
                  class="survey-rating-star-label"
                  :class="[
                    `shape-${(element.settings as any)?.shape ?? 'star'}`,
                    {
                      filled: n <= previewRatingDisplayValue(element.id),
                      hovered: previewRatingIsHovered(element.id, n),
                      popping: previewRatingIsPopping(element.id, n),
                    },
                  ]"
                  @mouseenter="previewRatingHover = { ...previewRatingHover, [element.id]: n }"
                  @mouseleave="previewRatingHover = { ...previewRatingHover, [element.id]: 0 }"
                  @click="previewSelectRating(element.id, n)"
                >
                  <span v-if="(element.settings as any)?.show_numbers" class="survey-rating-star-number">{{ n }}</span>
                  <span class="survey-rating-star-icon">{{ ratingShapeIcon((element.settings as any)?.shape ?? 'star') }}</span>
                </button>
              </div>
              <div v-else-if="element.type === 'number'" class="sb-preview-number-row">
                <input
                  type="number"
                  class="survey-input sb-preview-number-input"
                  :min="(element.settings as any)?.min ?? undefined"
                  :max="(element.settings as any)?.max ?? undefined"
                  :step="(element.settings as any)?.decimal_places ? Math.pow(10, -Number((element.settings as any).decimal_places)) : 1"
                  :value="previewTextValues[element.id] ?? ''"
                  placeholder="請輸入數字"
                  @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                />
                <span v-if="(element.settings as any)?.unit" class="sb-preview-number-unit">{{ (element.settings as any).unit }}</span>
              </div>
              <div v-else-if="element.type === 'linear_scale'" class="survey-linear-scale">
                <span class="survey-linear-scale-value">{{ previewLinearScaleValue(element) }}</span>
                <input
                  type="range"
                  class="survey-linear-scale-input"
                  :min="(element.settings as any)?.min ?? 1"
                  :max="(element.settings as any)?.max ?? 5"
                  :step="(element.settings as any)?.step ?? 1"
                  :value="previewLinearScaleValue(element)"
                  :style="{ '--survey-range-fill': linearScaleFillPercent(element) }"
                  @input="previewUpdateTextValue(element.id, ($event.target as HTMLInputElement).value)"
                />
                <span v-if="(element.settings as any)?.unit" class="sb-preview-number-unit">{{ (element.settings as any).unit }}</span>
              </div>
              <div v-else-if="element.type === 'constant_sum'" class="survey-choices survey-constant-sum">
                <label v-for="opt in previewOptions(element)" :key="opt.id" class="survey-choice-label survey-preview-inline-input survey-constant-sum-row">
                  <span>{{ opt.label }}</span>
                  <span class="survey-constant-sum-input-wrap">
                    <input
                      type="number"
                      class="survey-input"
                      :placeholder="String((element.settings as any)?.unit || '0')"
                      :value="constantSumValue(element.id, opt.id)"
                      @input="previewUpdateConstantSumValue(element.id, opt.id, ($event.target as HTMLInputElement).value)"
                    />
                    <span v-if="(element.settings as any)?.unit" class="survey-constant-sum-unit">{{ (element.settings as any).unit }}</span>
                  </span>
                </label>
                <div class="survey-constant-sum-summary" :data-status="constantSumStatus(element)">
                  <span>目前合計 {{ formatSurveyNumber(constantSumCurrent(element)) }}</span>
                  <span v-if="constantSumTotal(element) !== null">目標 {{ formatSurveyNumber(constantSumTotal(element)!) }}</span>
                  <strong>{{ constantSumStatusText(element) }}</strong>
                </div>
              </div>
              <div v-else-if="element.type === 'selection_based'" class="survey-choices">
                <p v-if="!selectionBasedSourceElement(element)" class="survey-help">請先在右側選擇來源題目。</p>
                <p v-else-if="previewSelectionBasedOptions(element).length === 0" class="survey-help">請先回答來源題目，這裡會顯示可複選的選項。</p>
                <template v-else>
                  <label
                    v-for="opt in previewSelectionBasedOptions(element)"
                    :key="opt.id"
                    class="survey-choice-label"
                    :class="{ selected: previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value) }"
                    @click.prevent="previewToggleCheckbox(element.id, opt.value)"
                  >
                    <input class="survey-choice-input" type="checkbox" :name="element.id" :checked="previewSelections[element.id] instanceof Set && (previewSelections[element.id] as Set<string>).has(opt.value)" @change.prevent />
                    <span>{{ opt.label }}</span>
                  </label>
                </template>
              </div>
              <div v-else-if="element.type === 'ranking'" class="sb-preview-ranking">
                <div
                  v-for="(opt, index) in previewRankingOrder(element)"
                  :key="opt.id"
                  class="sb-preview-ranking-item"
                >
                  <span class="sb-preview-ranking-position">{{ index + 1 }}</span>
                  <span class="sb-preview-ranking-label">{{ opt.label }}</span>
                  <button type="button" class="sb-preview-ranking-move" :disabled="index === 0" @click="previewMoveRanking(element, opt.value, -1)">↑</button>
                  <button type="button" class="sb-preview-ranking-move" :disabled="index === element.options.length - 1" @click="previewMoveRanking(element, opt.value, 1)">↓</button>
                </div>
              </div>
              <div v-else-if="element.type === 'file_upload'" class="survey-choices">
                <input
                  type="file"
                  class="survey-file-input"
                  :data-preview-file-input="element.id"
                  :accept="previewFileAccept(element)"
                  @change="previewFileSelected(element.id, $event)"
                />
                <button
                  type="button"
                  class="survey-file-dropzone"
                  :class="{ 'is-dragging': previewFileDragOver[element.id], 'is-uploaded': previewFileNames[element.id] }"
                  @click="previewChooseFile(element.id)"
                  @dragenter.prevent="previewFileDragOver = { ...previewFileDragOver, [element.id]: true }"
                  @dragover.prevent="previewFileDragOver = { ...previewFileDragOver, [element.id]: true }"
                  @dragleave.prevent="previewFileDragOver = { ...previewFileDragOver, [element.id]: false }"
                  @drop.prevent="previewFileDropped(element.id, $event)"
                >
                  <span class="survey-file-icon" aria-hidden="true">☁</span>
                  <span class="survey-file-title">選擇檔案或將檔案拖曳至此</span>
                  <span class="survey-file-limit">{{ previewFileSizeLabel(element) }}</span>
                  <span class="survey-file-format">檔案格式：{{ previewFileFormatLabel(element) }}</span>
                </button>
                <p v-if="previewFileNames[element.id]" class="sb-preview-help">已選擇：{{ previewFileNames[element.id] }}</p>
              </div>
              <div v-else-if="element.type === 'signature'" class="survey-choices">
                <button
                  type="button"
                  class="survey-input sb-preview-signature-pad"
                  :class="{ signed: previewSignatures[element.id] }"
                  @click="previewSignatures = { ...previewSignatures, [element.id]: true }"
                >
                  {{ previewSignatures[element.id] ? '已簽名' : '點擊模擬簽名' }}
                </button>
                <button
                  v-if="previewSignatures[element.id]"
                  type="button"
                  class="sb-preview-signature-clear"
                  @click="previewSignatures = { ...previewSignatures, [element.id]: false }"
                >清除簽名</button>
              </div>
              <div v-else-if="element.type === 'address'" class="sb-preview-address">
                <input
                  v-for="addressKey in ((element.settings as any)?.fields_enabled ?? ['country', 'city', 'district', 'address', 'postal_code'])"
                  :key="addressKey"
                  type="text"
                  class="survey-input"
                  :placeholder="String(addressKey)"
                  :value="previewAddressValues[element.id]?.[String(addressKey)] ?? ((String(addressKey) === 'country' && (element.settings as any)?.country_locked) ? String((element.settings as any).country_locked) : '')"
                  :disabled="String(addressKey) === 'country' && !!(element.settings as any)?.country_locked"
                  @input="previewUpdateAddress(element.id, String(addressKey), ($event.target as HTMLInputElement).value)"
                />
              </div>
              <div v-else-if="element.type === 'matrix_single' || element.type === 'matrix_multi'" class="survey-preview-matrix-wrap">
                <div class="survey-preview-matrix-scroll">
                  <table class="survey-matrix">
                    <thead>
                      <tr>
                        <th></th>
                        <th v-for="col in element.matrix_cols" :key="col.id">{{ col.label }}</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="row in element.matrix_rows" :key="row.id">
                        <td>{{ row.label }}</td>
                        <td
                          v-for="col in element.matrix_cols"
                          :key="col.id"
                          class="survey-preview-matrix-cell"
                          :class="{
                            selected: element.type === 'matrix_single'
                              ? previewMatrixSingleSelected(element.id, row.id, col.id)
                              : previewMatrixMultiSelected(element.id, row.id, col.id)
                          }"
                          @click="element.type === 'matrix_single'
                            ? previewSelectMatrixSingle(element.id, row.id, col.id)
                            : previewToggleMatrixMulti(element.id, row.id, col.id)"
                        >
                          <span class="survey-preview-matrix-pip" :class="element.type === 'matrix_multi' ? 'square' : ''"></span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
              <div v-else-if="element.type === 'nps'" class="survey-nps-wrap">
                <div class="survey-nps-row">
                  <button
                    v-for="n in 11" :key="n"
                    type="button"
                    class="survey-nps-pip"
                    :aria-pressed="previewNps[element.id] === n - 1"
                    :class="{
                      selected: previewNps[element.id] === n - 1,
                      red:    (element.settings as any)?.color_bands && n - 1 <= 6,
                      yellow: (element.settings as any)?.color_bands && n - 1 >= 7 && n - 1 <= 8,
                      green:  (element.settings as any)?.color_bands && n - 1 >= 9,
                    }"
                    @click="previewSelectNps(element.id, n - 1)"
                  >{{ n - 1 }}</button>
                </div>
                <div class="survey-nps-labels">
                  <span>{{ (element.settings as any)?.low_label || '非常不推薦' }}</span>
                  <span>{{ (element.settings as any)?.high_label || '非常推薦' }}</span>
                </div>
              </div>
              <div v-else-if="element.type === 'cascade_select'" class="survey-cascade-grid">
                <p v-if="(element.cascade_levels ?? []).length === 0" class="survey-help">請先設定層級與選項資料。</p>
                <div
                  v-for="(lvl, li) in (element.cascade_levels ?? [])"
                  :key="lvl.id"
                  class="survey-preview-cascade-row"
                >
                  <select
                    class="survey-select"
                    :disabled="li > 0 && !(previewCascade[element.id]?.[li - 1])"
                    :value="previewCascade[element.id]?.[li] ?? ''"
                    @change="cascadePreviewSelect(element.id, li, ($event.target as HTMLSelectElement).value)"
                  >
                    <option value="">{{ lvl.label || '請選擇' }}</option>
                    <option
                      v-for="opt in cascadePreviewLevelOptions(element, li)"
                      :key="opt.id"
                      :value="opt.id"
                    >{{ opt.label }}</option>
                  </select>
                </div>
              </div>
            </div>
          </template>
        </template>
        <label v-if="previewIsLastPage && previewHasTerms" class="sb-preview-terms">
          <input type="checkbox" v-model="previewTermsAccepted" />
          <span>{{ store.schema.settings?.terms_text }}</span>
        </label>
        <div v-if="store.selectedPage?.kind !== 'welcome' && store.selectedPage?.kind !== 'thank_you'" class="sb-preview-footer">
          <button
            v-if="store.schema.pages.findIndex(p => p.id === store.selectedPageId) > 0"
            type="button"
            class="sb-preview-nav-btn sb-preview-nav-btn--secondary"
            @click="previewGoPrev()"
          >← 上一頁</button>
          <div style="flex:1" />
          <button type="button" class="sb-preview-nav-btn sb-preview-nav-btn--primary" :disabled="previewSubmitDisabled" @click="previewGoNext()">
            {{ previewIsLastPage ? '提交' : '下一頁 →' }}
          </button>
        </div>
      </div>
    </template>
  </div>
</template>
