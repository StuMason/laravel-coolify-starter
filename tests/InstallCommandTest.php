<?php

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // Clean up any test artifacts
    $this->testPaths = [
        base_path('nixpacks.toml'),
        app_path('Http/Controllers/HealthCheckController.php'),
        app_path('Providers/HorizonServiceProvider.php'),
        app_path('Providers/TelescopeServiceProvider.php'),
        resource_path('js/pages/health-check.tsx'),
        base_path('docs/standards'),
        base_path('.prettierrc'),
        base_path('.prettierignore'),
        base_path('.editorconfig'),
        base_path('eslint.config.js'),
    ];
});

afterEach(function () {
    foreach ($this->testPaths ?? [] as $path) {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        } elseif (File::exists($path)) {
            File::delete($path);
        }
    }
});

it('registers the coolify:install command', function () {
    $this->artisan('list')
        ->expectsOutputToContain('coolify:install')
        ->assertExitCode(0);
});

it('shows the command signature in help', function () {
    $this->artisan('coolify:install', ['--help' => true])
        ->expectsOutputToContain('--horizon')
        ->expectsOutputToContain('--reverb')
        ->expectsOutputToContain('--telescope')
        ->expectsOutputToContain('--all')
        ->expectsOutputToContain('--force')
        ->assertExitCode(0);
});

it('sanitizes project names correctly', function () {
    // Test via reflection since it's a private method
    $command = new \Stumason\CoolifyStarter\Commands\InstallCommand;
    $reflection = new ReflectionClass($command);
    $method = $reflection->getMethod('sanitizeName');
    $method->setAccessible(true);

    expect($method->invoke($command, 'My Project'))->toBe('my_project');
    expect($method->invoke($command, 'my-project'))->toBe('my_project');
    expect($method->invoke($command, 'MyProject123'))->toBe('myproject123');
    expect($method->invoke($command, 'test@app!'))->toBe('test_app_');
});

it('has all required stub files', function () {
    $stubsPath = dirname(__DIR__).'/stubs';

    expect(File::exists("{$stubsPath}/nixpacks.toml"))->toBeTrue();
    expect(File::exists("{$stubsPath}/HealthCheckController.php.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/health-check.tsx.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/HorizonServiceProvider.php.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/TelescopeServiceProvider.php.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/CLAUDE.md.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/prettierrc.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/prettierignore.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/editorconfig.stub"))->toBeTrue();
    expect(File::exists("{$stubsPath}/eslint.config.js.stub"))->toBeTrue();
    expect(File::isDirectory("{$stubsPath}/docs/standards"))->toBeTrue();
});

it('has valid php syntax in stub files', function () {
    $stubsPath = dirname(__DIR__).'/stubs';
    $phpStubs = [
        "{$stubsPath}/HealthCheckController.php.stub",
        "{$stubsPath}/HorizonServiceProvider.php.stub",
        "{$stubsPath}/TelescopeServiceProvider.php.stub",
    ];

    foreach ($phpStubs as $stub) {
        $result = exec("php -l {$stub} 2>&1", $output, $exitCode);
        expect($exitCode)->toBe(0, "Syntax error in {$stub}: ".implode("\n", $output));
    }
});

it('has valid nixpacks.toml syntax', function () {
    $nixpacksPath = dirname(__DIR__).'/stubs/nixpacks.toml';
    $content = File::get($nixpacksPath);

    // Basic TOML structure checks
    expect($content)->toContain('[phases.setup]');
    expect($content)->toContain('[phases.build]');
    expect($content)->toContain('[start]');
    expect($content)->toContain('[staticAssets]');
});
