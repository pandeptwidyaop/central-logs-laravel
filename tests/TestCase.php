<?php

namespace CentralLogs\Tests;

use CentralLogs\CentralLogsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

/**
 * Base test case for package tests.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Additional setup if needed
    }

    /**
     * Get package providers.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return array
     */
    protected function getPackageProviders($app): array
    {
        return [
            CentralLogsServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param  \Illuminate\Foundation\Application  $app
     * @return void
     */
    protected function defineEnvironment($app): void
    {
        // Setup default configuration for testing
        $app['config']->set('central-logs.api_url', 'http://localhost:8080');
        $app['config']->set('central-logs.api_key', 'test-api-key');
        $app['config']->set('central-logs.mode', 'sync');
        $app['config']->set('central-logs.batch.enabled', false);
        $app['config']->set('central-logs.debug', false);
    }
}
