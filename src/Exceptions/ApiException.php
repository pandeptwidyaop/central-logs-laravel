<?php

namespace CentralLogs\Exceptions;

/**
 * Exception thrown when API communication fails.
 */
class ApiException extends CentralLogsException
{
    /**
     * Create a new API exception instance.
     *
     * @param  string  $message
     * @param  int  $code
     * @param  \Throwable|null  $previous
     */
    public function __construct(string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Create an exception for connection failure.
     */
    public static function connectionFailed(string $url, \Throwable $previous): self
    {
        return new self(
            "Failed to connect to Central Logs API at {$url}",
            0,
            $previous
        );
    }

    /**
     * Create an exception for invalid response.
     */
    public static function invalidResponse(int $statusCode, string $body): self
    {
        return new self(
            "Central Logs API returned invalid response (Status: {$statusCode}): {$body}",
            $statusCode
        );
    }

    /**
     * Create an exception for timeout.
     */
    public static function timeout(string $url, float $timeout): self
    {
        return new self(
            "Request to Central Logs API at {$url} timed out after {$timeout} seconds"
        );
    }

    /**
     * Create an exception for authentication failure.
     */
    public static function authenticationFailed(): self
    {
        return new self(
            'Central Logs API authentication failed. Please check your API key.',
            401
        );
    }
}
