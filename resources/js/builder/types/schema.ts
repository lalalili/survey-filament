export type SurveyElementType =
  | 'single_choice'
  | 'multiple_choice'
  | 'select'
  | 'cascade_select'
  | 'short_text'
  | 'long_text'
  | 'email'
  | 'phone'
  | 'date'
  | 'time'
  | 'linear_scale'
  | 'constant_sum'
  | 'rating'
  | 'number'
  | 'nps'
  | 'matrix_single'
  | 'matrix_multi'
  | 'ranking'
  | 'file_upload'
  | 'signature'
  | 'address'
  | 'section_title'
  | 'description_block'
  | 'divider'
  | 'quote_block';

export interface CascadeLevel {
  id: string;
  label: string;
}

export interface CascadeNode {
  id: string;
  label: string;
  children?: CascadeNode[];
}

export type JumpActionType = 'next_page' | 'go_to_page' | 'end_survey';

export interface SurveyOptionAction {
  type: JumpActionType;
  target_page_id?: string | null;
}

export interface SurveyOption {
  id: string;
  label: string;
  value: string;
  capacity?: number | null;
  is_hidden?: boolean;
  action?: SurveyOptionAction | null;
  score_delta_json?: Record<string, number>;
}

export interface SurveyElement {
  id: string;
  type: SurveyElementType;
  field_key?: string | null;
  label: string;
  description: string;
  required: boolean;
  placeholder?: string | null;
  options: SurveyOption[];
  settings: Record<string, unknown>;
  matrix_rows?: Array<{ id: string; label: string }>;
  matrix_cols?: Array<{ id: string; label: string }>;
  cascade_levels?: CascadeLevel[];
  cascade_data?: CascadeNode[];
  validation_rules?: Record<string, unknown>;
  show_if?: ConditionGroup | null;
  show_if_field_key?: string | null;
  show_if_value?: string | null;
  is_hidden?: boolean;
  personalized_key?: string | null;
}

export interface Condition {
  field_key: string;
  op: 'equals' | 'not_equals' | 'contains' | 'not_contains' | 'greater_than' | 'less_than' | 'between' | 'is_empty' | 'is_not_empty';
  value?: unknown;
}

export interface ConditionGroup {
  logic: 'and' | 'or';
  conditions: Condition[];
}

export interface PageJumpRule {
  condition: ConditionGroup;
  action: SurveyOptionAction;
}

export interface SurveyPage {
  id: string;
  kind?: 'welcome' | 'question' | 'thank_you';
  title: string;
  welcome_settings?: {
    enabled?: boolean;
    cta_label?: string;
    estimated_time_minutes?: number;
    subtitle?: string;
    content?: string | null;
  } | null;
  thank_you_settings?: {
    enabled?: boolean;
    message?: string;
    redirect_url?: string | null;
  } | null;
  jump_rules?: PageJumpRule[];
  elements: SurveyElement[];
}

export interface SurveyCalculation {
  id?: string;
  key: string;
  label: string;
  initial_value?: number;
  output_format?: 'number' | 'grade' | 'label';
  grade_map_json?: Array<Record<string, unknown>>;
}

export interface SurveyTheme {
  id: number;
  name: string;
  tokens: Record<string, string>;
}

export interface AudienceListColumn {
  key?: string | number | null;
  label?: string | number | null;
  value?: string | number | null;
  name?: string | number | null;
}

export interface AudienceListSummary {
  id: number;
  name: string;
  columns: Array<string | AudienceListColumn>;
}

export interface BuilderCapabilities {
  can_manage_advanced_fields: boolean;
  question_types: string[];
}

export interface SurveySettings {
  progress?: {
    mode?: 'none' | 'bar' | 'steps' | 'percent';
    show_estimated_time?: boolean;
  };
  description?: string | null;
  show_question_numbers?: boolean;
  allow_back?: boolean;
  language?: 'zh-TW' | 'zh-CN' | 'en';
  terms_text?: string | null;
  response_number?: boolean;
  notify_emails?: string | null;
  password?: string | null;
  starts_at?: string | null;
  ends_at?: string | null;
  max_responses?: number | null;
  quota_message?: string | null;
  uniqueness_mode?: 'none' | 'email' | 'token' | 'ip' | 'cookie';
  uniqueness_message?: string | null;
  personalization?: {
    audience_list_id?: number | string | null;
    required?: boolean;
    name_column?: string | null;
    email_column?: string | null;
    external_id_column?: string | null;
    field_mappings?: Record<string, string>;
  };
  anomaly?: {
    min_seconds?: number | null;
    detect_duplicate?: 'none' | 'cookie' | 'ip' | 'both';
    turnstile?: boolean;
  };
}

export interface SurveyBuilderSchema {
  id: number | string;
  title: string;
  status: string;
  version: number;
  settings?: SurveySettings;
  theme_id?: number | null;
  theme_overrides?: Record<string, string>;
  calculations?: SurveyCalculation[];
  thank_you_branches?: Array<{ condition: { calc_key?: string; op?: string; value?: unknown } | ConditionGroup; page_id?: string | null }>;
  pages: SurveyPage[];
}

export interface BuilderEndpoints {
  show: string;
  update: string;
  publish: string;
  uploadImage: string;
}

export interface BuilderPayload {
  survey: {
    id: number;
    title: string;
    status: string;
    version: number;
    published_at?: string | null;
  };
  schema: SurveyBuilderSchema;
  themes?: SurveyTheme[];
  audience_lists?: AudienceListSummary[];
  capabilities?: BuilderCapabilities;
}
