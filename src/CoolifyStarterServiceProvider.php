<?php

namespace Stumason\CoolifyStarter;

use Illuminate\Support\ServiceProvider;
use Stumason\CoolifyStarter\Commands\InstallCommand;

class CoolifyStarterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
