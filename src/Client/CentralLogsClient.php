<?php

namespace CentralLogs\Client;

use CentralLogs\Client\Contracts\LogClientInterface;
use CentralLogs\Exceptions\ApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client for communicating with Central Logs API.
 */
class CentralLogsClient implements LogClientInterface
{
    /**
     * The Guzzle HTTP client instance.
     */
    protected Client $client;

    /**
     * The Central Logs API URL.
     */
    protected string $apiUrl;

    /**
     * The API key for authentication.
     */
    protected string $apiKey;

    /**
     * HTTP client configuration.
     */
    protected array $config;

    /**
     * Debug mode flag.
     */
    protected bool $debug;

    /**
     * Create a new Central Logs client instance.
     *
     * @param  string  $apiUrl
     * @param  string  $apiKey
     * @param  array  $config
     * @param  bool  $debug
     */
    public function __construct(string $apiUrl, string $apiKey, array $config = [], bool $debug = false)
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->apiKey = $apiKey;
        $this->config = $config;
        $this->debug = $debug;

        $this->client = $this->createClient();
    }

    /**
     * Send a single log entry to Central Logs.
     *
     * @param  array  $logData
     * @return bool
     * @throws ApiException
     */
    public function sendLog(array $logData): bool
    {
        try {
            $response = $this->client->post('/api/v1/logs', [
                'json' => $logData,
            ]);

            return $response->getStatusCode() >= 200 && $response->getStatusCode() < 300;
        } catch (RequestException $e) {
            $this->handleRequestException($e);
        } catch (ConnectException $e) {
            throw ApiException::connectionFailed($this->apiUrl, $e);
        }

        return false;
    }

    /**
     * Send multiple log entries in a batch to Central Logs.
     *
     * @param  array  $logs
     * @return bool
     * @throws ApiException
     */
    public function sendBatch(array $logs): bool
    {
        if (empty($logs)) {
            return true;
        }

        // Split into chunks of 100 (API limit)
        $chunks = array_chunk($logs, 100);

        foreach ($chunks as $chunk) {
            try {
                $response = $this->client->post('/api/v1/logs/batch', [
                    'json' => ['logs' => $chunk],
                ]);

                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    throw ApiException::invalidResponse(
                        $response->getStatusCode(),
                        $response->getBody()->getContents()
                    );
                }
            } catch (RequestException $e) {
                $this->handleRequestException($e);
            } catch (ConnectException $e) {
                throw ApiException::connectionFailed($this->apiUrl, $e);
            }
        }

        return true;
    }

    /**
     * Test the connection to Central Logs API.
     *
     * @return bool
     * @throws ApiException
     */
    public function testConnection(): bool
    {
        try {
            $testLog = [
                'level' => 'INFO',
                'message' => 'Connection test from Laravel package',
                'metadata' => ['test' => true],
                'source' => 'central-logs-laravel',
                'timestamp' => now()->toIso8601String(),
            ];

            return $this->sendLog($testLog);
        } catch (ApiException $e) {
            throw $e;
        }
    }

    /**
     * Create the Guzzle HTTP client with retry middleware.
     *
     * @return Client
     */
    protected function createClient(): Client
    {
        $stack = HandlerStack::create();

        // Add retry middleware
        $stack->push(Middleware::retry(
            $this->retryDecider(),
            $this->retryDelay()
        ));

        // Add debug middleware if enabled
        if ($this->debug) {
            $stack->push($this->debugMiddleware());
        }

        return new Client([
            'base_uri' => $this->apiUrl,
            'handler' => $stack,
            'timeout' => $this->config['timeout'] ?? 5,
            'connect_timeout' => 3,
            'headers' => [
                'X-API-Key' => $this->apiKey,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'User-Agent' => 'CentralLogs-Laravel/1.0',
            ],
            'verify' => $this->config['verify_ssl'] ?? true,
            'http_errors' => true,
        ]);
    }

    /**
     * Determine if the request should be retried.
     *
     * @return callable
     */
    protected function retryDecider(): callable
    {
        return function (
            int $retries,
            RequestInterface $request,
            ?ResponseInterface $response = null,
            ?GuzzleException $exception = null
        ) {
            $maxRetries = $this->config['retry']['times'] ?? 3;

            // Don't retry if we've exceeded max retries
            if ($retries >= $maxRetries) {
                return false;
            }

            // Retry on connection errors
            if ($exception instanceof ConnectException) {
                return true;
            }

            // Retry on server errors (5xx) or rate limiting (429)
            if ($response) {
                $statusCode = $response->getStatusCode();
                if ($statusCode >= 500 || $statusCode === 429) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Calculate the delay before retrying.
     *
     * @return callable
     */
    protected function retryDelay(): callable
    {
        return function (int $retries) {
            $baseDelay = $this->config['retry']['delay'] ?? 100; // milliseconds

            // Exponential backoff: baseDelay * 2^retries
            return $baseDelay * (2 ** $retries);
        };
    }

    /**
     * Create debug middleware for logging requests/responses.
     *
     * @return callable
     */
    protected function debugMiddleware(): callable
    {
        return function (callable $handler) {
            return function (RequestInterface $request, array $options) use ($handler) {
                error_log(sprintf(
                    '[CentralLogs] Request: %s %s',
                    $request->getMethod(),
                    $request->getUri()
                ));

                return $handler($request, $options)->then(
                    function (ResponseInterface $response) {
                        error_log(sprintf(
                            '[CentralLogs] Response: %d',
                            $response->getStatusCode()
                        ));

                        return $response;
                    }
                );
            };
        };
    }

    /**
     * Handle request exceptions and convert to ApiException.
     *
     * @param  RequestException  $e
     * @throws ApiException
     */
    protected function handleRequestException(RequestException $e): void
    {
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode === 401 || $statusCode === 403) {
                throw ApiException::authenticationFailed();
            }

            throw ApiException::invalidResponse($statusCode, $body);
        }

        throw ApiException::connectionFailed($this->apiUrl, $e);
    }
}
