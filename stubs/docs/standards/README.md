# Coding Standards

> **Stack:** Laravel 12, Inertia.js v2, React 19, shadcn/ui, Pest v4, Tailwind CSS v4, Horizon
>
> These standards are based on real patterns in this codebase and tribal knowledge from previous projects. They complement the Laravel Boost guidelines in `CLAUDE.md`.

---

## Quick Navigation

- **[General Standards](./general.md)** - Core principles, exception handling, type hints
- **[Backend Standards](./backend.md)** - Laravel patterns: models, migrations, controllers, policies
- **[Frontend Standards](./frontend.md)** - Inertia + React + Tailwind patterns
- **[Testing Standards](./testing.md)** - Pest v4 testing patterns

---

## Quick Reference

### Common Commands

```bash
# Format code
vendor/bin/pint

# Run tests
php artisan test

# Run tests with filter
php artisan test --filter=ProfileTest

# Build frontend
npm run build

# Dev server
npm run dev

# Fresh database with all dev data
php artisan migrate:fresh --seed

# Seed services only (safe for production)
php artisan db:seed --class=ServiceSeeder
```

### Key Principles

1. **Thin controllers, fat Actions** - Controllers orchestrate, Actions contain business logic
2. **Always import classes** - Never use inline FQCN
3. **Never use env() outside config files** - Use `config()` helper
4. **Type hints everywhere** - All parameters and return types
5. **Money as integers** - Store pence/cents, not pounds/dollars
6. **Soft deletes for user entities** - Use `SoftDeletes` trait
7. **Lowercase import paths** - Critical for production builds
8. **Authorization first** - Check permissions before mutations
9. **Use createQuietly in tests** - Avoid event side effects
10. **Use Card component** - Never raw divs with card styles
11. **Icons from lucide-react** - Never use emojis in UI

---

## Quick Checklist Before Committing

- [ ] Business logic in Actions, not controllers
- [ ] All classes imported (no inline FQCN)
- [ ] Unused imports removed
- [ ] Type hints on all method parameters and returns
- [ ] Docblocks for public methods
- [ ] Money stored as integers (cents/pence)
- [ ] Soft deletes on user-managed models
- [ ] Authorization checks before mutations
- [ ] Array notation for validation rules
- [ ] Lowercase import paths in frontend
- [ ] Use `<Card>` component, not raw divs with card styles
- [ ] Use `lucide-react` icons, never emojis
- [ ] Tests for Actions (happy path + error cases)
- [ ] Tests use `createQuietly()` and `Event::fake()`
- [ ] Run `vendor/bin/pint` to format code
- [ ] Run `npm run format && npm run lint` for frontend
- [ ] Run `php artisan test` to verify tests pass

---

## External References

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [Inertia.js v2 Documentation](https://inertiajs.com)
- [React 19 Documentation](https://react.dev)
- [Pest Documentation](https://pestphp.com)
- [Tailwind CSS v4 Documentation](https://tailwindcss.com)
- [shadcn/ui Documentation](https://ui.shadcn.com)
- [Wayfinder Documentation](https://github.com/laravel/wayfinder)
