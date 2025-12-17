# Laravel Coolify Starter

Sets up a fresh Laravel app for Coolify deployment.

## What this does

Runs `php artisan coolify:install` which:

- Installs Horizon, Reverb, Telescope, Sanctum (you pick which ones)
- Installs `stumason/laravel-coolify` for the deployment dashboard
- Publishes `nixpacks.toml` for Coolify builds
- Adds a `/health` endpoint
- Switches `.env` to Postgres and Redis
- Adds a `composer run dev` script that runs everything concurrently
- Publishes coding standards docs to `docs/standards/`

If you have `COOLIFY_URL` and `COOLIFY_TOKEN` configured, it offers to run `coolify:provision` to create the app on Coolify.

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Fresh Laravel install

## Installation

```bash
composer require stumason/laravel-coolify-starter --dev
php artisan coolify:install
```

Answer the prompts or use flags:

```bash
php artisan coolify:install --all                    # Install everything
php artisan coolify:install --horizon --telescope    # Pick specific packages
php artisan coolify:install --no-interaction         # Skip prompts
php artisan coolify:install --force                  # Overwrite existing files
```

## After installation

```bash
createdb your_project_name
php artisan migrate
composer run dev
```

## Files published

```
nixpacks.toml
app/Http/Controllers/HealthCheckController.php
resources/js/pages/health-check.tsx
app/Providers/HorizonServiceProvider.php      (if Horizon)
app/Providers/TelescopeServiceProvider.php    (if Telescope)
docs/standards/
.prettierrc
.prettierignore
.editorconfig
eslint.config.js
```

## .env changes

```
DB_CONNECTION=pgsql
DB_DATABASE=your_project_name
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

## License

MIT
