<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
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
