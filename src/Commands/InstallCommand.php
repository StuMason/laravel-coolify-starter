<?php

namespace Stumason\CoolifyStarter\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class InstallCommand extends Command
{
    protected $signature = 'coolify:install
                            {--horizon : Install Laravel Horizon}
                            {--reverb : Install Laravel Reverb}
                            {--telescope : Install Laravel Telescope}
                            {--all : Install all optional packages}
                            {--force : Overwrite existing files}';

    protected $description = 'Install Coolify starter kit for production deployments';

    private bool $installHorizon = false;

    private bool $installReverb = false;

    private bool $installTelescope = false;

    public function handle(): int
    {
        info('🚀 Installing Coolify Starter Kit...');

        $this->determinePackagesToInstall();
        $this->installPackages();
        $this->publishStubs();
        $this->updateComposerJson();
        $this->updateBootstrapProviders();
        $this->runMigrations();

        $this->newLine();
        info('✅ Coolify Starter Kit installed successfully!');
        $this->newLine();

        $this->components->bulletList([
            'Run <comment>composer run dev</comment> to start the development server',
            'Visit <comment>/health</comment> to check system status',
            $this->installHorizon ? 'Visit <comment>/horizon</comment> to monitor queues' : null,
            $this->installTelescope ? 'Visit <comment>/telescope</comment> for debugging' : null,
            'Deploy to Coolify using the <comment>nixpacks.toml</comment> configuration',
        ]);

        return self::SUCCESS;
    }

    private function determinePackagesToInstall(): void
    {
        if ($this->option('all')) {
            $this->installHorizon = true;
            $this->installReverb = true;
            $this->installTelescope = true;

            return;
        }

        if ($this->option('horizon') || $this->option('reverb') || $this->option('telescope')) {
            $this->installHorizon = $this->option('horizon');
            $this->installReverb = $this->option('reverb');
            $this->installTelescope = $this->option('telescope');

            return;
        }

        // Interactive mode
        $this->installHorizon = confirm(
            label: 'Install Laravel Horizon? (Redis-based queue management)',
            default: true
        );

        $this->installReverb = confirm(
            label: 'Install Laravel Reverb? (WebSocket server)',
            default: true
        );

        $this->installTelescope = confirm(
            label: 'Install Laravel Telescope? (Debugging & monitoring)',
            default: true
        );
    }

    private function installPackages(): void
    {
        $packages = [];

        if ($this->installHorizon) {
            $packages[] = 'laravel/horizon';
        }

        if ($this->installReverb) {
            $packages[] = 'laravel/reverb';
        }

        if ($this->installTelescope) {
            $packages[] = 'laravel/telescope';
        }

        // Always install Sanctum for API auth
        $packages[] = 'laravel/sanctum';

        if (empty($packages)) {
            return;
        }

        $packageList = implode(' ', $packages);

        spin(
            callback: function () use ($packageList) {
                Process::run("composer require {$packageList} --no-interaction")->throw();
            },
            message: 'Installing Composer packages...'
        );

        // Run package-specific install commands
        if ($this->installHorizon) {
            spin(
                callback: fn () => Process::run('php artisan horizon:install --no-interaction')->throw(),
                message: 'Installing Horizon...'
            );
        }

        if ($this->installReverb) {
            spin(
                callback: fn () => Process::run('php artisan reverb:install --no-interaction')->throw(),
                message: 'Installing Reverb...'
            );
        }

        if ($this->installTelescope) {
            spin(
                callback: fn () => Process::run('php artisan telescope:install --no-interaction')->throw(),
                message: 'Installing Telescope...'
            );
        }
    }

    private function publishStubs(): void
    {
        $stubsPath = dirname(__DIR__, 2).'/stubs';
        $force = $this->option('force');

        // Nixpacks config
        $this->publishFile(
            "{$stubsPath}/nixpacks.toml",
            base_path('nixpacks.toml'),
            'nixpacks.toml',
            $force
        );

        // Health check controller
        $this->publishFile(
            "{$stubsPath}/HealthCheckController.php.stub",
            app_path('Http/Controllers/HealthCheckController.php'),
            'HealthCheckController',
            $force
        );

        // Health check React page
        $this->publishFile(
            "{$stubsPath}/health-check.tsx.stub",
            resource_path('js/pages/health-check.tsx'),
            'health-check.tsx',
            $force
        );

        // Service providers
        if ($this->installHorizon) {
            $this->publishFile(
                "{$stubsPath}/HorizonServiceProvider.php.stub",
                app_path('Providers/HorizonServiceProvider.php'),
                'HorizonServiceProvider',
                $force
            );
        }

        if ($this->installTelescope) {
            $this->publishFile(
                "{$stubsPath}/TelescopeServiceProvider.php.stub",
                app_path('Providers/TelescopeServiceProvider.php'),
                'TelescopeServiceProvider',
                $force
            );
        }

        // Update AppServiceProvider for Telescope fix
        if ($this->installTelescope) {
            $this->updateAppServiceProvider();
        }

        // Add health check route
        $this->addHealthCheckRoute();
    }

    private function publishFile(string $source, string $destination, string $name, bool $force): void
    {
        if (File::exists($destination) && ! $force) {
            warning("Skipping {$name} (already exists). Use --force to overwrite.");

            return;
        }

        File::ensureDirectoryExists(dirname($destination));
        File::copy($source, $destination);
        info("Published {$name}");
    }

    private function updateAppServiceProvider(): void
    {
        $path = app_path('Providers/AppServiceProvider.php');
        $content = File::get($path);

        // Check if already modified
        if (str_contains($content, 'TelescopeServiceProvider')) {
            return;
        }

        // Add use statement
        $content = str_replace(
            'use Illuminate\Support\ServiceProvider;',
            "use Illuminate\Support\ServiceProvider;\nuse Laravel\Telescope\TelescopeServiceProvider as BaseTelescopeServiceProvider;",
            $content
        );

        // Add register logic
        $registerMethod = <<<'PHP'
    public function register(): void
    {
        // Register Telescope only when Redis extension is available
        // This prevents build failures during package:discover
        if (extension_loaded('redis')) {
            $this->app->register(BaseTelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }
    }
PHP;

        $content = preg_replace(
            '/public function register\(\): void\s*\{[^}]*\}/s',
            $registerMethod,
            $content
        );

        File::put($path, $content);
        info('Updated AppServiceProvider for Telescope');
    }

    private function addHealthCheckRoute(): void
    {
        $routesPath = base_path('routes/web.php');
        $content = File::get($routesPath);

        if (str_contains($content, 'HealthCheckController')) {
            return;
        }

        // Add use statement if not present
        if (! str_contains($content, 'use App\Http\Controllers\HealthCheckController;')) {
            $content = preg_replace(
                '/<\?php/',
                "<?php\n\nuse App\Http\Controllers\HealthCheckController;",
                $content,
                1
            );
        }

        // Add route
        $content .= "\n\nRoute::get('/health', HealthCheckController::class)->name('health');\n";

        File::put($routesPath, $content);
        info('Added /health route');
    }

    private function updateComposerJson(): void
    {
        $composerPath = base_path('composer.json');
        $composer = json_decode(File::get($composerPath), true);

        // Update dev script
        $devCommands = ['php artisan serve'];

        if ($this->installHorizon) {
            $devCommands[] = 'php artisan horizon';
        }

        if ($this->installReverb) {
            $devCommands[] = 'php artisan reverb:start';
        }

        $devCommands[] = 'php artisan pail --timeout=0';
        $devCommands[] = 'npm run dev';

        $colors = ['#93c5fd', '#c4b5fd', '#fb7185', '#fdba74', '#4ade80'];
        $names = ['server'];

        if ($this->installHorizon) {
            $names[] = 'horizon';
        }
        if ($this->installReverb) {
            $names[] = 'reverb';
        }
        $names[] = 'logs';
        $names[] = 'vite';

        $colorStr = implode(',', array_slice($colors, 0, count($names)));
        $nameStr = implode(',', $names);
        $cmdStr = implode('" "', $devCommands);

        $composer['scripts']['dev'] = [
            'Composer\\Config::disableProcessTimeout',
            "npx concurrently -c \"{$colorStr}\" \"{$cmdStr}\" --names={$nameStr} --kill-others",
        ];

        // Add dont-discover for Telescope
        if ($this->installTelescope) {
            $composer['extra']['laravel']['dont-discover'] = $composer['extra']['laravel']['dont-discover'] ?? [];
            if (! in_array('laravel/telescope', $composer['extra']['laravel']['dont-discover'])) {
                $composer['extra']['laravel']['dont-discover'][] = 'laravel/telescope';
            }
        }

        File::put($composerPath, json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        info('Updated composer.json scripts');
    }

    private function updateBootstrapProviders(): void
    {
        $providersPath = base_path('bootstrap/providers.php');
        $content = File::get($providersPath);

        $providersToAdd = [];

        if ($this->installHorizon && ! str_contains($content, 'HorizonServiceProvider')) {
            $providersToAdd[] = 'App\\Providers\\HorizonServiceProvider::class';
        }

        // Note: TelescopeServiceProvider is registered manually in AppServiceProvider
        // to handle the Redis extension check, so we don't add it here

        if (empty($providersToAdd)) {
            return;
        }

        // Find the return array and add providers
        foreach ($providersToAdd as $provider) {
            $content = preg_replace(
                '/return \[(.*?)\];/s',
                "return [\$1    {$provider},\n];",
                $content
            );
        }

        File::put($providersPath, $content);
        info('Updated bootstrap/providers.php');
    }

    private function runMigrations(): void
    {
        if (confirm('Run database migrations?', true)) {
            spin(
                callback: fn () => Process::run('php artisan migrate --no-interaction')->throw(),
                message: 'Running migrations...'
            );
        }
    }
}
