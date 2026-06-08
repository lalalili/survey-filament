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
    'recipient_navigation_enabled' => true,

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
    | Widgets
    |--------------------------------------------------------------------------
    */
    'widgets_enabled' => true,

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

];
