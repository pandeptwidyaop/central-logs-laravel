<?php

namespace CentralLogs\Tests\Feature;

use CentralLogs\Handler\CentralLogsHandler;
use CentralLogs\Tests\TestCase;
use Illuminate\Support\Facades\Log;

/**
 * Boots the package on whichever Laravel version the CI matrix installed and ships a real log
 * entry over HTTP to a local stand-in for the Central Logs API.
 */
class LaravelCompatibilityTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    private static int $port;

    private static string $receivedFile;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$receivedFile = tempnam(sys_get_temp_dir(), 'central-logs-');
        self::$port = self::freePort();

        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:'.self::$port, __DIR__.'/../Fixtures/receiver.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes,
            null,
            ['CENTRAL_LOGS_RECEIVER_FILE' => self::$receivedFile]
        );

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @fsockopen('127.0.0.1', self::$port);
            if ($socket !== false) {
                fclose($socket);

                return;
            }
            usleep(100000);
        }

        self::fail('The stand-in Central Logs server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        @unlink(self::$receivedFile);

        parent::tearDownAfterClass();
    }

    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('central-logs.api_url', 'http://127.0.0.1:'.self::$port);
        $app['config']->set('logging.channels.central-logs', [
            'driver' => 'monolog',
            'handler' => CentralLogsHandler::class,
            'level' => 'debug',
        ]);
    }

    public function test_the_service_provider_registers_the_configuration()
    {
        $this->assertSame('test-api-key', config('central-logs.api_key'));
        $this->assertSame('sync', config('central-logs.mode'));
    }

    public function test_a_log_entry_reaches_the_central_logs_api()
    {
        file_put_contents(self::$receivedFile, '');
        $message = 'compatibility check on Laravel '.$this->app->version();

        Log::channel('central-logs')->error($message, ['context' => 'value']);

        $requests = array_values(array_filter(array_map(
            fn ($line) => json_decode($line, true),
            explode(PHP_EOL, (string) file_get_contents(self::$receivedFile))
        )));

        $this->assertNotEmpty($requests, 'No request reached the stand-in Central Logs API.');
        $this->assertSame('POST', $requests[0]['method']);
        $this->assertSame('test-api-key', $requests[0]['api_key']);
        $this->assertStringContainsString($message, json_encode($requests[0]['body']));
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }
}
