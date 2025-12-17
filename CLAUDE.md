# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package (`stumason/laravel-coolify-starter`) that transforms a fresh Laravel app into a production-ready Coolify deployment. It provides an artisan command that installs and configures Horizon, Reverb, Telescope, Sanctum, and publishes coding standards documentation.

**Stack:** PHP 8.2+, Laravel 11/12

## Common Commands

```bash
# Run the main install command (interactive)
php artisan coolify:install

# Install with all optional packages
php artisan coolify:install --all

# Install specific packages
php artisan coolify:install --horizon --telescope

# Overwrite existing files
php artisan coolify:install --force

# Custom project name
php artisan coolify:install my-project-name
```

## Architecture

### Core Components

- [src/CoolifyStarterServiceProvider.php](src/CoolifyStarterServiceProvider.php) - Registers the `coolify:install` command
- [src/Commands/InstallCommand.php](src/Commands/InstallCommand.php) - Main install logic

### Stubs Directory

All files published to target Laravel projects live in `stubs/`:

- `nixpacks.toml` - Coolify/Nixpacks build configuration
- `HealthCheckController.php.stub` - Health check endpoint controller
- `health-check.tsx.stub` - React health check page
- `HorizonServiceProvider.php.stub` - Horizon auth gate configuration
- `TelescopeServiceProvider.php.stub` - Telescope auth gate configuration
- `CLAUDE.md.stub` - Prepended to target project's CLAUDE.md
- `docs/standards/` - Coding standards documentation (Laravel 12 + Inertia + React + Pest v4)
- Config stubs: `.prettierrc`, `.prettierignore`, `.editorconfig`, `eslint.config.js`

### Install Command Flow

1. Determine project name (from argument or directory name)
2. Prompt for optional packages (Horizon, Reverb, Telescope)
3. Install selected Composer packages + Sanctum
4. Publish stub files to appropriate locations
5. Update `composer.json` with `dev` script using concurrently
6. Update `bootstrap/providers.php` for Horizon
7. Update `.env` (PostgreSQL, Redis for session/cache/queue, Reverb credentials)

### Key Implementation Details

- Telescope is registered conditionally in AppServiceProvider (checks for Redis extension to prevent build failures)
- Reverb is installed via `vendor:publish` instead of `reverb:install` to avoid interactive prompts
- Environment updates happen last to avoid triggering Laravel's post-update hooks with incorrect DB config
- Migrations are not run automatically; user must run manually after install

## Testing the Package

To test changes, install the package in a fresh Laravel project:

```bash
# In a fresh Laravel project
composer require stumason/laravel-coolify-starter:dev-main --dev
php artisan coolify:install
```
