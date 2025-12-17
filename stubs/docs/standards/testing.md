# Testing Standards

> Pest v4 testing patterns for this project.

---

## Table of Contents

1. [Pest Patterns](#pest-patterns)
2. [Test Organization](#test-organization)
3. [Best Practices](#best-practices)
4. [Testing Actions](#testing-actions)
5. [Pest v4 Browser Testing](#pest-v4-browser-testing)

---

## Pest Patterns

### AAA Structure (Arrange, Act, Assert)

```php
it('updates user profile', function () {
    // Arrange
    $user = User::factory()->create([
        'name' => 'Old Name',
    ]);

    // Act
    $response = $this->actingAs($user)->put(route('profile.update'), [
        'name' => 'New Name',
        'email' => $user->email,
    ]);

    // Assert
    $response->assertRedirect(route('profile.edit'));
    expect($user->fresh()->name)->toBe('New Name');
});
```

### Use createQuietly in Tests

```php
// ✅ Good - Suppresses model events
$user = User::factory()->createQuietly();

// ❌ Bad - Triggers events
$user = User::factory()->create();
```

**Why:** Avoids side effects from event listeners during tests.

### Fake Events Not Being Tested

```php
use Illuminate\Support\Facades\Event;

it('creates a category', function () {
    // Arrange
    Event::fake([CategoryCreated::class]);
    $user = User::factory()->createQuietly();

    // Act
    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => 'Test Category',
    ]);

    // Assert
    $response->assertRedirect();
    expect(Category::count())->toBe(1);
});
```

---

## Test Organization

### Directory Structure

```text
tests/Feature/
├── Actions/                              # Action unit tests
│   ├── Cleaner/
│   │   ├── UpdateCleanerInfoTest.php
│   │   └── ToggleProfileVisibilityTest.php
│   ├── Job/
│   │   ├── CreateDraftJobTest.php
│   │   └── PublishJobPostingTest.php
│   └── Quote/
│       ├── AcceptJobQuoteTest.php
│       └── SubmitJobQuoteTest.php
├── DataTransferObjects/                  # DTO tests
│   ├── CleanerJobDataTest.php
│   └── ClientQuoteDataTest.php
├── Models/                               # Model scope/behavior tests
│   └── JobPostingScopesTest.php
├── Client/                               # Controller integration tests
│   └── PostJobWizardTest.php
└── Auth/
    ├── LoginTest.php
    └── RegistrationTest.php
```

### Split Tests by Controller Method (When Complex)

```text
tests/Feature/
├── Auth/
│   ├── LoginTest.php
│   └── RegistrationTest.php
├── Settings/
│   ├── ProfileControllerTest.php        # Simple - all methods in one file
│   └── TwoFactorControllerTest.php
└── Categories/
    ├── CategoryControllerIndexTest.php   # Complex - split by method
    ├── CategoryControllerStoreTest.php
    ├── CategoryControllerUpdateTest.php
    └── CategoryControllerDestroyTest.php
```

**Naming:** `{ControllerName}{MethodName}Test.php` when split.

### Test Method Order

```php
class CategoryControllerStoreTest extends TestCase
{
    // 1. Authorization tests
    it('requires authentication', function () { /* ... */ });

    // 2. Validation tests
    it('validates required fields', function () { /* ... */ });

    // 3. Happy path tests
    it('creates a category successfully', function () { /* ... */ });
}
```

---

## Best Practices

### Use Specific Assertions

```php
// ✅ Good - Use specific assertions
$response->assertSuccessful();
$response->assertForbidden();
$response->assertNotFound();

// ❌ Bad - Generic status assertions
$response->assertStatus(200);
$response->assertStatus(403);
$response->assertStatus(404);
```

### Test Authorization

```php
it('prevents unauthorized users from updating categories', function () {
    $user = User::factory()->createQuietly();
    $otherUser = User::factory()->createQuietly();
    $category = Category::factory()->create(['user_id' => $otherUser->id]);

    $response = $this->actingAs($user)->put(route('categories.update', $category), [
        'name' => 'Updated Name',
    ]);

    $response->assertForbidden();
});
```

### Test Validation Rules

```php
it('validates required fields', function () {
    $user = User::factory()->createQuietly();

    $response = $this->actingAs($user)->post(route('categories.store'), [
        'name' => '',
    ]);

    $response->assertSessionHasErrors(['name']);
});
```

### Use Datasets for Repeated Tests

```php
it('validates email format', function (string $email) {
    $response = $this->post(route('register'), [
        'email' => $email,
        'name' => 'Test User',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ]);

    $response->assertSessionHasErrors(['email']);
})->with([
    'invalid-format-1' => 'not-an-email',
    'invalid-format-2' => 'missing@domain',
    'invalid-format-3' => '@nodomain.com',
]);
```

---

## Testing Actions

Actions are the core of business logic and should have comprehensive tests.

### Action Test Structure

```php
<?php

use App\Actions\Cleaner\UpdateCleanerInfo;
use App\Enums\ExperienceBand;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it updates user personal info', function () {
    $user = User::factory()->cleaner()->create();

    $action = new UpdateCleanerInfo;
    $action->handle($user, [
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '07123456789',
        'dob' => '1990-05-15',
        'bio' => 'Experienced cleaner',
        'experience_band' => ExperienceBand::TenPlus->value,
    ]);

    $user->refresh();
    expect($user->first_name)->toBe('John');
    expect($user->last_name)->toBe('Doe');
});

test('it throws exception when profile not found', function () {
    $user = User::factory()->create(); // No cleaner profile

    $action = new UpdateCleanerInfo;

    expect(fn () => $action->handle($user, [
        'first_name' => 'John',
        // ...
    ]))->toThrow(InvalidArgumentException::class, 'Profile not found');
});
```

### What to Test in Actions

1. **Happy path** - Normal operation with valid data
2. **Edge cases** - Null values, empty arrays, boundary conditions
3. **Error cases** - Invalid data, missing relationships
4. **Side effects** - Events dispatched, related models updated
5. **Return values** - Correct model/data returned

### Testing Events in Actions

```php
use App\Events\QuoteAccepted;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Event::fake([QuoteAccepted::class]);
});

test('it dispatches QuoteAccepted event', function () {
    $job = JobPosting::factory()->open()->create();
    $quote = Quote::factory()->create(['job_posting_id' => $job->id]);

    $action = new AcceptJobQuote;
    $action->handle($job, $quote);

    Event::assertDispatched(QuoteAccepted::class, function ($event) use ($quote) {
        return $event->quote->id === $quote->id;
    });
});
```

### Testing Database Transactions

```php
test('it uses database transaction', function () {
    $job = JobPosting::factory()->open()->create();

    // Force an error mid-transaction
    Quote::creating(fn () => throw new \Exception('Forced error'));

    expect(fn () => $action->handle($job, $data))
        ->toThrow(\Exception::class);

    // Verify nothing was persisted
    expect(Quote::count())->toBe(0);
});
```

---

## Pest v4 Browser Testing

Pest v4 includes powerful browser testing capabilities using Playwright.

### Setup

Browser tests require Playwright to be installed:

```bash
composer require pestphp/pest-plugin-browser:^4.0 --dev
npm install -D playwright@latest
npx playwright install --with-deps chromium
```

### Smoke Tests (Recommended for Refactors)

We have smoke tests in `tests/Browser/SmokeTest.php` that verify pages load without JavaScript errors. Run these before and after major refactors:

```bash
php artisan test tests/Browser/SmokeTest.php
```

```php
// tests/Browser/SmokeTest.php
it('loads public pages without JavaScript errors', function () {
    $pages = visit([
        '/',
        '/how-it-works',
    ]);

    $pages->assertNoJavascriptErrors();
});

it('loads authenticated pages without JavaScript errors', function () {
    $user = User::factory()->createQuietly();
    $this->actingAs($user);

    $pages = visit([
        '/dashboard',
        '/cleaners',
    ]);

    $pages->assertNoJavascriptErrors();
});
```

### Example: Interactive Browser Test

```php
it('allows users to request a password reset', function () {
    Notification::fake();

    $user = User::factory()->create();

    $page = visit('/login');

    $page->click('Forgot Password?')
        ->waitForUrl('/forgot-password')
        ->fill('email', $user->email)
        ->click('Send Reset Link')
        ->assertSee('We have emailed your password reset link!');

    Notification::assertSent(ResetPassword::class, function ($notification) use ($user) {
        return $notification->user->id === $user->id;
    });
});
```

### Common Browser Test Patterns

```php
// Visit multiple pages at once (smoke testing)
$pages = visit(['/', '/about', '/contact']);
$pages->assertNoJavascriptErrors();

// Wait for navigation
$page->click('Submit')
    ->waitForUrl('/success')
    ->assertSee('Thank you');

// Wait for elements
$page->click('Load More')
    ->wait(500)
    ->assertSee('More content');

// Take screenshots (for debugging)
$page->screenshot('path/to/screenshot.png');
```

---

## Running Tests

### Run All Tests

```bash
php artisan test
```

### Run Tests in a File

```bash
php artisan test tests/Feature/ExampleTest.php
```

### Run Tests with Filter

```bash
php artisan test --filter=testName
```

### Run Tests in Parallel

```bash
php artisan test --parallel
```

---

## References

- [Pest Documentation](https://pestphp.com)
- [Pest v4 Browser Testing](https://pestphp.com/docs/browser-testing)
- [Laravel Testing Documentation](https://laravel.com/docs/12.x/testing)
- [General Standards](./general.md)
- [Backend Standards](./backend.md)
- [Frontend Standards](./frontend.md)
