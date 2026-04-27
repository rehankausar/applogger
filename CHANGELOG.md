# Changelog

All notable changes to this package are documented here.

## [1.0.0] — 2026-04-27

### Added
- Initial release, extracted from the `fastnetksa/invoice` monorepo application
- `DatabaseLogHandler` — Monolog 3 handler that persists log records to the `application_logs` database table
- `CreateDatabaseLogger` — Monolog factory invokable for use as a Laravel custom log channel driver
- `ApplicationLog` Eloquent model with:
  - Level constants (`LEVEL_ERROR`, `LEVEL_WARNING`, `LEVEL_INFO`, `LEVEL_DEBUG`)
  - Static logging helpers: `logError()`, `logWarning()`, `logInfo()`, `logDebug()`, `logEntry()`
  - Query scopes: `errors()`, `warnings()`, `level()`, `type()`, `dateRange()`
  - Accessors: `formatted_context`, `short_message`
  - Config-driven `getFillable()` — tenant columns included automatically
  - Dynamic `belongsTo` relationships registered via `resolveRelationUsing()` from config
- `ApplicationLogsController` with:
  - `index` — DataTables server-side log list with level/type/date/user filters
  - `show` — JSON detail endpoint including dynamic relationship display values
  - `cleanup` — delete log records older than N days
  - `health` — JSON health status (24h error/warning counts, configurable thresholds)
  - `fileLogs` — list Laravel log files from `storage/logs/`
  - `viewFile` — read last N lines of a log file (path-traversal protected)
  - `downloadFile` — stream log file download (path-traversal protected)
  - `deleteFile` — delete a log file (`laravel.log` is protected)
  - `clearAllFiles` — delete all log files except `laravel.log` (which is truncated to empty)
- Config (`config/applogger.php`) with sections for:
  - `types` — domain-specific log type vocabulary
  - `type_keywords` — keyword → type auto-detection map
  - `default_type` — fallback type when no keyword matches
  - `context_fields` — tenant columns populated via invokable resolver classes
  - `relationships` — dynamic `belongsTo` definitions for the detail view
  - `sensitive_keys` — keys redacted from context before storage
  - `routes` — enable/disable package routes, prefix, name, middleware
  - `layout` — Blade layout path (`layout.value`)
  - `table` — database table name (default: `application_logs`)
  - `channel` — Monolog channel name (default: `database`)
  - `health` — `critical_errors` / `warning_errors` thresholds per 24h
- Base migration with universal columns; tenant columns added by the host app after publishing
- Blade views: `logs/index.blade.php` (dashboard + DataTable) and `logs/files.blade.php` (file manager)
- `AppLoggerServiceProvider` with publishable config, migration, and views; conditional route loading

### Fixed
- `ApplicationLog::getFillable()` visibility changed from `protected` to `public` — required for PHP 8.3 compatibility (Laravel 12 changed the parent `Model::getFillable()` signature to `public`)
- Package source views refactored from a broken `@if($layoutType) ... @extends ... @endif` pattern (Blade compile-time directives cannot be inside runtime `@if` blocks) to a direct `@extends(config('applogger.layout.value'))` call
- Default `layout.type` config key removed — `@extends` is now the only rendering strategy; apps that need component-style layouts should publish and customise views
- `layout.value` default updated to `layouts.app` (standard Laravel Breeze layout path)
