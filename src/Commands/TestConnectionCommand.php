<?php

namespace CentralLogs\Commands;

use CentralLogs\Client\Contracts\LogClientInterface;
use CentralLogs\Exceptions\ApiException;
use Illuminate\Console\Command;

/**
 * Artisan command to test connection to Central Logs API.
 */
class TestConnectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'central-logs:test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test connection to Central Logs API';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(LogClientInterface $client): int
    {
        $this->info('Testing connection to Central Logs...');
        $this->newLine();

        // Display configuration
        $this->info('Configuration:');
        $this->line('  API URL: '.config('central-logs.api_url'));
        $this->line('  Mode: '.config('central-logs.mode'));
        $this->line('  Batch Enabled: '.(config('central-logs.batch.enabled') ? 'Yes' : 'No'));
        $this->line('  Source: '.config('central-logs.source'));
        $this->newLine();

        // Check API key
        if (empty(config('central-logs.api_key'))) {
            $this->error('❌ API key is not configured!');
            $this->line('  Please set CENTRAL_LOGS_API_KEY in your .env file.');

            return self::FAILURE;
        }

        // Test connection
        try {
            $this->info('Sending test log to Central Logs...');

            $success = $client->testConnection();

            if ($success) {
                $this->newLine();
                $this->info('✅ Connection successful!');
                $this->line('  Your Laravel application can now send logs to Central Logs.');

                return self::SUCCESS;
            }

            $this->newLine();
            $this->error('❌ Connection failed with unknown error.');

            return self::FAILURE;
        } catch (ApiException $e) {
            $this->newLine();
            $this->error('❌ Connection failed!');
            $this->line('  Error: '.$e->getMessage());
            $this->newLine();

            $this->info('Troubleshooting tips:');
            $this->line('  1. Verify your API key is correct');
            $this->line('  2. Check that Central Logs is running and accessible');
            $this->line('  3. Verify the API URL is correct');
            $this->line('  4. Check your network/firewall settings');

            if (config('central-logs.debug')) {
                $this->newLine();
                $this->line('Exception details: '.$e->__toString());
            }

            return self::FAILURE;
        }
    }
}
