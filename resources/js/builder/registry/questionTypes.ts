import type { SurveyElement, SurveyElementType } from '../types/schema';

export interface QuestionTypeDefinition {
  id: string;
  type: SurveyElementType;
  label: string;
  icon: string;
  supportsOptions: boolean;
  supportsPlaceholder: boolean;
  supportsRequired: boolean;
  createDefault: () => SurveyElement;
}

function randomId(prefix: string): string {
  return `${prefix}_${Math.random().toString(36).slice(2, 9)}`;
}

function createBase(type: SurveyElementType, label: string): SurveyElement {
  const isContentBlock = type === 'section_title' || type === 'description_block' || type === 'divider' || type === 'quote_block';

  return {
    id: randomId('q'),
    type,
    field_key: isContentBlock ? null : randomId('question'),
    label,
    description: '',
    required: false,
    placeholder: null,
    options: [],
    settings: {},
    show_if_field_key: null,
    show_if_value: null,
    is_hidden: false,
    personalized_key: null,
  };
}

function choiceOptions() {
  return [
    { id: randomId('opt'), label: '選項 1', value: randomId('option') },
    { id: randomId('opt'), label: '選項 2', value: randomId('option') },
  ];
}

function matrixRows() {
  return [
    { id: randomId('row'), label: '品質' },
    { id: randomId('row'), label: '服務' },
  ];
}

function matrixCols() {
  return [
    { id: randomId('col'), label: '差' },
    { id: randomId('col'), label: '普通' },
    { id: randomId('col'), label: '好' },
  ];
}

export const questionTypes: QuestionTypeDefinition[] = [
  {
    id: 'single_choice',
    type: 'single_choice',
    label: '單選題',
    icon: '◉',
    supportsOptions: true,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('single_choice', '未命名單選題'), options: choiceOptions() }),
  },
  {
    id: 'multiple_choice',
    type: 'multiple_choice',
    label: '複選題',
    icon: '☑',
    supportsOptions: true,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('multiple_choice', '未命名複選題'), options: choiceOptions() }),
  },
  {
    id: 'select',
    type: 'select',
    label: '下拉選單',
    icon: '▾',
    supportsOptions: true,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('select', '未命名下拉選單'), options: choiceOptions() }),
  },
  {
    id: 'cascade_select',
    type: 'cascade_select',
    label: '巢狀選擇題',
    icon: '⊕',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('cascade_select', '未命名巢狀選擇題'),
      cascade_levels: [],
      cascade_data: [],
    }),
  },
  {
    id: 'short_text',
    type: 'short_text',
    label: '單行文字',
    icon: 'T',
    supportsOptions: false,
    supportsPlaceholder: true,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('short_text', '未命名單行文字題'), placeholder: '請輸入回答' }),
  },
  {
    id: 'long_text',
    type: 'long_text',
    label: '多行文字',
    icon: '¶',
    supportsOptions: false,
    supportsPlaceholder: true,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('long_text', '未命名多行文字題'), placeholder: '請輸入詳細回答' }),
  },
  {
    id: 'preset_email',
    type: 'short_text',
    label: 'Email',
    icon: '@',
    supportsOptions: false,
    supportsPlaceholder: true,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('short_text', 'Email'),
      placeholder: 'name@example.com',
      settings: { input_format: 'email', input_mode: 'email' },
      validation_rules: {},
    }),
  },
  {
    id: 'preset_mobile_tw',
    type: 'short_text',
    label: '手機',
    icon: '☎',
    supportsOptions: false,
    supportsPlaceholder: true,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('short_text', '手機'),
      placeholder: '0912345678',
      settings: {
        input_format: 'mobile_tw',
        input_mode: 'numeric',
        minlength: 10,
        maxlength: 10,
        pattern: '09[0-9]{8}',
      },
      validation_rules: {},
    }),
  },
  {
    id: 'preset_address_line',
    type: 'short_text',
    label: '地址',
    icon: '⌂',
    supportsOptions: false,
    supportsPlaceholder: true,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('short_text', '地址'), placeholder: '請輸入完整地址' }),
  },
  {
    id: 'date',
    type: 'date',
    label: '日期',
    icon: '📅',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => createBase('date', '未命名日期題'),
  },
  {
    id: 'time',
    type: 'time',
    label: '時間',
    icon: '◷',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => createBase('time', '未命名時間題'),
  },
  {
    id: 'rating',
    type: 'rating',
    label: '星級評分',
    icon: '★',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('rating', '未命名評分題'), settings: { count: 5, shape: 'star', show_numbers: false } }),
  },
  {
    id: 'number',
    type: 'number',
    label: '數字',
    icon: '#',
    supportsOptions: false,
    supportsPlaceholder: true,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('number', '未命名數字題'), settings: { min: 0, max: 100, step: 1, decimal_places: 0, unit: '' } }),
  },
  {
    id: 'constant_sum',
    type: 'constant_sum',
    label: '總計題',
    icon: 'Σ',
    supportsOptions: true,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('constant_sum', '未命名總計題'),
      options: choiceOptions(),
      settings: { total: 100, unit: '' },
    }),
  },
  {
    id: 'nps',
    type: 'nps',
    label: 'NPS 淨推薦值',
    icon: '10',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('nps', '未命名 NPS 題'), settings: { low_label: '非常不推薦', high_label: '非常推薦', color_bands: false } }),
  },
  {
    id: 'linear_scale',
    type: 'linear_scale',
    label: '數字滑桿',
    icon: '─●',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('linear_scale', '未命名數字滑桿題'),
      settings: { min: 1, max: 5, step: 1, low_label: '低', high_label: '高', unit: '' },
    }),
  },
  {
    id: 'matrix_single',
    type: 'matrix_single',
    label: '矩陣單選',
    icon: '▦',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('matrix_single', '未命名矩陣單選題'), matrix_rows: matrixRows(), matrix_cols: matrixCols() }),
  },
  {
    id: 'matrix_multi',
    type: 'matrix_multi',
    label: '矩陣複選',
    icon: '▦',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('matrix_multi', '未命名矩陣複選題'), matrix_rows: matrixRows(), matrix_cols: matrixCols() }),
  },
  {
    id: 'selection_based',
    type: 'selection_based',
    label: '重複核選題',
    icon: '⮌',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('selection_based', '未命名重複核選題'),
      settings: { source_field_key: null },
    }),
  },
  {
    id: 'ranking',
    type: 'ranking',
    label: '排序題',
    icon: '↕',
    supportsOptions: true,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({ ...createBase('ranking', '未命名排序題'), options: choiceOptions() }),
  },
  // 暫不開發：file_upload（檔案上傳）、signature（簽名）
  // 後端 action / 公開端 blade 已有基礎實作，但 Builder UI 與填寫端體驗尚未完整，暫時隱藏。
  {
    id: 'legacy_address',
    type: 'address',
    label: '地址',
    icon: '⌂',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('address', '未命名地址題'),
      settings: {
        country_label: '國家',
        city_label: '城市',
        postal_code_label: '郵遞區號',
        address_line_label: '地址',
      },
    }),
  },
  {
    id: 'section_title',
    type: 'section_title',
    label: '標題',
    icon: 'H',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: false,
    createDefault: () => ({
      ...createBase('section_title', '標題'),
      description: '新的標題',
    }),
  },
  {
    id: 'description_block',
    type: 'description_block',
    label: '說明文字',
    icon: 'i',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: false,
    createDefault: () => ({
      ...createBase('description_block', '說明文字'),
      description: '<p>補充這個區段需要讓填寫者知道的資訊。</p>',
    }),
  },
  {
    id: 'divider',
    type: 'divider',
    label: '分隔線',
    icon: '─',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: false,
    createDefault: () => ({
      ...createBase('divider', '分隔線'),
      description: '',
    }),
  },
  {
    id: 'quote_block',
    type: 'quote_block',
    label: '引言',
    icon: '❝',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: false,
    createDefault: () => ({
      ...createBase('quote_block', '引言'),
      description: '請輸入引言內容',
    }),
  },
  {
    id: 'file_upload',
    type: 'file_upload',
    label: '檔案上傳',
    icon: '⬆',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => ({
      ...createBase('file_upload', '未命名檔案上傳題'),
      settings: { max_size_mb: 10, allowed_mimes: [] },
    }),
  },
  {
    id: 'signature',
    type: 'signature',
    label: '簽名',
    icon: '✎',
    supportsOptions: false,
    supportsPlaceholder: false,
    supportsRequired: true,
    createDefault: () => createBase('signature', '未命名簽名題'),
  },
];

export function getQuestionType(idOrType: string): QuestionTypeDefinition {
  return questionTypes.find((definition) => definition.id === idOrType)
    ?? questionTypes.find((definition) => definition.type === idOrType)
    ?? questionTypes[0];
}
