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
                            {name? : Project name for database/redis naming}
                            {--horizon : Install Laravel Horizon}
                            {--reverb : Install Laravel Reverb}
                            {--telescope : Install Laravel Telescope}
                            {--all : Install all optional packages}
                            {--force : Overwrite existing files}';

    protected $description = 'Install Coolify starter kit for production deployments';

    private bool $installHorizon = false;

    private bool $installReverb = false;

    private bool $installTelescope = false;

    private string $projectName = '';

    public function handle(): int
    {
        info('🚀 Installing Coolify Starter Kit...');

        $this->determineProjectName();
        $this->determinePackagesToInstall();
        $this->installPackages();
        $this->publishStubs();
        $this->updateComposerJson();
        $this->updateBootstrapProviders();
        $this->updateEnvFile(); // Update env LAST to avoid triggering Boost's post-update-cmd with wrong DB config
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

    private function determineProjectName(): void
    {
        $name = $this->argument('name');

        if ($name) {
            $this->projectName = $this->sanitizeName($name);

            return;
        }

        // Infer from directory name
        $this->projectName = $this->sanitizeName(basename(base_path()));
    }

    private function sanitizeName(string $name): string
    {
        // Convert to lowercase, replace spaces/hyphens with underscores
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $name));
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
                callback: function () {
                    // reverb:install prompts for app ID even with --no-interaction, so we publish manually
                    Process::run('php artisan vendor:publish --provider="Laravel\Reverb\ReverbServiceProvider" --no-interaction')->throw();
                },
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

        // Coding standards documentation
        $this->publishDirectory(
            "{$stubsPath}/docs/standards",
            base_path('docs/standards'),
            'docs/standards',
            $force
        );

        // Config files
        $this->publishFile(
            "{$stubsPath}/prettierrc.stub",
            base_path('.prettierrc'),
            '.prettierrc',
            $force
        );

        $this->publishFile(
            "{$stubsPath}/prettierignore.stub",
            base_path('.prettierignore'),
            '.prettierignore',
            $force
        );

        $this->publishFile(
            "{$stubsPath}/editorconfig.stub",
            base_path('.editorconfig'),
            '.editorconfig',
            $force
        );

        $this->publishFile(
            "{$stubsPath}/eslint.config.js.stub",
            base_path('eslint.config.js'),
            'eslint.config.js',
            $force
        );

        // Prepend to CLAUDE.md with coding standards references
        $this->prependToClaudeMd("{$stubsPath}/CLAUDE.md.stub");

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

    private function publishDirectory(string $source, string $destination, string $name, bool $force): void
    {
        if (File::isDirectory($destination) && ! $force) {
            warning("Skipping {$name} (already exists). Use --force to overwrite.");

            return;
        }

        File::ensureDirectoryExists($destination);
        File::copyDirectory($source, $destination);
        info("Published {$name}");
    }

    private function updateEnvFile(): void
    {
        $envPath = base_path('.env');

        if (! File::exists($envPath)) {
            warning('.env file not found, skipping environment setup.');

            return;
        }

        $content = File::get($envPath);
        $updates = [];

        // App name
        if (str_contains($content, 'APP_NAME=Laravel')) {
            $appName = ucwords(str_replace('_', ' ', $this->projectName));
            $content = preg_replace('/^APP_NAME=.*/m', "APP_NAME=\"{$appName}\"", $content);
            $updates[] = 'APP_NAME';
        }

        // Database - handle both commented and uncommented lines
        // Check for sqlite connection OR commented DB lines (fresh Laravel install)
        if (str_contains($content, 'DB_CONNECTION=sqlite') || preg_match('/^#\s*DB_HOST=/m', $content)) {
            // Replace or uncomment and set DB settings
            $dbSettings = [
                'DB_CONNECTION' => 'pgsql',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '5432',
                'DB_DATABASE' => $this->projectName,
                'DB_USERNAME' => 'postgres',
                'DB_PASSWORD' => '',
            ];

            foreach ($dbSettings as $key => $value) {
                // Match both commented (# DB_KEY=...) and uncommented (DB_KEY=...) lines
                $content = preg_replace(
                    '/^#?\s*'.preg_quote($key, '/').'=.*/m',
                    "{$key}={$value}",
                    $content
                );
            }
            $updates[] = 'DB_*';
        }

        // Redis/Cache/Queue/Session
        $redisSettings = [
            'SESSION_DRIVER' => 'redis',
            'CACHE_STORE' => 'redis',
            'QUEUE_CONNECTION' => 'redis',
        ];

        foreach ($redisSettings as $key => $value) {
            if (preg_match("/^{$key}=(?!{$value})/m", $content)) {
                $content = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $content);
                $updates[] = $key;
            }
        }

        // Reverb settings
        if ($this->installReverb && ! str_contains($content, 'REVERB_APP_ID')) {
            $reverbId = random_int(100000, 999999);
            $reverbKey = bin2hex(random_bytes(16));
            $reverbSecret = bin2hex(random_bytes(16));

            $content .= "\n# Reverb WebSocket Server\n";
            $content .= "REVERB_APP_ID={$reverbId}\n";
            $content .= "REVERB_APP_KEY={$reverbKey}\n";
            $content .= "REVERB_APP_SECRET={$reverbSecret}\n";
            $content .= "REVERB_HOST=localhost\n";
            $content .= "REVERB_PORT=8080\n";
            $content .= "REVERB_SCHEME=http\n";
            $updates[] = 'REVERB_*';
        }

        if (! empty($updates)) {
            File::put($envPath, $content);
            info('Updated .env: '.implode(', ', $updates));

            // Clear config cache so the new values take effect immediately
            Process::run('php artisan config:clear --no-interaction');
        }
    }

    private function prependToClaudeMd(string $stubPath): void
    {
        $claudeMdPath = base_path('CLAUDE.md');
        $stubContent = File::get($stubPath);

        // Check if already prepended
        if (File::exists($claudeMdPath)) {
            $existingContent = File::get($claudeMdPath);

            if (str_contains($existingContent, 'docs/standards/')) {
                warning('Skipping CLAUDE.md (already contains coding standards reference).');

                return;
            }

            // Prepend stub content to existing file
            File::put($claudeMdPath, $stubContent."\n\n---\n\n".$existingContent);
            info('Updated CLAUDE.md with coding standards references');
        } else {
            // Create new file
            File::put($claudeMdPath, $stubContent);
            info('Published CLAUDE.md');
        }
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
        // Note: We don't run migrations automatically because the .env was just updated
        // and the current PHP process still has the old config cached.
        // The user should run migrations manually after the install completes.
        info('Run <comment>php artisan migrate</comment> to set up the database');
    }
}
