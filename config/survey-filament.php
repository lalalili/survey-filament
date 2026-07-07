<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Panel Registration
    |--------------------------------------------------------------------------
    | IDs of the Filament panels where the survey plugin should be active.
    | An empty array means the plugin registers itself on all panels.
    */
    'panel_ids' => ['admin'],

    /*
    |--------------------------------------------------------------------------
    | Navigation
    |--------------------------------------------------------------------------
    */
    'navigation_group' => '問卷管理',

    'navigation_sort' => 50,

    // Keep the standalone survey package recipient list visible by default.
    'recipient_navigation_enabled'          => true,
    'recipient_navigation_super_admin_only' => false,

    'trigger_action_preset_navigation_enabled' => true,

    'trigger_rule_navigation_enabled' => true,

    'response_navigation_group' => '報表',

    'response_navigation_label' => '回覆紀錄',

    // How to handle marketing automation dispatch references when deleting a list.
    // Supported values: restrict, detach.
    'recipient_activity_dispatch_delete_strategy' => 'restrict',

    /*
    |--------------------------------------------------------------------------
    | Builder JSON Actions
    |--------------------------------------------------------------------------
    | Controls whether admins can import and export survey builder JSON from
    | the survey resource. Disabled by default because these actions expose the
    | full editable survey schema.
    */
    'builder_json_actions_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Builder Language Setting
    |--------------------------------------------------------------------------
    | Controls whether admins can choose the public survey language in the
    | builder display settings. Disabled until the public survey UI supports
    | localized labels, buttons, and validation messages.
    */
    'builder_language_setting_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Builder Thank-you Redirect Setting
    |--------------------------------------------------------------------------
    | Controls whether admins can configure a thank-you redirect URL in the
    | builder. Disabled until the public survey UI consistently supports the
    | redirect behavior across all frontend rendering modes.
    */
    'builder_thank_you_redirect_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Builder Accent Color Setting
    |--------------------------------------------------------------------------
    | Controls whether admins can configure the public survey accent color in
    | the builder display settings. Disabled until the public survey UI uses the
    | accent token consistently across rendering modes.
    */
    'builder_accent_color_setting_enabled' => false,

    /*
    |--------------------------------------------------------------------------
    | Survey Category Options
    |--------------------------------------------------------------------------
    | Optional category options for the builder settings UI. Keys are persisted
    | to surveys.category, values are displayed to admins. Leave empty to allow
    | free-form category input in the reusable package.
    */
    'survey_category_options' => [],

    // Survey table columns hidden by default. Supported values include:
    // category, fields_count, recipients_count.
    'survey_table_hidden_columns' => [],

    /*
    |--------------------------------------------------------------------------
    | Resource Discovery
    |--------------------------------------------------------------------------
    | Set to false to disable automatic resource registration and register
    | them manually inside your AdminPanelProvider instead.
    */
    'resource_discovery' => true,

    /*
    |--------------------------------------------------------------------------
    | Survey Resource Class Override
    |--------------------------------------------------------------------------
    | Replace the default SurveyResource with an application-specific subclass.
    | Set to null to use the package default.
    */
    'survey_resource_class' => null,

    'response_resource_class' => null,

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Survey Guide Page
    |--------------------------------------------------------------------------
    |
    | 後台內建「問卷使用說明」頁面，介紹拖拉式問卷編輯器與題型。
    | 設為 false 可隱藏此頁面與其導覽項目。
    |
    */
    'guide_enabled' => true,

    /*
    |--------------------------------------------------------------------------
    | Subscription Feature Gates
    |--------------------------------------------------------------------------
    */
    'subscription_owner_path' => 'merchant',

    'advanced_fields_feature_key' => 'survey.advanced_fields',

    /*
    |--------------------------------------------------------------------------
    | Query Scope (Multi-tenant isolation)
    |--------------------------------------------------------------------------
    | An optional closure that receives an Eloquent Builder and the current
    | authenticated user, and returns a scoped Builder.  Use this to restrict
    | which surveys (and related records) each user can see — for example, to
    | scope by merchant or company.
    |
    | Example (in AppServiceProvider::boot):
    |   config(['survey-filament.query_scope' => function ($query, $user) {
    |       return $user->is_super_admin
    |           ? $query
    |           : $query->where('merchant_id', $user->merchant_id);
    |   }]);
    |
    | Set to null to disable (show all records to every admin user).
    */
    'query_scope' => null,

    /*
    |--------------------------------------------------------------------------
    | Response Query Scope
    |--------------------------------------------------------------------------
    | An optional closure that receives the SurveyResponse Eloquent Builder and
    | the current authenticated user, returning a scoped Builder. Unlike
    | `query_scope` (which is applied through the `survey` relationship), this
    | runs directly on the response query, so it can filter by recipient
    | payload (e.g. dealer / location) as well as the related survey.
    |
    | Set to null to disable.
    */
    'response_query_scope' => null,

    /*
    |--------------------------------------------------------------------------
    | Response Export Action (Async override)
    |--------------------------------------------------------------------------
    | An optional callable(Survey $survey, Collection $records): void that
    | replaces the default synchronous XLSX download with an async flow
    | (e.g. dispatch a queued job and show a notification).
    |
    | Set to null to keep the default synchronous streaming download.
    */
    'response_export_action' => null,

];
