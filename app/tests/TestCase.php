<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function defineEnvironment($app): void
    {
        /** @var Application $app */
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! is_array(config('bossku_oauth')) || config('bossku_oauth') === []) {
            $file = dirname(__DIR__).'/config/bossku_oauth.php';
            if (is_readable($file)) {
                config(['bossku_oauth' => require $file]);
            }
        }
    }
}
