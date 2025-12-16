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
 * Queue job for sending a single log entry asynchronously.
 */
class SendLogToCentralLogs implements ShouldQueue
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
    public int $timeout = 30;

    /**
     * The log data to send.
     */
    protected array $logData;

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
     * @param  array  $logData
     * @param  string  $apiUrl
     * @param  string  $apiKey
     */
    public function __construct(array $logData, string $apiUrl, string $apiKey)
    {
        $this->logData = $logData;
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

        $client->sendLog($this->logData);
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
                Log::channel($fallbackChannel)->log(
                    strtolower($this->logData['level']),
                    $this->logData['message'],
                    $this->logData['metadata'] ?? []
                );

                Log::channel($fallbackChannel)->error(
                    'Failed to send log to Central Logs after '.$this->tries.' attempts',
                    [
                        'exception' => $exception->getMessage(),
                        'log_data' => $this->logData,
                    ]
                );
            } catch (\Throwable $e) {
                // Silently fail
                error_log(sprintf(
                    '[CentralLogs] Job failed and fallback logging failed: %s',
                    $e->getMessage()
                ));
            }
        }
    }
}
