<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Central Logs API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the Central Logs API endpoint and authentication.
    | Get your API key from your Central Logs dashboard.
    |
    */

    'api_url' => env('CENTRAL_LOGS_URL', 'http://localhost:8080'),
    'api_key' => env('CENTRAL_LOGS_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Logging Mode
    |--------------------------------------------------------------------------
    |
    | Determines how logs are sent to Central Logs:
    | - 'sync': Logs are sent immediately (blocks request)
    | - 'async': Logs are queued and sent via Laravel Queue
    |
    | Note: 'async' mode requires a queue driver to be configured.
    |
    */

    'mode' => env('CENTRAL_LOGS_MODE', 'async'),

    /*
    |--------------------------------------------------------------------------
    | Batch Configuration
    |--------------------------------------------------------------------------
    |
    | Enable batch processing to reduce API calls and improve performance.
    | Logs will be aggregated and sent in batches.
    |
    */

    'batch' => [
        'enabled' => env('CENTRAL_LOGS_BATCH_ENABLED', true),

        // Maximum number of logs per batch (API limit: 100)
        'size' => env('CENTRAL_LOGS_BATCH_SIZE', 50),

        // Maximum time in seconds before flushing batch
        'timeout' => env('CENTRAL_LOGS_BATCH_TIMEOUT', 5),

        // Queue name for batch processing
        'queue' => env('CENTRAL_LOGS_BATCH_QUEUE', 'central-logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Source Identifier
    |--------------------------------------------------------------------------
    |
    | Identifier for your application in Central Logs.
    | This helps you distinguish logs from different applications.
    |
    */

    'source' => env('CENTRAL_LOGS_SOURCE', env('APP_NAME', 'laravel')),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the HTTP client used to communicate with Central Logs API.
    |
    */

    'http' => [
        // Request timeout in seconds
        'timeout' => env('CENTRAL_LOGS_TIMEOUT', 5),

        // Retry configuration
        'retry' => [
            'times' => env('CENTRAL_LOGS_RETRY_TIMES', 3),
            'delay' => env('CENTRAL_LOGS_RETRY_DELAY', 100), // milliseconds
        ],

        // Verify SSL certificates
        'verify_ssl' => env('CENTRAL_LOGS_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Fallback Configuration
    |--------------------------------------------------------------------------
    |
    | When Central Logs is unavailable, logs can be sent to a fallback channel.
    |
    */

    'fallback' => [
        'enabled' => env('CENTRAL_LOGS_FALLBACK', true),
        'channel' => env('CENTRAL_LOGS_FALLBACK_CHANNEL', 'stack'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Context Enrichment
    |--------------------------------------------------------------------------
    |
    | Automatically include additional context with every log entry.
    |
    */

    'context' => [
        // Include authenticated user information
        'include_user' => true,

        // Include HTTP request information
        'include_request' => true,

        // Include session information
        'include_session' => true,

        // Include environment information
        'include_environment' => true,

        // Custom metadata to include with every log
        'custom' => [
            // 'app_version' => '1.0.0',
            // 'datacenter' => 'us-east-1',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Level
    |--------------------------------------------------------------------------
    |
    | Minimum log level to send to Central Logs.
    | Available levels: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY
    |
    */

    'level' => env('CENTRAL_LOGS_LEVEL', 'DEBUG'),

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the queue connection and name for async logging.
    |
    */

    'queue' => [
        'connection' => env('CENTRAL_LOGS_QUEUE_CONNECTION'),
        'name' => env('CENTRAL_LOGS_QUEUE_NAME', 'central-logs'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Mode
    |--------------------------------------------------------------------------
    |
    | Enable debug mode to log all API requests and responses.
    | Useful for troubleshooting connection issues.
    |
    */

    'debug' => env('CENTRAL_LOGS_DEBUG', false),
];
