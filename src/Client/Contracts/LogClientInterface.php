<?php

namespace CentralLogs\Client\Contracts;

use CentralLogs\Exceptions\ApiException;

/**
 * Interface for log client implementations.
 */
interface LogClientInterface
{
    /**
     * Send a single log entry to Central Logs.
     *
     * @param  array  $logData
     * @return bool
     * @throws ApiException
     */
    public function sendLog(array $logData): bool;

    /**
     * Send multiple log entries in a batch to Central Logs.
     *
     * @param  array  $logs
     * @return bool
     * @throws ApiException
     */
    public function sendBatch(array $logs): bool;

    /**
     * Test the connection to Central Logs API.
     *
     * @return bool
     * @throws ApiException
     */
    public function testConnection(): bool;
}
