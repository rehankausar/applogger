<?php

namespace FastnetKSA\AppLogger\Logging;

use FastnetKSA\AppLogger\Models\ApplicationLog;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DatabaseLogHandler extends AbstractProcessingHandler
{
    public function __construct($level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
    }

    protected function write(LogRecord $record): void
    {
        try {
            $context   = $record->context ?? [];
            $exception = $context['exception'] ?? null;
            $type      = $context['type'] ?? $this->determineTypeFromMessage($record->message);

            $data = [
                'level'      => strtolower($record->level->getName()),
                'type'       => $type,
                'message'    => $record->message,
                'context'    => $this->sanitizeContext($context),
                'url'        => $context['url']     ?? (request()->fullUrl() ?? null),
                'method'     => $context['method']  ?? (request()->method()  ?? null),
                'ip_address' => $context['ip']      ?? (request()->ip()       ?? null),
                'user_id'    => $context['user_id'] ?? auth()->id(),
            ];

            // Resolve tenant context fields from config
            foreach (config('applogger.context_fields', []) as $field) {
                $col = $field['column'];
                if (isset($context[$col])) {
                    $data[$col] = $context[$col];
                } else {
                    $data[$col] = is_string($field['resolver'])
                        ? app($field['resolver'])()
                        : (is_callable($field['resolver']) ? call_user_func($field['resolver']) : null);
                }
            }

            if ($exception instanceof \Throwable) {
                $data['stack_trace'] = $exception->getTraceAsString();
                $data['file']        = $exception->getFile();
                $data['line']        = $exception->getLine();
            }

            ApplicationLog::create($data);
        } catch (\Exception $e) {
            // Fail silently — logging must never break the application
        }
    }

    protected function sanitizeContext(array $context): array
    {
        unset($context['exception']);

        foreach (config('applogger.sensitive_keys', []) as $key) {
            if (isset($context[$key])) {
                $context[$key] = '[REDACTED]';
            }
        }

        return $context;
    }

    /**
     * Auto-detect log type from message keywords (config-driven).
     * First match wins; falls back to config('applogger.default_type').
     */
    protected function determineTypeFromMessage(string $message): string
    {
        $message  = strtolower($message);
        $keywords = config('applogger.type_keywords', []);

        foreach ($keywords as $keyword => $type) {
            if (str_contains($message, (string) $keyword)) {
                return $type;
            }
        }

        return config('applogger.default_type', 'system');
    }
}
