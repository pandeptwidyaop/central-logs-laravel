<?php

namespace CentralLogs\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Monolog\Level;
use Monolog\LogRecord;
use Throwable;

/**
 * Transforms Monolog log records to Central Logs format.
 */
class LogTransformer
{
    /**
     * Configuration for context enrichment.
     */
    protected array $contextConfig;

    /**
     * Source identifier for the application.
     */
    protected string $source;

    /**
     * Custom metadata to include with every log.
     */
    protected array $customMetadata;

    /**
     * Create a new log transformer instance.
     *
     * @param  array  $contextConfig
     * @param  string  $source
     */
    public function __construct(array $contextConfig = [], string $source = 'laravel')
    {
        $this->contextConfig = $contextConfig;
        $this->source = $source;
        $this->customMetadata = $contextConfig['custom'] ?? [];
    }

    /**
     * Transform Monolog log record to Central Logs format.
     *
     * @param  LogRecord  $record
     * @return array
     */
    public function transform(LogRecord $record): array
    {
        return [
            'level' => $this->mapLevel($record->level),
            'message' => $record->message,
            'metadata' => $this->buildMetadata($record),
            'source' => $this->source,
            'timestamp' => $record->datetime->format('Y-m-d\TH:i:s.u\Z'),
        ];
    }

    /**
     * Map Monolog level to Central Logs level.
     *
     * @param  Level  $level
     * @return string
     */
    protected function mapLevel(Level $level): string
    {
        return match ($level) {
            Level::Debug => 'DEBUG',
            Level::Info, Level::Notice => 'INFO',
            Level::Warning => 'WARN',
            Level::Error => 'ERROR',
            Level::Critical, Level::Alert, Level::Emergency => 'CRITICAL',
        };
    }

    /**
     * Build metadata from log record and enrichment config.
     *
     * @param  LogRecord  $record
     * @return array
     */
    protected function buildMetadata(LogRecord $record): array
    {
        $metadata = [];

        // Include context from log call
        if (! empty($record->context)) {
            $metadata['context'] = $this->processContext($record->context);
        }

        // Include extra data
        if (! empty($record->extra)) {
            $metadata['extra'] = $record->extra;
        }

        // Add Laravel-specific enrichment
        if ($this->contextConfig['include_user'] ?? true) {
            $metadata = array_merge($metadata, $this->getUserContext());
        }

        if ($this->contextConfig['include_request'] ?? true) {
            $metadata = array_merge($metadata, $this->getRequestContext());
        }

        if ($this->contextConfig['include_session'] ?? true) {
            $metadata = array_merge($metadata, $this->getSessionContext());
        }

        if ($this->contextConfig['include_environment'] ?? true) {
            $metadata = array_merge($metadata, $this->getEnvironmentContext());
        }

        // Add custom metadata
        if (! empty($this->customMetadata)) {
            $metadata['custom'] = $this->customMetadata;
        }

        return $metadata;
    }

    /**
     * Process context data, handling exceptions specially.
     *
     * @param  array  $context
     * @return array
     */
    protected function processContext(array $context): array
    {
        $processed = [];

        foreach ($context as $key => $value) {
            if ($value instanceof Throwable) {
                $processed['exception'] = $this->exceptionToArray($value);
            } else {
                $processed[$key] = $this->sanitizeValue($value);
            }
        }

        return $processed;
    }

    /**
     * Convert exception to array format.
     *
     * @param  Throwable  $exception
     * @return array
     */
    protected function exceptionToArray(Throwable $exception): array
    {
        return [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => collect($exception->getTrace())
                ->take(10) // Limit trace depth
                ->map(function ($trace) {
                    return [
                        'file' => $trace['file'] ?? 'unknown',
                        'line' => $trace['line'] ?? 0,
                        'function' => $trace['function'] ?? 'unknown', // @phpstan-ignore-line
                        'class' => $trace['class'] ?? null,
                    ];
                })
                ->toArray(),
        ];
    }

    /**
     * Get authenticated user context.
     *
     * @return array
     */
    protected function getUserContext(): array
    {
        if (! app()->bound('auth')) {
            return [];
        }

        try {
            $user = Auth::user();

            if ($user) {
                return [
                    'user_id' => $user->getAuthIdentifier(),
                    'user_email' => $user->email ?? null,
                ];
            }
        } catch (\Throwable $e) {
            // Silently fail if auth system not available
        }

        return [];
    }

    /**
     * Get HTTP request context.
     *
     * @return array
     */
    protected function getRequestContext(): array
    {
        if (! app()->bound('request') || app()->runningInConsole()) {
            return [];
        }

        try {
            return [
                'request_id' => Request::header('X-Request-ID') ?? uniqid('req_'),
                'ip' => Request::ip(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
                'user_agent' => Request::userAgent(),
            ];
        } catch (\Throwable $e) {
            // Silently fail if request not available
        }

        return [];
    }

    /**
     * Get session context.
     *
     * @return array
     */
    protected function getSessionContext(): array
    {
        if (! app()->bound('session') || app()->runningInConsole()) {
            return [];
        }

        try {
            if (session()->isStarted()) {
                return [
                    'session_id' => session()->getId(),
                ];
            }
        } catch (\Throwable $e) {
            // Silently fail if session not available
        }

        return [];
    }

    /**
     * Get environment context.
     *
     * @return array
     */
    protected function getEnvironmentContext(): array
    {
        return [
            'environment' => app()->environment(),
            'app_name' => config('app.name'),
            'hostname' => gethostname() ?: 'unknown',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
        ];
    }

    /**
     * Sanitize value for safe transmission.
     *
     * @param  mixed  $value
     * @return mixed
     */
    protected function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map([$this, 'sanitizeValue'], $value);
        }

        if (is_object($value)) {
            if (method_exists($value, 'toArray')) {
                return $value->toArray();
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }

            return get_class($value);
        }

        return $value;
    }
}
