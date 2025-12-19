<?php

namespace CentralLogs;

use CentralLogs\Client\CentralLogsClient;
use CentralLogs\Client\Contracts\LogClientInterface;
use CentralLogs\Commands\TestConnectionCommand;
use CentralLogs\Support\BatchAggregator;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for Central Logs package.
 */
class CentralLogsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // Merge package config with application config
        $this->mergeConfigFrom(
            __DIR__.'/../config/central-logs.php',
            'central-logs'
        );

        // Register the log client
        $this->app->singleton(LogClientInterface::class, function ($app) {
            $apiUrl = config('central-logs.api_url');
            $apiKey = config('central-logs.api_key');

            // Return null client if configuration is missing
            if (empty($apiUrl) || empty($apiKey)) {
                // Log warning only once during boot
                if (config('central-logs.debug', false)) {
                    error_log('[CentralLogs] API URL or API Key not configured. Please publish and configure central-logs.php');
                }

                return new class implements LogClientInterface {
                    public function sendLog(array $logData): bool {
                        return true;
                    }
                    public function sendBatch(array $logs): bool {
                        return true;
                    }
                    public function testConnection(): bool {
                        return false;
                    }
                };
            }

            return new CentralLogsClient(
                $apiUrl,
                $apiKey,
                config('central-logs.http', []),
                config('central-logs.debug', false)
            );
        });

        // Register the batch aggregator as singleton
        $this->app->singleton(BatchAggregator::class, function ($app) {
            return new BatchAggregator(
                $app->make(LogClientInterface::class),
                config('central-logs.batch', []),
                config('central-logs.fallback', []),
                config('central-logs.debug', false)
            );
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration file
        $this->publishes([
            __DIR__.'/../config/central-logs.php' => config_path('central-logs.php'),
        ], 'central-logs-config');

        // Register shutdown handler to flush batches
        register_shutdown_function(function () {
            if ($this->app->bound(BatchAggregator::class)) {
                try {
                    $aggregator = $this->app->make(BatchAggregator::class);
                    $aggregator->flush();
                } catch (\Throwable $e) {
                    // Silently fail - don't break application shutdown
                    error_log(sprintf(
                        '[CentralLogs] Failed to flush on shutdown: %s',
                        $e->getMessage()
                    ));
                }
            }
        });

        // Register console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                TestConnectionCommand::class,
            ]);
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            LogClientInterface::class,
            BatchAggregator::class,
        ];
    }
}
