#!/bin/sh
set -e

echo "Configuring test Laravel app..."

cd /var/www/test-app

# 1. Publish config
echo "Publishing config..."
php artisan vendor:publish --tag=central-logs-config --force

# 2. Configure .env
echo "Configuring .env..."
cat >> .env << 'EOF'

# Central Logs Configuration
CENTRAL_LOGS_URL=https://logs.trofeo.id
CENTRAL_LOGS_API_KEY=cl_396ebf1a63eecf67164b6bf0e8d9422d6916fb050a6fb30a97597790e6093395
CENTRAL_LOGS_MODE=async
CENTRAL_LOGS_BATCH_ENABLED=true
CENTRAL_LOGS_SOURCE=test-laravel-app

# Redis Configuration for Queue
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PORT=6379
EOF

# 3. Configure logging.php
echo "Configuring logging..."
cat > config/logging.php << 'EOFPHP'
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', env('LOG_STACK', 'daily,central-logs')),
            'ignore_exceptions' => false,
        ],

        'central-logs' => [
            'driver' => 'monolog',
            'handler' => CentralLogs\Handler\CentralLogsHandler::class,
            'level' => env('LOG_LEVEL', 'debug'),
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel Log',
            'emoji' => ':boom:',
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'with' => [
                'stream' => 'php://stderr',
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];
EOFPHP

# 4. Add test routes
echo "Adding test routes..."
cat >> routes/web.php << 'EOFROUTES'

// Central Logs Test Routes
Route::get('/test-log', function () {
    Log::channel('central-logs')->info('Test log from web route', [
        'user_id' => 123,
        'action' => 'test',
        'timestamp' => now()->toIso8601String(),
    ]);

    return response()->json([
        'message' => 'Log sent to Central Logs',
        'timestamp' => now()->toIso8601String(),
    ]);
});

Route::get('/test-log-error', function () {
    try {
        throw new \Exception('This is a test error');
    } catch (\Exception $e) {
        Log::channel('central-logs')->error('Test error log', [
            'exception' => $e,
            'context' => 'Testing error logging',
        ]);

        return response()->json([
            'message' => 'Error log sent to Central Logs',
            'error' => $e->getMessage(),
        ]);
    }
});

Route::get('/test-log-batch', function () {
    for ($i = 1; $i <= 20; $i++) {
        Log::channel('central-logs')->info("Batch test log #{$i}", [
            'iteration' => $i,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    return response()->json([
        'message' => '20 logs sent to Central Logs (will be batched)',
    ]);
});
EOFROUTES

echo "Configuration complete!"
echo ""
echo "You can now test the package:"
echo "1. Test connection: php artisan central-logs:test"
echo "2. Visit: http://localhost:8000/test-log"
echo "3. Visit: http://localhost:8000/test-log-error"
echo "4. Visit: http://localhost:8000/test-log-batch"
