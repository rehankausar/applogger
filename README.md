# laravel-applogger

A configurable database and file log management package for Laravel 11/12 applications.

Captures application logs to a database table via a custom Monolog channel, and provides a full-featured admin UI to browse, filter, inspect, and clean up both database logs and Laravel log files.

---

## Features

- Custom Monolog `database` channel — logs flow automatically to `application_logs` table
- Config-driven log **types** (`exception`, `payment`, `auth`, etc.)
- Keyword-based **auto-detection** of log type from message content
- **Tenant context fields** — attach arbitrary columns (e.g. `company_id`, `business_id`) via invokable resolver classes
- **Dynamic Eloquent relationships** — show related model data in log detail view without touching package code
- **Sensitive key redaction** — passwords, tokens, API keys never stored in plain text
- Admin UI with DataTables, filtering, log detail modal
- **File log management** — list, view, download, delete Laravel log files
- **Health status API** — JSON endpoint with 24-hour error/warning counts
- Fully publishable: config, migration, views

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | ^11.0 \| ^12.0 |
| yajra/laravel-datatables-oracle | ^11.0 \| ^12.0 |
| monolog/monolog | ^3.0 |

The admin UI also requires **Bootstrap 5**, **Font Awesome**, **DataTables**, and **SweetAlert2** to be available in the host application's layout.

---

## Installation

### Option A — Local path (monorepo / development)

1. Place the package in your project:
   ```
   packages/fastnetksa/laravel-applogger/
   ```

2. Register it in your root `composer.json`:
   ```json
   {
       "repositories": [
           {
               "type": "path",
               "url": "packages/fastnetksa/laravel-applogger"
           }
       ],
       "require": {
           "fastnetksa/laravel-applogger": "@dev"
       }
   }
   ```

3. Install:
   ```bash
   composer require fastnetksa/laravel-applogger:@dev
   ```

### Option B — Packagist (when published)

```bash
composer require fastnetksa/laravel-applogger
```

The ServiceProvider is auto-discovered via Laravel's package discovery — no manual registration needed.

---

## Setup

### 1. Publish config

```bash
php artisan vendor:publish --tag=applogger-config
```

This creates `config/applogger.php`. Edit it to match your application.

### 2. Publish & customise migration

```bash
php artisan vendor:publish --tag=applogger-migrations
```

Open the published migration and **add your tenant columns** before running migrate:

```php
// Inside Schema::create('application_logs', ...) — add after user_id:
$table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
$table->foreignId('business_id')->nullable()->constrained()->nullOnDelete();
```

### 3. Run migration

```bash
php artisan migrate
```

---

## Monolog Channel Setup

Add a `database` channel to `config/logging.php`:

```php
'channels' => [
    // ... existing channels ...

    'database' => [
        'driver' => 'custom',
        'via'    => \FastnetKSA\AppLogger\Logging\CreateDatabaseLogger::class,
        'level'  => 'debug',
    ],
],
```

Then set your default or stack channel to include `database`:

```php
// Option 1 — add to your stack
'stack' => [
    'driver'   => 'stack',
    'channels' => ['single', 'database'],
],

// Option 2 — set as default
'default' => env('LOG_CHANNEL', 'database'),
```

All log calls (`Log::error(...)`, `Log::info(...)`, etc.) will now be persisted to the database automatically.

---

## Layout Configuration

Set `layout.value` in `config/applogger.php` to the Blade view path of your application's main layout:

```php
'layout' => [
    'value' => 'layouts.admin',   // → resources/views/layouts/admin.blade.php
],
```

The layout **must** contain `@yield('content')` where page content should appear.

### If your layout also uses `{{ $slot }}`

When a layout dual-supports both component usage (`<x-your-layout>`) and extends usage (`@extends`), the `$slot` variable is only defined in the component context. To prevent an "Undefined variable $slot" error when the package uses `@extends`, change:

```blade
{{-- Before --}}
{{ $slot }}

{{-- After --}}
{{ $slot ?? '' }}
```

---

## Route Configuration

### Option A — Package-managed routes (default)

The package registers its own routes when `routes.enabled = true`:

```php
'routes' => [
    'enabled'    => true,
    'prefix'     => 'admin/system/logs',
    'name'       => 'applogger.',
    'middleware' => ['web', 'auth'],
],
```

Routes available:

| Method | URI | Name | Action |
|--------|-----|------|--------|
| GET | `{prefix}/` | `{name}index` | Log list & dashboard |
| GET | `{prefix}/{id}` | `{name}show` | Log detail (JSON) |
| POST | `{prefix}/cleanup` | `{name}cleanup` | Delete old logs |
| GET | `{prefix}/health/status` | `{name}health` | Health check (JSON) |
| GET | `{prefix}/files/list` | `{name}files` | File log list |
| GET | `{prefix}/files/view` | `{name}files.view` | View file content (JSON) |
| GET | `{prefix}/files/download` | `{name}files.download` | Download log file |
| POST | `{prefix}/files/delete` | `{name}files.delete` | Delete a log file |
| POST | `{prefix}/files/clear-all` | `{name}files.clear-all` | Clear all log files |

### Option B — Host-app-managed routes

If the host app has its own route group (e.g. with auth guards, permission middleware, locale prefix), disable the package routes and define them manually:

```php
// config/applogger.php
'routes' => [
    'enabled' => false,
],
```

```php
// routes/admin.php
use FastnetKSA\AppLogger\Http\Controllers\ApplicationLogsController;

Route::prefix('system/logs')->name('admin.system.logs.')->group(function () {
    Route::get('/',                 [ApplicationLogsController::class, 'index'])->name('index');
    Route::get('/{log}',            [ApplicationLogsController::class, 'show'])->name('show');
    Route::post('/cleanup',         [ApplicationLogsController::class, 'cleanup'])->name('cleanup');
    Route::get('/health/status',    [ApplicationLogsController::class, 'health'])->name('health');
    Route::get('/files/list',       [ApplicationLogsController::class, 'fileLogs'])->name('files');
    Route::get('/files/view',       [ApplicationLogsController::class, 'viewFile'])->name('files.view');
    Route::get('/files/download',   [ApplicationLogsController::class, 'downloadFile'])->name('files.download');
    Route::post('/files/delete',    [ApplicationLogsController::class, 'deleteFile'])->name('files.delete');
    Route::post('/files/clear-all', [ApplicationLogsController::class, 'clearAllFiles'])->name('files.clear-all');
});
```

---

## Tenant Context Fields

Attach extra columns (e.g. `company_id`) to every log record automatically.

### 1. Ensure the column exists in your migration

```php
$table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
```

### 2. Create an invokable resolver class

```php
// app/Logging/Resolvers/CompanyIdResolver.php
namespace App\Logging\Resolvers;

class CompanyIdResolver
{
    public function __invoke(): ?int
    {
        return auth()->user()?->company_id;
    }
}
```

> **Important:** Use an invokable class (not a closure). Closures cannot be serialised by `php artisan config:cache`.

### 3. Register in config

```php
// config/applogger.php
'context_fields' => [
    [
        'column'   => 'company_id',
        'resolver' => \App\Logging\Resolvers\CompanyIdResolver::class,
    ],
    [
        'column'   => 'business_id',
        'resolver' => \App\Logging\Resolvers\BusinessIdResolver::class,
    ],
],
```

---

## Dynamic Relationships

Display related model data in the log detail modal without modifying package code.

```php
// config/applogger.php
'relationships' => [
    'company' => [
        'model'   => \App\Models\Company::class,
        'foreign' => 'company_id',   // FK column on application_logs
        'display' => 'name',         // attribute to show in the detail view
    ],
    'business' => [
        'model'   => \App\Models\Business::class,
        'foreign' => 'business_id',
        'display' => 'name',
    ],
],
```

The ServiceProvider registers these as runtime `belongsTo` relationships on `ApplicationLog` using `resolveRelationUsing()`. The controller loads them with `with([...])` and renders the `display` attribute in the detail modal.

---

## Log Types

Define the type vocabulary for your application:

```php
// config/applogger.php
'types' => [
    'exception' => 'Exception',
    'auth'      => 'Authentication',
    'system'    => 'System',
    'database'  => 'Database',
    'invoice'   => 'Invoice',
    'payment'   => 'Payment',
],
```

### Keyword auto-detection

When a log entry has no explicit `type` in its context, the handler scans the message for keywords:

```php
'type_keywords' => [
    'payment'  => 'payment',
    'invoice'  => 'invoice',
    'auth'     => 'auth',
    'login'    => 'auth',
    'database' => 'database',
    'query'    => 'database',
],

'default_type' => 'system',  // fallback when no keyword matches
```

First match wins (top-to-bottom). To set an explicit type, pass it in log context:

```php
Log::error('Payment gateway timeout', ['type' => 'payment']);
```

---

## Static Logging Helpers

`ApplicationLog` provides static helpers for direct programmatic logging (bypassing Monolog):

```php
use FastnetKSA\AppLogger\Models\ApplicationLog;

ApplicationLog::logError('payment', 'Stripe charge failed', ['order_id' => 42], $exception);
ApplicationLog::logWarning('invoice', 'PDF generation slow', ['duration_ms' => 3200]);
ApplicationLog::logInfo('auth', 'User logged in', ['user_id' => 1]);
ApplicationLog::logDebug('system', 'Cache miss for key settings.theme');
```

All helpers call `logEntry()` internally, which resolves tenant context fields automatically.

---

## Sensitive Key Redaction

Context keys listed in `sensitive_keys` are replaced with `[REDACTED]` before being stored:

```php
'sensitive_keys' => [
    'password',
    'token',
    'secret',
    'api_key',
    'credit_card',
],
```

---

## View Customisation

Publish views to override them individually:

```bash
php artisan vendor:publish --tag=applogger-views
```

Files are copied to `resources/views/vendor/applogger/logs/`. Laravel automatically uses these instead of the package originals. You can override one file without touching the other.

---

## Health Status API

```
GET {prefix}/health/status
```

Returns JSON:

```json
{
    "status": "healthy",
    "total_logs_24h": 142,
    "errors_24h": 3,
    "warnings_24h": 11,
    "last_error": { ... }
}
```

Status values: `healthy` / `warning` / `critical` — determined by thresholds:

```php
'health' => [
    'critical_errors' => 100,   // errors/24h to trigger critical
    'warning_errors'  => 10,    // errors/24h to trigger warning
],
```

---

## Eloquent Query Scopes

```php
use FastnetKSA\AppLogger\Models\ApplicationLog;

ApplicationLog::errors()->get();                          // level = error
ApplicationLog::warnings()->get();                        // level = warning
ApplicationLog::level('info')->get();                     // any level
ApplicationLog::type('payment')->get();                   // by type
ApplicationLog::dateRange('2026-01-01', '2026-01-31')->get(); // date range
```

---

## Security Notes

- **File path traversal protection:** `viewFile`, `downloadFile`, and `deleteFile` validate that the resolved real path starts with `storage_path('logs')`. Requests with `../` style paths are rejected with 404.
- `laravel.log` is protected from deletion by `deleteFile`. `clearAllFiles` truncates it to empty rather than deleting.
- Sensitive context keys are redacted at write time — they are never stored even in the `context` JSON column.

---

## Package Structure

```
packages/fastnetksa/laravel-applogger/
├── composer.json
├── config/
│   └── applogger.php                    ← default config (publish to override)
├── database/
│   └── migrations/
│       └── ..._create_application_logs_table.php
├── resources/
│   └── views/
│       └── logs/
│           ├── index.blade.php          ← database log list + dashboard
│           └── files.blade.php          ← file log manager
├── routes/
│   └── applogger.php
└── src/
    ├── AppLoggerServiceProvider.php
    ├── Http/
    │   └── Controllers/
    │       └── ApplicationLogsController.php
    ├── Logging/
    │   ├── CreateDatabaseLogger.php     ← Monolog factory
    │   └── DatabaseLogHandler.php       ← Monolog handler → DB
    └── Models/
        └── ApplicationLog.php
```

---

## Publishable Tags

| Tag | What it publishes |
|-----|------------------|
| `applogger-config` | `config/applogger.php` |
| `applogger-migrations` | `database/migrations/..._create_application_logs_table.php` |
| `applogger-views` | `resources/views/vendor/applogger/logs/` |

---

## License

MIT
