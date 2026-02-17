<?php

namespace YassineDabbous\JsonableRequest\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use YassineDabbous\JsonableRequest\JsonableRequestServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            JsonableRequestServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // You can configure environment variables here if needed
        // For example, app('config')->set('database.default', 'sqlite');
    }
}