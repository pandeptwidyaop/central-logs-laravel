<?php

namespace CentralLogs\Support;

use CentralLogs\Client\Contracts\LogClientInterface;
use CentralLogs\Exceptions\ApiException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Aggregates logs in batches for efficient transmission.
 */
class BatchAggregator
{
    /**
     * The collected logs waiting to be flushed.
     */
    protected array $logs = [];

    /**
     * Timestamp of when the first log was added to current batch.
     */
    protected ?Carbon $firstLogTime = null;

    /**
     * The log client instance.
     */
    protected LogClientInterface $client;

    /**
     * Batch configuration.
     */
    protected array $batchConfig;

    /**
     * Fallback configuration.
     */
    protected array $fallbackConfig;

    /**
     * Debug mode flag.
     */
    protected bool $debug;

    /**
     * Whether the aggregator is currently flushing.
     */
    protected bool $isFlushing = false;

    /**
     * Create a new batch aggregator instance.
     *
     * @param  LogClientInterface  $client
     * @param  array  $batchConfig
     * @param  array  $fallbackConfig
     * @param  bool  $debug
     */
    public function __construct(
        LogClientInterface $client,
        array $batchConfig,
        array $fallbackConfig = [],
        bool $debug = false
    ) {
        $this->client = $client;
        $this->batchConfig = $batchConfig;
        $this->fallbackConfig = $fallbackConfig;
        $this->debug = $debug;
    }

    /**
     * Add a log to the batch.
     *
     * @param  array  $log
     * @return void
     */
    public function add(array $log): void
    {
        if ($this->isFlushing) {
            // Skip adding logs while flushing to avoid infinite loops
            return;
        }

        $this->logs[] = $log;
        $this->firstLogTime ??= now();

        if ($this->shouldFlush()) {
            $this->flush();
        }
    }

    /**
     * Determine if the batch should be flushed.
     *
     * @return bool
     */
    protected function shouldFlush(): bool
    {
        // Check batch size
        if ($this->isBatchSizeReached()) {
            $this->logDebug('Flushing: batch size reached');

            return true;
        }

        // Check timeout
        if ($this->isTimeoutReached()) {
            $this->logDebug('Flushing: timeout reached');

            return true;
        }

        // Check memory threshold
        if ($this->isMemoryThresholdReached()) {
            $this->logDebug('Flushing: memory threshold reached');

            return true;
        }

        return false;
    }

    /**
     * Check if batch size has been reached.
     *
     * @return bool
     */
    protected function isBatchSizeReached(): bool
    {
        $batchSize = $this->batchConfig['size'] ?? 50;

        return count($this->logs) >= $batchSize;
    }

    /**
     * Check if timeout has been reached.
     *
     * @return bool
     */
    protected function isTimeoutReached(): bool
    {
        if (! $this->firstLogTime) {
            return false;
        }

        $timeout = $this->batchConfig['timeout'] ?? 5; // seconds

        return $this->firstLogTime->diffInSeconds(now()) >= $timeout;
    }

    /**
     * Check if memory threshold has been reached.
     *
     * @return bool
     */
    protected function isMemoryThresholdReached(): bool
    {
        $memoryLimit = $this->getMemoryLimit();

        if ($memoryLimit === -1) {
            return false; // No memory limit
        }

        $currentUsage = memory_get_usage(true);
        $threshold = $memoryLimit * 0.8; // 80% threshold

        return $currentUsage >= $threshold;
    }

    /**
     * Get PHP memory limit in bytes.
     *
     * @return int
     */
    protected function getMemoryLimit(): int
    {
        $memoryLimit = ini_get('memory_limit');

        if ($memoryLimit === '-1') {
            return -1;
        }

        return $this->convertToBytes($memoryLimit);
    }

    /**
     * Convert memory limit string to bytes.
     *
     * @param  string  $value
     * @return int
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        return match ($last) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    /**
     * Flush all collected logs to Central Logs.
     *
     * @return void
     */
    public function flush(): void
    {
        if (empty($this->logs) || $this->isFlushing) {
            return;
        }

        // Acquire lock to prevent concurrent flushes
        $lockKey = 'central-logs:flush-lock';
        $lockAcquired = Cache::lock($lockKey, 10)->get();

        if (! $lockAcquired) {
            $this->logDebug('Could not acquire flush lock, skipping');

            return;
        }

        $this->isFlushing = true;

        try {
            // Copy and clear logs
            $batch = $this->logs;
            $this->logs = [];
            $this->firstLogTime = null;

            $this->logDebug(sprintf('Flushing batch of %d logs', count($batch)));

            // Send to Central Logs
            $this->client->sendBatch($batch);

            $this->logDebug('Batch flushed successfully');
        } catch (ApiException $e) {
            $this->logDebug('Batch flush failed: '.$e->getMessage());

            // Handle failure with fallback
            $this->handleFlushFailure($batch, $e);
        } finally {
            $this->isFlushing = false;
            Cache::lock($lockKey)->release();
        }
    }

    /**
     * Handle flush failure by logging to fallback channel.
     *
     * @param  array  $batch
     * @param  ApiException  $e
     * @return void
     */
    protected function handleFlushFailure(array $batch, ApiException $e): void
    {
        if (! ($this->fallbackConfig['enabled'] ?? true)) {
            return;
        }

        $fallbackChannel = $this->fallbackConfig['channel'] ?? 'stack';

        try {
            // Log each failed log entry to fallback channel
            foreach ($batch as $log) {
                Log::channel($fallbackChannel)->log(
                    strtolower($log['level']),
                    $log['message'],
                    $log['metadata'] ?? []
                );
            }

            // Log the error once
            Log::channel($fallbackChannel)->error(
                'Central Logs batch flush failed',
                [
                    'exception' => $e->getMessage(),
                    'batch_size' => count($batch),
                ]
            );
        } catch (\Throwable $fallbackException) {
            // Silently fail - we don't want to break the application
            error_log(sprintf(
                '[CentralLogs] Fallback failed: %s',
                $fallbackException->getMessage()
            ));
        }
    }

    /**
     * Get the current batch size.
     *
     * @return int
     */
    public function getBatchSize(): int
    {
        return count($this->logs);
    }

    /**
     * Check if batch is empty.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return empty($this->logs);
    }

    /**
     * Clear all logs without flushing.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->logs = [];
        $this->firstLogTime = null;
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
            error_log(sprintf('[CentralLogs BatchAggregator] %s', $message));
        }
    }
}
