<?php

use Stumason\CoolifyStarter\CoolifyStarterServiceProvider;

it('registers the service provider', function () {
    $providers = $this->app->getLoadedProviders();

    expect($providers)->toHaveKey(CoolifyStarterServiceProvider::class);
});

it('registers commands only in console', function () {
    expect($this->app->runningInConsole())->toBeTrue();

    $commands = \Illuminate\Support\Facades\Artisan::all();

    expect($commands)->toHaveKey('coolify:install');
});
