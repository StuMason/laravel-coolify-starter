<?php

namespace Stumason\CoolifyStarter\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Stumason\CoolifyStarter\CoolifyStarterServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CoolifyStarterServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
    }
}
