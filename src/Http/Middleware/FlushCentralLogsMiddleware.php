<?php

namespace CentralLogs\Http\Middleware;

use CentralLogs\Support\BatchAggregator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to flush Central Logs batches after each request.
 *
 * This ensures logs are sent even if batch size or timeout hasn't been reached.
 */
class FlushCentralLogsMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            if (app()->bound(BatchAggregator::class)) {
                $aggregator = app()->make(BatchAggregator::class);

                // Only flush if there are logs waiting
                if (!$aggregator->isEmpty()) {
                    $aggregator->flush();
                }
            }
        } catch (\Throwable $e) {
            // Silently fail - don't break the application
            error_log(sprintf(
                '[CentralLogs] Middleware flush failed: %s',
                $e->getMessage()
            ));
        }
    }
}
