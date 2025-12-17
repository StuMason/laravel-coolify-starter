# Frontend Coding Standards

> Inertia.js v2 + React 19 + Tailwind CSS v4 patterns for this project.

---

## Table of Contents

1. [Inertia + React](#inertia--react)
2. [UI Components](#ui-components)
3. [Form Handling](#form-handling)
4. [Component Structure](#component-structure)
5. [Wayfinder Routing](#wayfinder-routing)
6. [Role-Aware Navigation](#role-aware-navigation)
7. [Money & Currency](#money--currency)

---

## Inertia + React

### Import Path Casing (Critical for Production Builds)

**ALWAYS use lowercase for all import paths.** Production builds on Linux are case-sensitive and will fail if casing is incorrect.

```tsx
// ✅ CORRECT - lowercase paths
import { SystemStatus } from '@/components/system-status';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import { SharedData } from '@/types';

// ❌ WRONG - uppercase will break production builds
import { SystemStatus } from '@/Components/system-status';
import { Button } from '@/Components/ui/button';
import AppLayout from '@/Layouts/app-layout';
```

**Why this matters:**

- **Local (macOS/Windows)**: Case-insensitive filesystems, so `Components` and `components` work
- **Production (Linux/Coolify)**: Case-sensitive filesystem, so builds fail with wrong casing

**Standard path aliases:**

- `@/components/` - All React components
- `@/layouts/` - Layout components
- `@/pages/` - Inertia page components
- `@/types/` - TypeScript type definitions
- `@/routes/` - Wayfinder generated routes
- `@/actions/` - Wayfinder generated actions

---

### Page Component Structure

```tsx
import { Head } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';

export default function Dashboard() {
    return (
        <AppLayout>
            <Head title="Dashboard" />

            <div className="space-y-6">
                {/* Page content */}
            </div>
        </AppLayout>
    );
}
```

### Using Shared Data

```tsx
import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

export default function Profile() {
    const { auth } = usePage<SharedData>().props;

    return <div>Welcome, {auth.user.name}!</div>;
}
```

---

## Form Handling

### Inertia Form Component (Recommended)

```tsx
import { Form } from '@inertiajs/react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/input-error';

export default function ProfileForm() {
    const { auth } = usePage<SharedData>().props;

    return (
        <Form
            {...ProfileController.update.form()}
            options={{
                preserveScroll: true,
            }}
            className="space-y-6"
        >
            {({ processing, recentlySuccessful, errors }) => (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="name">Name</Label>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={auth.user.name}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <Button disabled={processing}>
                        {processing ? 'Saving...' : 'Save'}
                    </Button>

                    {recentlySuccessful && (
                        <p className="text-sm text-green-600">Saved!</p>
                    )}
                </>
            )}
        </Form>
    );
}
```

**Key Points:**

- ✅ Use Wayfinder's generated `ProfileController.update.form()` helper
- ✅ Destructure `processing`, `recentlySuccessful`, `errors` from render prop
- ✅ Use `defaultValue` for inputs (not `value`)
- ✅ Use `preserveScroll` to keep scroll position on errors

### Alternative: useForm Hook

```tsx
import { useForm } from '@inertiajs/react';
import { update } from '@/routes/profile';

export default function ProfileForm() {
    const { data, setData, put, processing, errors } = useForm({
        name: auth.user.name,
        email: auth.user.email,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put(update().url);
    };

    return (
        <form onSubmit={submit}>
            <Input
                value={data.name}
                onChange={(e) => setData('name', e.target.value)}
            />
            <Button disabled={processing}>Save</Button>
        </form>
    );
}
```

**When to use:**

- Use `<Form>` component for most cases (cleaner, less boilerplate)
- Use `useForm` hook when you need programmatic control

---

## Component Structure

### Organize by Feature, Not Type

```
resources/js/
├── components/
│   ├── app-header.tsx           # App-specific components
│   ├── app-sidebar.tsx
│   ├── delete-user.tsx          # Feature components
│   ├── two-factor-setup-modal.tsx
│   └── ui/                      # shadcn components
│       ├── button.tsx
│       ├── input.tsx
│       └── dialog.tsx
├── pages/
│   ├── auth/
│   │   ├── login.tsx
│   │   └── register.tsx
│   ├── settings/
│   │   ├── profile.tsx
│   │   └── password.tsx
│   └── dashboard.tsx
└── layouts/
    ├── app-layout.tsx
    ├── auth-layout.tsx
    └── settings/
        └── layout.tsx
```

### Component Naming

- ✅ Use kebab-case for component files: `app-header.tsx`, `edit-profile-dialog.tsx`
- ✅ Use PascalCase for the component function: `export default function AppHeader()`
- ✅ Use kebab-case for Inertia page paths: `'settings/profile'`, `'Admin/Dashboard'`
- ✅ Page components in `pages/` can use PascalCase folders for namespacing: `pages/Admin/Dashboard.tsx`

---

## Wayfinder Routing

### Type-Safe Route Helpers

```tsx
import { edit as profileEdit } from '@/routes/profile';
import { Link } from '@inertiajs/react';

// ✅ Good - Type-safe routes
<Link href={profileEdit().url}>Edit Profile</Link>

// ❌ Bad - Hardcoded routes
<Link href="/settings/profile">Edit Profile</Link>
```

### Breadcrumbs Pattern

```tsx
import { type BreadcrumbItem } from '@/types';
import { edit } from '@/routes/profile';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit().url,
    },
];

export default function Profile() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {/* Content */}
        </AppLayout>
    );
}
```

---

## UI Components

### Card Component

Use the `Card` component from `@/components/ui/card` for all card-style containers:

```tsx
import { Card } from '@/components/ui/card';

// Standard card (rounded-2xl, border, white bg, p-6)
<Card>
    <h3 className="text-xl font-semibold">Title</h3>
    <p className="mt-2 text-gray-600">Content</p>
</Card>

// Stats cards use smaller radius
<Card className="rounded-xl">
    <div className="flex items-center gap-3">
        <div className="flex size-12 items-center justify-center rounded-full bg-blue-100">
            <Icon className="size-6 text-blue-600" />
        </div>
        <div>
            <p className="text-sm text-gray-600">Label</p>
            <p className="text-2xl font-bold">Value</p>
        </div>
    </div>
</Card>
```

**Do NOT use raw divs for cards:**

```tsx
// ❌ Bad - raw div with card styles
<div className="rounded-2xl border border-gray-200 bg-white p-6">

// ✅ Good - Card component
<Card>
```

### Icons

Always use `lucide-react` for icons. Never use emojis in UI.

```tsx
import { Search, Plus, Settings } from 'lucide-react';

<Button>
    <Search className="size-4" />
    Search
</Button>
```

### Dialog Pattern

Use shadcn Dialog for modals with forms:

```tsx
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

export function EditProfileDialog({ profile, onSuccess }) {
    const [open, setOpen] = useState(false);

    const handleSuccess = () => {
        setOpen(false);
        onSuccess?.();
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" size="sm">
                    <Pencil className="size-4" />
                    Edit
                </Button>
            </DialogTrigger>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Edit Profile</DialogTitle>
                </DialogHeader>
                <ProfileForm profile={profile} onSuccess={handleSuccess} />
            </DialogContent>
        </Dialog>
    );
}
```

---

## Role-Aware Navigation

### Accessing User Roles

User role information is available via SharedData:

```tsx
import { usePage } from '@inertiajs/react';
import { type SharedData } from '@/types';

export function MyComponent() {
    const { auth } = usePage<SharedData>().props;

    // Available role checks
    const isAdmin = auth.user?.is_admin;
    const isModerator = auth.user?.is_moderator;
    const roles = auth.user?.roles; // ['admin', 'moderator', etc.]

    return (
        <div>
            {isAdmin && <AdminDashboard />}
            {isModerator && <ModeratorDashboard />}
        </div>
    );
}
```

### Role-Specific Routes

Use role-specific route helpers for navigation:

```tsx
import admin from '@/routes/admin';
import user from '@/routes/user';

// Determine dashboard URL based on role
const dashboardUrl = isAdmin
    ? admin.dashboard().url
    : user.dashboard().url;

<Link href={dashboardUrl}>Dashboard</Link>
```

---

## Money & Currency

### Display Money Values

Money is stored as integers (pence/cents) in the database. Always divide by 100 for display:

```tsx
// ✅ Good - Format money from integer
<p>£{(stats.totalSpent / 100).toFixed(2)}</p>

// For rates (stored as pence per hour)
<p>£{(profile.hourly_rate / 100).toFixed(2)}/hr</p>
```

### Money Input Fields

When accepting money input, convert to integers before sending to backend:

```tsx
// In form submission
const rateInPence = Math.round(parseFloat(formData.rate) * 100);
```

---

## References

- [Inertia.js v2 Documentation](https://inertiajs.com)
- [React 19 Documentation](https://react.dev)
- [Tailwind CSS v4 Documentation](https://tailwindcss.com)
- [shadcn/ui Documentation](https://ui.shadcn.com)
- [Wayfinder Documentation](https://github.com/laravel/wayfinder)
- [General Standards](./general.md)
- [Backend Standards](./backend.md)
- [Testing Standards](./testing.md)
