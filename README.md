# Laravel Coolify Starter

A Laravel package that configures a fresh Laravel app for production deployment on Coolify with Nixpacks.

Opinionated. Built for Claude Code and VSCode. Includes comprehensive coding standards documentation that gets published to your project for AI-assisted development.

## What it does

Runs a single artisan command that:

- Installs Horizon, Reverb, Telescope, and Sanctum (optional, via prompts or flags)
- Publishes a `nixpacks.toml` configured for Coolify deployments
- Adds a `/health` endpoint with controller and React page
- Updates `.env` for PostgreSQL and Redis (session, cache, queue)
- Configures `composer.json` with a `dev` script using concurrently
- Publishes service providers with sensible auth gates for Horizon/Telescope
- Drops coding standards docs into `docs/standards/`
- Adds config files: `.prettierrc`, `.editorconfig`, `eslint.config.js`

## Requirements

- PHP 8.2+
- Laravel 12
- Fresh Laravel install (works best on new projects)

## Installation

```bash
composer require stumason/laravel-coolify-starter --dev
```

## Usage

```bash
php artisan coolify:install
```

Interactive prompts will ask which optional packages to install.

### Flags

```bash
# Install everything
php artisan coolify:install --all

# Pick specific packages
php artisan coolify:install --horizon --telescope

# Skip prompts, install nothing optional
php artisan coolify:install --no-interaction

# Overwrite existing files
php artisan coolify:install --force

# Custom project name (defaults to directory name)
php artisan coolify:install my-app
```

### After installation

```bash
# Create the database (uses project name)
createdb your_project_name

# Run migrations
php artisan migrate

# Start dev server
composer run dev
```

## What gets installed

### Always installed

- `laravel/sanctum` - API authentication

### Optional (via prompts)

- `laravel/horizon` - Redis queue dashboard
- `laravel/reverb` - WebSocket server
- `laravel/telescope` - Debug assistant

## Files published

```text
nixpacks.toml                           # Coolify/Nixpacks build config
app/Http/Controllers/HealthCheckController.php
resources/js/pages/health-check.tsx
app/Providers/HorizonServiceProvider.php    # if Horizon installed
app/Providers/TelescopeServiceProvider.php  # if Telescope installed
docs/standards/                             # Coding standards
.prettierrc
.prettierignore
.editorconfig
eslint.config.js
CLAUDE.md                                   # Prepended with standards refs
```

## Environment changes

The installer updates `.env`:

```bash
DB_CONNECTION=pgsql
DB_DATABASE=your_project_name
SESSION_DRIVER=redis
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

If Reverb is installed, it also generates `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`.

## License

MIT
