<?php

namespace CentralLogs\Jobs;

use CentralLogs\Client\CentralLogsClient;
use CentralLogs\Exceptions\ApiException;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queue job for sending a batch of log entries asynchronously.
 */
class SendLogBatchToCentralLogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public array $backoff = [5, 15, 30];

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The batch of logs to send.
     */
    protected array $logs;

    /**
     * The Central Logs API URL.
     */
    protected string $apiUrl;

    /**
     * The API key for authentication.
     */
    protected string $apiKey;

    /**
     * Create a new job instance.
     *
     * @param  array  $logs
     * @param  string  $apiUrl
     * @param  string  $apiKey
     */
    public function __construct(array $logs, string $apiUrl, string $apiKey)
    {
        $this->logs = $logs;
        $this->apiUrl = $apiUrl;
        $this->apiKey = $apiKey;
    }

    /**
     * Execute the job.
     *
     * @return void
     * @throws ApiException
     */
    public function handle(): void
    {
        $client = new CentralLogsClient(
            $this->apiUrl,
            $this->apiKey,
            config('central-logs.http', []),
            config('central-logs.debug', false)
        );

        $client->sendBatch($this->logs);
    }

    /**
     * Handle a job failure.
     *
     * @param  Throwable  $exception
     * @return void
     */
    public function failed(Throwable $exception): void
    {
        // Log to fallback channel
        $fallbackConfig = config('central-logs.fallback', []);

        if ($fallbackConfig['enabled'] ?? true) {
            $fallbackChannel = $fallbackConfig['channel'] ?? 'stack';

            try {
                // Log each failed log entry to fallback channel
                foreach ($this->logs as $log) {
                    Log::channel($fallbackChannel)->log(
                        strtolower($log['level']),
                        $log['message'],
                        $log['metadata'] ?? []
                    );
                }

                // Log the error once
                Log::channel($fallbackChannel)->error(
                    'Failed to send log batch to Central Logs after '.$this->tries.' attempts',
                    [
                        'exception' => $exception->getMessage(),
                        'batch_size' => count($this->logs),
                    ]
                );
            } catch (\Throwable $e) {
                // Silently fail
                error_log(sprintf(
                    '[CentralLogs] Batch job failed and fallback logging failed: %s',
                    $e->getMessage()
                ));
            }
        }
    }
}
