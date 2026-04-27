<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Log Types
    |--------------------------------------------------------------------------
    | Key   = value stored in the `type` column
    | Value = human-readable label shown in filter dropdowns
    |
    | Apps should publish this config and add their domain-specific types here.
    */
    'types' => [
        'exception' => 'Exception',
        'auth'      => 'Authentication',
        'system'    => 'System',
        'database'  => 'Database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Keyword → Type Auto-Detection Map
    |--------------------------------------------------------------------------
    | Used by DatabaseLogHandler when no explicit `type` is in the log context.
    | Key   = substring to look for (case-insensitive) in the log message
    | Value = type string to assign
    |
    | Evaluated top-to-bottom; first match wins.
    */
    'type_keywords' => [
        'auth'     => 'auth',
        'login'    => 'auth',
        'database' => 'database',
        'query'    => 'database',
    ],

    /*
    |--------------------------------------------------------------------------
    | Default / Fallback Type
    |--------------------------------------------------------------------------
    */
    'default_type' => 'system',

    /*
    |--------------------------------------------------------------------------
    | Tenant / Session Context Fields
    |--------------------------------------------------------------------------
    | Extra columns to populate on each log record (e.g. company_id, property_id).
    | These columns must exist in the application_logs table (add them in the
    | published migration before running php artisan migrate).
    |
    | 'column'   = DB column name
    | 'resolver' = Invokable class FQN (NOT a closure — closures break config:cache)
    |
    | Example:
    |   ['column' => 'company_id', 'resolver' => \App\Logging\Resolvers\CompanyIdResolver::class],
    */
    'context_fields' => [
        //
    ],

    /*
    |--------------------------------------------------------------------------
    | Dynamic Eloquent Relationships
    |--------------------------------------------------------------------------
    | Extra belongsTo relationships to register on the ApplicationLog model.
    |
    | Key         = relationship method name (e.g. 'company')
    | 'model'     = fully-qualified Eloquent model class
    | 'foreign'   = foreign key column on application_logs
    | 'display'   = attribute on the related model to show in the log detail view
    */
    'relationships' => [
        //
        // 'company' => [
        //     'model'   => \App\Models\Company::class,
        //     'foreign' => 'company_id',
        //     'display' => 'name',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Context Keys — Redacted Before Storage
    |--------------------------------------------------------------------------
    */
    'sensitive_keys' => [
        'password',
        'token',
        'secret',
        'api_key',
        'credit_card',
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Configuration
    |--------------------------------------------------------------------------
    | Set 'enabled' => false to suppress package route registration entirely
    | (useful when the host app defines its own route group with a parent prefix).
    */
    'routes' => [
        'enabled'    => true,
        'prefix'     => 'admin/system/logs',
        'name'       => 'applogger.',
        'middleware' => ['web', 'auth'],
    ],

    /*
    |--------------------------------------------------------------------------
    | View Layout
    |--------------------------------------------------------------------------
    | 'value' → Blade view path of your application's main layout.
    |           The layout must contain @yield('content') for the page body.
    |
    |   'layouts.app'    → resources/views/layouts/app.blade.php  (Laravel default)
    |   'layouts.admin'  → resources/views/layouts/admin.blade.php
    |
    | 'stack' → Name of the Blade @stack your layout exposes for inline scripts.
    |           Package views use @push(config('applogger.layout.stack')) to inject
    |           their DataTable / AJAX scripts into this stack.
    |
    |   Common values: 'scripts', 'script_page', 'js', 'page_scripts'
    |   Set this to whatever @stack your layout defines.
    */
    'layout' => [
        'value' => 'layouts.app',
        'stack' => 'script_page',
    ],

    /*
    |--------------------------------------------------------------------------
    | Table Name
    |--------------------------------------------------------------------------
    */
    'table' => 'application_logs',

    /*
    |--------------------------------------------------------------------------
    | Monolog Channel Name
    |--------------------------------------------------------------------------
    */
    'channel' => 'database',

    /*
    |--------------------------------------------------------------------------
    | Health Thresholds (errors per 24h)
    |--------------------------------------------------------------------------
    */
    'health' => [
        'critical_errors' => 100,
        'warning_errors'  => 10,
    ],

];
