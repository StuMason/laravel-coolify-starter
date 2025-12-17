# General Coding Standards

> **Stack:** Laravel 12, Inertia.js v2, React 19, shadcn/ui, Pest v4, Tailwind CSS v4, Horizon
>
> These standards complement the Laravel Boost guidelines in `CLAUDE.md`.

---

## Table of Contents

1. [Core Principles](#core-principles)
2. [Common Patterns](#common-patterns)
3. [Quick Checklist](#quick-checklist)

---

## Core Principles

### Always Import Classes

```php
// ✅ Good
use Illuminate\Support\Facades\Auth;
use App\Models\User;

Auth::logout();
$user = User::find(1);

// ❌ Bad
\Illuminate\Support\Facades\Auth::logout();
$user = \App\Models\User::find(1);
```

### Never Use env() Outside Config Files

**Critical for Production:** In production, `env()` returns `null` after `config:cache`, which can cause security issues.

```php
// ✅ Good - Use config() helper
$adminEmails = config('admin.emails', []);

// ❌ Bad - Direct env() usage in application code
$adminEmails = explode(',', env('ADMIN_EMAILS', ''));
```

**Correct pattern:**

1. Define in config file (e.g., `config/admin.php`):
```php
return [
    'emails' => array_map('trim', array_filter(explode(',', env('ADMIN_EMAILS', '')))),
];
```

2. Use in application code:
```php
$adminEmails = config('admin.emails', []);
```

### Avoid Class Variables - Prefer Functional Code

```php
// ✅ Good - Explicit dependencies
public function destroy(Request $request): RedirectResponse
{
    $user = $request->user();
    Auth::logout();
    $user->delete();
}

// ❌ Bad - Class variables hide dependencies
class ProfileController extends Controller
{
    private User $user;

    public function __construct()
    {
        $this->user = auth()->user();
    }
}
```

### Remove Unused Imports and Variables

- Run Pint before committing: `vendor/bin/pint`
- Use IDE tools to auto-remove unused imports

---

## Common Patterns

### Exception Handling

Use `$throwable` not `$e` for consistency:

```php
use Throwable;

try {
    // Complex operation
} catch (Throwable $throwable) {
    throw ValidationException::withMessages([
        'error' => 'Operation failed: ' . $throwable->getMessage(),
    ]);
}
```

### Type Hints Everywhere

Always use explicit return type declarations for methods and functions:

```php
// ✅ Good
protected function isAccessible(User $user, ?string $path = null): bool
{
    // ...
}

// ❌ Bad - Missing return type
protected function isAccessible(User $user, ?string $path = null)
{
    // ...
}
```

---

## Quick Checklist Before Committing

- [ ] All classes imported (no inline FQCN)
- [ ] Unused imports removed
- [ ] Type hints on all method parameters and returns
- [ ] Docblocks for public methods
- [ ] Run `vendor/bin/pint` to format code
- [ ] Run `php artisan test` to verify tests pass

---

## References

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Backend Standards](./backend.md)
- [Frontend Standards](./frontend.md)
- [Testing Standards](./testing.md)
