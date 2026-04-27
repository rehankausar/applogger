<?php

namespace FastnetKSA\AppLogger\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationLog extends Model
{
    public const UPDATED_AT = null;

    // Log level constants (universal — not app-specific)
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_INFO    = 'info';
    public const LEVEL_DEBUG   = 'debug';

    protected $casts = [
        'context'    => 'array',
        'created_at' => 'datetime',
    ];

    public function getTable(): string
    {
        return config('applogger.table', 'application_logs');
    }

    /**
     * Return all configured log types (from config/applogger.php).
     * e.g. ['exception' => 'Exception', 'invoice' => 'Invoice', ...]
     */
    public static function types(): array
    {
        return config('applogger.types', []);
    }

    /**
     * Always fillable — tenant columns are resolved at write-time via context_fields config.
     */
    public function getFillable(): array
    {
        $base = [
            'level', 'type', 'message', 'context',
            'stack_trace', 'file', 'line',
            'url', 'method', 'ip_address', 'user_id',
        ];

        $tenantColumns = array_column(config('applogger.context_fields', []), 'column');

        return array_unique(array_merge($base, $tenantColumns));
    }

    /**
     * Universal user relationship (every Laravel app has a users table).
     */
    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model', \App\Models\User::class));
    }

    // -------------------------------------------------------------------------
    // Static logging helpers
    // -------------------------------------------------------------------------

    /**
     * Create a log entry. Tenant context fields are resolved via config.
     */
    public static function logEntry(
        string $level,
        string $type,
        string $message,
        array $context = [],
        ?\Throwable $exception = null
    ): self {
        $data = [
            'level'      => $level,
            'type'       => $type,
            'message'    => $message,
            'context'    => $context,
            'url'        => request()->fullUrl(),
            'method'     => request()->method(),
            'ip_address' => request()->ip(),
            'user_id'    => auth()->id(),
        ];

        foreach (config('applogger.context_fields', []) as $field) {
            $data[$field['column']] = is_string($field['resolver'])
                ? app($field['resolver'])()
                : (is_callable($field['resolver']) ? call_user_func($field['resolver']) : null);
        }

        if ($exception) {
            $data['stack_trace'] = $exception->getTraceAsString();
            $data['file']        = $exception->getFile();
            $data['line']        = $exception->getLine();
        }

        return static::create($data);
    }

    public static function logError(string $type, string $message, array $context = [], ?\Throwable $exception = null): self
    {
        return static::logEntry(self::LEVEL_ERROR, $type, $message, $context, $exception);
    }

    public static function logWarning(string $type, string $message, array $context = []): self
    {
        return static::logEntry(self::LEVEL_WARNING, $type, $message, $context);
    }

    public static function logInfo(string $type, string $message, array $context = []): self
    {
        return static::logEntry(self::LEVEL_INFO, $type, $message, $context);
    }

    public static function logDebug(string $type, string $message, array $context = []): self
    {
        return static::logEntry(self::LEVEL_DEBUG, $type, $message, $context);
    }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    public function scopeLevel($query, string $level)
    {
        return $query->where('level', $level);
    }

    public function scopeType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeDateRange($query, $from, $to)
    {
        return $query->whereBetween('created_at', [$from, $to]);
    }

    public function scopeErrors($query)
    {
        return $query->where('level', self::LEVEL_ERROR);
    }

    public function scopeWarnings($query)
    {
        return $query->where('level', self::LEVEL_WARNING);
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    public function getFormattedContextAttribute(): string
    {
        return is_array($this->context)
            ? json_encode($this->context, JSON_PRETTY_PRINT)
            : (string) $this->context;
    }

    public function getShortMessageAttribute(): string
    {
        return strlen($this->message) > 100
            ? substr($this->message, 0, 100).'...'
            : $this->message;
    }
}
