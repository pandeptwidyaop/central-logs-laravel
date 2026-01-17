<?php

namespace CentralLogs\Handler;

use CentralLogs\Client\Contracts\LogClientInterface;
use CentralLogs\Exceptions\ApiException;
use CentralLogs\Jobs\SendLogBatchToCentralLogs;
use CentralLogs\Jobs\SendLogToCentralLogs;
use CentralLogs\Support\BatchAggregator;
use CentralLogs\Support\LogTransformer;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Main Monolog handler for Central Logs.
 *
 * Routes logs to sync or async processing based on configuration.
 */
class CentralLogsHandler extends AbstractProcessingHandler
{
    /**
     * The log client instance.
     */
    protected LogClientInterface $client;

    /**
     * The log transformer instance.
     */
    protected LogTransformer $transformer;

    /**
     * The batch aggregator instance (when batch mode is enabled).
     */
    protected ?BatchAggregator $batchAggregator = null;

    /**
     * Configuration array.
     */
    protected array $config;

    /**
     * Whether to use async mode (queue).
     */
    protected bool $async;

    /**
     * Whether batch mode is enabled.
     */
    protected bool $batchEnabled;

    /**
     * Fallback configuration.
     */
    protected array $fallbackConfig;

    /**
     * Debug mode flag.
     */
    protected bool $debug;

    /**
     * Create a new Central Logs handler instance.
     *
     * @param  array  $config
     * @param  int|Level|null  $level
     * @param  bool  $bubble
     */
    public function __construct(array $config = [], int|Level $level = null, bool $bubble = true)
    {
        $normalizedLevel = $this->normalizeLevel($level);
        parent::__construct($normalizedLevel, $bubble);

        // Merge with Laravel config if not provided
        $laravelConfig = config('central-logs', []);
        $this->config = array_merge($laravelConfig, $config);

        $this->async = ($this->config['mode'] ?? 'async') === 'async';
        $this->batchEnabled = $this->config['batch']['enabled'] ?? false;
        $this->fallbackConfig = $this->config['fallback'] ?? [];
        $this->debug = $this->config['debug'] ?? false;

        // Initialize client
        $this->client = app(LogClientInterface::class);

        // Initialize transformer
        $this->transformer = new LogTransformer(
            $this->config['context'] ?? [],
            $this->config['source'] ?? 'laravel'
        );

        // Initialize batch aggregator if batch mode is enabled
        if ($this->batchEnabled) {
            $this->batchAggregator = app(BatchAggregator::class);
        }
    }

    /**
     * Write the log record.
     *
     * @param  array|LogRecord  $record
     * @return void
     */
    protected function write(array|LogRecord $record): void
    {
        try {
            // Transform the log record
            $logData = $this->transformer->transform($record);

            // Route to appropriate handler
            if ($this->batchEnabled) {
                $this->handleBatchMode($logData);
            } elseif ($this->async) {
                $this->handleAsyncMode($logData);
            } else {
                $this->handleSyncMode($logData);
            }
        } catch (\Throwable $e) {
            // Don't break the application if logging fails
            $this->handleError($e, $record);
        }
    }

    /**
     * Handle log in batch mode.
     *
     * @param  array  $logData
     * @return void
     */
    protected function handleBatchMode(array $logData): void
    {
        if (! $this->batchAggregator) {
            // Fallback to non-batch mode
            if ($this->async) {
                $this->handleAsyncMode($logData);
            } else {
                $this->handleSyncMode($logData);
            }

            return;
        }

        // Add to batch aggregator
        $this->batchAggregator->add($logData);

        $this->logDebug('Log added to batch');
    }

    /**
     * Handle log in async mode (queue).
     *
     * @param  array  $logData
     * @return void
     */
    protected function handleAsyncMode(array $logData): void
    {
        $queueConfig = $this->config['queue'] ?? [];

        SendLogToCentralLogs::dispatch(
            $logData,
            $this->config['api_url'] ?? config('central-logs.api_url'),
            $this->config['api_key'] ?? config('central-logs.api_key')
        )
            ->onConnection($queueConfig['connection'] ?? null)
            ->onQueue($queueConfig['name'] ?? 'central-logs');

        $this->logDebug('Log dispatched to queue');
    }

    /**
     * Handle log in sync mode.
     *
     * @param  array  $logData
     * @return void
     */
    protected function handleSyncMode(array $logData): void
    {
        try {
            $this->client->sendLog($logData);
            $this->logDebug('Log sent synchronously');
        } catch (ApiException $e) {
            $this->logDebug('Sync send failed: '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Handle logging errors.
     *
     * @param  \Throwable  $e
     * @param  array|LogRecord  $record
     * @return void
     */
    protected function handleError(\Throwable $e, array|LogRecord $record): void
    {
        // Use fallback channel if enabled
        if ($this->fallbackConfig['enabled'] ?? true) {
            $this->logToFallback($record, $e);
        }

        // Log to error_log as last resort
        error_log(sprintf(
            '[CentralLogs] Failed to process log: %s - Original message: %s',
            $e->getMessage(),
            $record['message']
        ));
    }

    /**
     * Log to fallback channel.
     *
     * @param  array|LogRecord  $record
     * @param  \Throwable  $e
     * @return void
     */
    protected function logToFallback(array|LogRecord $record, \Throwable $e): void
    {
        try {
            $fallbackChannel = $this->fallbackConfig['channel'] ?? 'stack';

            // Avoid circular logging by checking if we're already in fallback
            if ($fallbackChannel === 'central-logs') {
                return;
            }

            Log::channel($fallbackChannel)->log(
                $this->getLevelName($record['level']),
                $record['message'],
                $record['context']
            );
        } catch (\Throwable $fallbackException) {
            // Silently fail - we don't want to break the application
            error_log(sprintf(
                '[CentralLogs] Fallback logging failed: %s',
                $fallbackException->getMessage()
            ));
        }
    }

    /**
     * Log debug message if debug mode is enabled.
     *
     * @param  string  $message
     * @return void
     */
    protected function logDebug(string $message): void
    {
        if ($this->debug) {
            error_log(sprintf('[CentralLogs Handler] %s', $message));
        }
    }

    /**
     * Get the batch aggregator instance.
     *
     * @return BatchAggregator|null
     */
    public function getBatchAggregator(): ?BatchAggregator
    {
        return $this->batchAggregator;
    }

    /**
     * Normalize level parameter for Monolog 2/3 compatibility.
     *
     * @param  int|Level|null  $level
     * @return int|Level
     */
    protected function normalizeLevel(int|Level|null $level): int|Level
    {
        if ($level === null) {
            // Monolog 3: Level::Debug, Monolog 2: Logger::DEBUG (100)
            return class_exists(Level::class) ? Level::Debug : 100;
        }

        return $level;
    }

    /**
     * Get PSR log level name from Monolog level.
     *
     * @param  int|Level  $level
     * @return string
     */
    protected function getLevelName(int|Level $level): string
    {
        // Monolog 3: Level enum
        if ($level instanceof Level) {
            return $level->toPsrLogLevel();
        }

        // Monolog 2: integer constants
        return match ($level) {
            100 => 'debug',
            200 => 'info',
            250 => 'notice',
            300 => 'warning',
            400 => 'error',
            500 => 'critical',
            550 => 'alert',
            600 => 'emergency',
            default => 'info',
        };
    }

    /**
     * Flush any pending batched logs.
     *
     * @return void
     */
    public function close(): void
    {
        if ($this->batchAggregator) {
            $this->batchAggregator->flush();
        }

        parent::close();
    }
}
