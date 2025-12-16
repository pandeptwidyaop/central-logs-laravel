<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Log;

Route::get('/', function () {
    return view('welcome');
});

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
