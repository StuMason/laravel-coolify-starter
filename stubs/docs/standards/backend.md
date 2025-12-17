# Backend Coding Standards

> Laravel 12 patterns and conventions for this project.

---

## Table of Contents

1. [Models](#models)
2. [Migrations](#migrations)
3. [Controllers](#controllers)
4. [Form Requests](#form-requests)
5. [Actions](#actions)
6. [Policies](#policies)
7. [Enums](#enums)
8. [Advanced Patterns](#advanced-patterns)

---

## Models

### Use Modern casts() Method

```php
// ✅ Good - Laravel 12 pattern (already in User.php)
protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'two_factor_confirmed_at' => 'datetime',
    ];
}

// ❌ Bad - Old property-based casts
protected $casts = [
    'email_verified_at' => 'datetime',
];
```

### Follow Laravel 12 Documentation Standards

```php
/**
 * The attributes that are mass assignable.
 *
 * @var list<string>
 */
protected $fillable = [
    'name',
    'email',
    'password',
];
```

### Soft Deletes for User-Managed Entities

```php
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use SoftDeletes;
}
```

### Custom BelongsTo Relations for MorphTo

When you have a `MorphTo` relation, create custom `BelongsTo` relations to simplify usage:

```php
// Example: Payment model with morphTo
public function payable(): MorphTo
{
    return $this->morphTo();
}

// ✅ Add custom BelongsTo for common types
public function lease(): BelongsTo
{
    return $this->belongsTo(Lease::class, 'payable_id')
        ->where('payable_type', Lease::class);
}

// Then you can use the specific relation:
$payment->lease  // Instead of checking $payment->payable
```

---

## Migrations

### Money Stored as Integers (Cents)

```php
// ✅ Good
$table->integer('amount'); // Stores cents

// Model cast
protected function casts(): array
{
    return [
        'amount' => 'integer',
    ];
}

// ❌ Bad - Floating point precision issues
$table->double('amount', 15, 2);
```

### Soft Deletes for User Entities

```php
$table->softDeletes();
```

### Pivot Tables Need ID Column

```php
// ✅ Good - Needed for HasOneOfMany queries
Schema::create('role_user', function (Blueprint $table) {
    $table->id(); // Primary key
    $table->foreignId('role_id')->constrained();
    $table->foreignId('user_id')->constrained();
    $table->timestamps();
});
```

### Foreign Keys Auto-Index

```php
// ✅ Good - Laravel auto-indexes foreign keys
$table->foreignId('company_id')->constrained();

// ❌ Bad - Redundant index
$table->foreignId('company_id')->constrained();
$table->index('company_id'); // Unnecessary!
```

### Morphs Already Creates Composite Index

```php
// ✅ Good
$table->morphs('attachmentable');

// ❌ Bad - Redundant
$table->morphs('attachmentable');
$table->index(['attachmentable_type', 'attachmentable_id']); // Already done!
```

---

## Controllers

### Thin Controller Pattern (Preferred)

Controllers should be thin orchestration layers. Business logic belongs in Action classes.

```php
use App\Actions\User\UpdateUserProfile;
use App\Http\Requests\User\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class ProfileController extends Controller
{
    /**
     * Update the user's profile information.
     */
    public function update(UpdateProfileRequest $request, UpdateUserProfile $action): RedirectResponse
    {
        try {
            $action->handle($request->user(), $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Profile information updated successfully');
    }
}
```

**Controller responsibilities (only these):**

1. ✅ Authorization (`$this->authorize()` or Policy)
2. ✅ Inject the Action via method injection
3. ✅ Pass validated data to the Action
4. ✅ Handle exceptions and return response
5. ✅ Flash messages and redirects

**NOT controller responsibilities:**

- ❌ Data transformation logic
- ❌ Database queries beyond simple lookups
- ❌ Business rules and validation logic
- ❌ Event dispatching (Actions do this)

### Benefits for API Development

This pattern makes adding API endpoints trivial - the same Action works for both:

```php
// Web Controller (Inertia)
class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateUserProfile $action): RedirectResponse
    {
        $action->handle($request->user(), $request->validated());
        return back()->with('success', 'Updated');
    }
}

// API Controller (Mobile App)
class Api\ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request, UpdateUserProfile $action): JsonResponse
    {
        $user = $action->handle($request->user(), $request->validated());
        return response()->json(UserResource::make($user));
    }
}
```

### Standard Inertia Render Pattern

```php
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Dashboard', [
            'user' => $request->user(),
            'stats' => DashboardData::fromUser($request->user()),
        ]);
    }
}
```

### Authorization First

```php
public function update(Request $request, Category $category): RedirectResponse
{
    // 1. Authorize FIRST
    $this->authorize('update', $category);

    // 2. Then delegate to Action
    $action->handle($category, $request->validated());

    return to_route('categories.index');
}
```

### Exception Handling Pattern

```php
use InvalidArgumentException;

public function store(StoreRequest $request, CreateResource $action): RedirectResponse
{
    try {
        $action->handle($request->user(), $request->validated());
    } catch (InvalidArgumentException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('success', 'Resource created successfully');
}
```

---

## Form Requests

### Standard Validation Pattern

```php
namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:' . User::class . ',email,' . $this->user()->id,
            ],
        ];
    }
}
```

**Key Points:**

- ✅ Use array notation for validation rules (not pipe-separated strings)
- ✅ Omit `authorize()` method (handle authorization in controllers)
- ✅ One rule per line for readability

---

## Actions

Actions encapsulate business logic in single-purpose, testable classes. This is the **preferred pattern** for all business logic in this project.

### When to Use Actions

**Always use Actions for:**

- ✅ Any operation that modifies data
- ✅ Operations involving multiple models
- ✅ Logic that needs to be reused (web + API)
- ✅ Operations that dispatch events
- ✅ Anything more complex than a single model update

**Consider inline only for:**

- Simple single-field updates with no side effects
- Read-only operations (use DTOs instead)

### Action Structure

Actions live in `app/Actions/{Domain}/` organized by domain:

```text
app/Actions/
├── User/
│   ├── UpdateUserProfile.php
│   ├── UpdateUserSettings.php
│   └── ToggleProfileVisibility.php
├── Order/
│   ├── CreateDraftOrder.php
│   ├── PublishOrder.php
│   └── UpdateDraftOrderStep.php
└── Invoice/
    ├── CreateInvoice.php
    ├── MarkInvoicePaid.php
    └── VoidInvoice.php
```

### Standard Action Pattern

```php
namespace App\Actions\User;

use App\Models\User;
use InvalidArgumentException;

class UpdateUserProfile
{
    /**
     * Update the user's profile information.
     *
     * @param  array<string, mixed>  $data
     * @throws InvalidArgumentException
     */
    public function handle(User $user, array $data): User
    {
        // Update user fields
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'bio' => $data['bio'] ?? null,
        ]);

        return $user;
    }
}
```

### Action with Events

```php
namespace App\Actions\Order;

use App\Events\OrderApproved;
use App\Events\OrderRejected;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ApproveOrder
{
    public function handle(Order $order): Order
    {
        if ($order->status !== OrderStatus::Pending) {
            throw new InvalidArgumentException('Order is not pending approval');
        }

        return DB::transaction(function () use ($order) {
            // Approve this order
            $order->update([
                'status' => OrderStatus::Approved,
                'approved_at' => now(),
            ]);

            OrderApproved::dispatch($order);

            return $order;
        });
    }
}
```

### Action with Wizard Steps

For multi-step wizards, use a single Action with step-specific logic:

```php
namespace App\Actions\Order;

use App\Models\Order;

class UpdateDraftOrderStep
{
    public function handle(Order $order, int $step, array $data): Order
    {
        $updateData = match ($step) {
            2 => $this->prepareStepTwoData($data),
            3 => $this->prepareStepThreeData($data),
            4 => $this->prepareStepFourData($data),
            default => $data,
        };

        $updateData['wizard_step'] = $step;
        $order->update($updateData);

        return $order;
    }

    private function prepareStepTwoData(array $data): array
    {
        return [
            'shipping_address' => $data['shipping_address'],
            'billing_address' => $data['billing_address'],
        ];
    }
    // ... other step methods
}
```

### Using Actions in Controllers

Inject Actions via method injection:

```php
public function store(StoreRequest $request, CreateResource $action): RedirectResponse
{
    try {
        $action->handle($request->user(), $request->validated());
    } catch (InvalidArgumentException $e) {
        return back()->with('error', $e->getMessage());
    }

    return back()->with('success', 'Created successfully');
}
```

### Testing Actions

Actions should have comprehensive unit tests:

```php
test('it updates user profile', function () {
    $user = User::factory()->create();

    $action = new UpdateUserProfile;
    $result = $action->handle($user, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'bio' => 'Software developer',
    ]);

    expect($result->bio)->toBe('Software developer');
    $user->refresh();
    expect($user->name)->toBe('John Doe');
});
```

---

## Policies

### Use Fortify Authentication

```php
namespace App\Policies;

use App\Models\User;
use App\Models\Category;

class CategoryPolicy
{
    /**
     * Determine if the user can create categories.
     */
    public function create(User $user): bool
    {
        // Use Fortify's auth context
        return $user !== null;
    }

    /**
     * Determine if the user can update the category.
     */
    public function update(User $user, Category $category): bool
    {
        return $user->id === $category->user_id;
    }
}
```

**Register in `AuthServiceProvider`:**

```php
protected $policies = [
    Category::class => CategoryPolicy::class,
];
```

---

## Enums

### Basic Enum Pattern

```php
namespace App\Enums;

enum UserStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case SUSPENDED = 'suspended';
}
```

### Use in Model

```php
protected function casts(): array
{
    return [
        'status' => UserStatus::class,
    ];
}
```

---

## Advanced Patterns

### Data Transformation: DTOs vs API Resources

**When building Inertia.js applications with TypeScript frontends, choose the right pattern for your use case:**

#### Use DTOs (Data Transfer Objects) when:

- ✅ Building Inertia.js pages with explicit, type-safe contracts
- ✅ You want crystal-clear documentation of exactly what data a page receives
- ✅ Working with complex, nested data structures that need predictable shapes
- ✅ You need a single, reusable data structure across multiple controllers/pages
- ✅ TypeScript integration is critical (DTOs map perfectly to TS interfaces)

**Example: UserProfileData DTO**
```php
// app/DataTransferObjects/UserProfileData.php
final readonly class UserProfileData
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public array $roles,
        // ... explicit contract
    ) {}

    public static function fromModel(User $user): self
    {
        return new self(
            id: $user->id,
            name: $user->name,
            email: $user->email,
            roles: $user->roles->pluck('name')->all(),
        );
    }
}

// In Controller
return Inertia::render('Profile/Show', [
    'userData' => UserProfileData::fromModel($user),
]);
```

**Best Practices for DTOs:**
- Keep business logic in models (accessors, computed properties)
- DTOs should assemble data, not transform it
- Use model accessors for URL generation, computed values, etc.
- Reference model properties rather than duplicating transformation logic

```php
// ✅ Good - Model has accessor, DTO references it
// Model: Document.php
public function getFileUrlAttribute(): string
{
    return Storage::disk('s3')->url($this->file_path);
}

// DTO references the accessor
documents: $user->documents->map(fn($doc) => [
    'url' => $doc->file_url,  // Uses accessor
])->all(),

// ❌ Bad - DTO duplicates transformation logic
documents: $user->documents->map(fn($doc) => [
    'url' => Storage::disk('s3')->url($doc->file_path),
])->all(),
```

#### Use API Resources when:

- ✅ Building REST APIs consumed by mobile apps or third parties
- ✅ You need standard Laravel resource collections and pagination
- ✅ You want conditional field inclusion (`$this->when()`, `$this->whenLoaded()`)
- ✅ You're versioning your API endpoints
- ✅ You need JSON:API or similar standardized formats

**Example: API Resource**
```php
// app/Http/Resources/UserResource.php
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
        ];
    }
}

// In API Controller
return UserResource::make($user);
```

**Summary:**
- **Inertia + TypeScript?** → Use DTOs for explicit contracts
- **REST API?** → Use API Resources for flexibility and standards
- **Both exist in your app?** → Perfectly fine to use both patterns where appropriate

---

These patterns don't exist yet but might be valuable:

### 1. HasUid Trait (Recommended)

**Why:** Security, obfuscation, better public-facing identifiers without database overhead.

UIDs are computed on-the-fly from integer IDs using Sqids - no database column needed.

```php
// app/Models/Traits/HasUid.php
trait HasUid
{
    public function getUidAttribute(): string
    {
        if ($this->id) {
            return app(SqidService::class)->encode($this->id);
        }
        return '';
    }

    public function getRouteKeyName(): string
    {
        return 'uid';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= 'id';

        if ($field === 'uid') {
            $field = 'id';
            $value = app(SqidService::class)->decode($value)[0] ?? null;
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    public static function findByUid(string $uid): ?static
    {
        return static::where('id', app(SqidService::class)->decode($uid)[0] ?? null)
            ->firstOrFail();
    }
}

// No migration needed - UIDs are computed from integer ID

// Route binding works automatically
Route::get('/users/{user}', [UserController::class, 'show']);
// Accepts: /users/abc123 → decodes to ID and finds user
```

### 2. EncodedInputParser Trait

**For API-style forms with nested UID relationships:**

```php
// app/Http/Requests/Traits/EncodedInputParser.php
trait EncodedInputParser
{
    public function decodeAndMapPayload(array $fields): void
    {
        foreach ($fields as $field) {
            $value = $this->input($field);
            if ($value) {
                $this->merge([
                    $field => Sqids::decode($value),
                ]);
            }
        }
    }
}

// Usage in FormRequest
public function prepareForValidation(): void
{
    $this->decodeAndMapPayload(['category_uid', 'parent_uid']);
}

public function rules(): array
{
    return [
        'category_uid' => ['required', 'exists:categories,id'],
    ];
}
```

### 3. ChecksModelUsage Trait

**For preventing deletion of models in use:**

```php
// app/Contracts/HasUsageCheck.php
interface HasUsageCheck
{
    public function getUsageRelationships(): array;
    public function isInUse(): bool;
}

// app/Traits/ChecksModelUsage.php
trait ChecksModelUsage
{
    public function isInUse(): bool
    {
        foreach ($this->getUsageRelationships() as $relationship) {
            if ($this->$relationship()->exists()) {
                return true;
            }
        }
        return false;
    }

    abstract public function getUsageRelationships(): array;
}

// Usage in Model
class Category extends Model implements HasUsageCheck
{
    use ChecksModelUsage;

    public function getUsageRelationships(): array
    {
        return ['products', 'subcategories'];
    }
}

// In Controller
public function destroy(Category $category): RedirectResponse
{
    if ($category->isInUse()) {
        return back()->withErrors(['error' => 'Category is in use']);
    }

    $category->delete();
    return to_route('categories.index');
}
```

### 4. Spatie Query Builder

**For filtering, sorting, including relations in index routes:**

```bash
composer require spatie/laravel-query-builder
```

```php
// app/Http/Queries/CategoryIndexQuery.php
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;

class CategoryIndexQuery extends QueryBuilder
{
    public function __construct(Request $request)
    {
        $query = Category::query();

        parent::__construct($query, $request);

        $this->allowedFilters([
            AllowedFilter::exact('type'),
            AllowedFilter::partial('name'),
        ]);

        $this->allowedSorts(['name', 'created_at', 'id']);

        $this->allowedIncludes(['parent', 'children']);

        $this->defaultSort('-id');
    }
}

// Controller
public function index(Request $request, CategoryIndexQuery $query)
{
    return Inertia::render('categories/index', [
        'categories' => $query->paginate(),
    ]);
}

// Frontend can now use:
// /categories?filter[type]=product&sort=name&include=parent
```

---

## References

- [Laravel 12 Documentation](https://laravel.com/docs/12.x)
- [General Standards](./general.md)
- [Frontend Standards](./frontend.md)
- [Testing Standards](./testing.md)
