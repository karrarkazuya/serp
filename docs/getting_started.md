# Getting Started

S-ERP is an Odoo-inspired ERP built with Laravel, Blade, Alpine.js, and Tailwind CSS. The web UI is primarily server-rendered: web controllers fetch data, return Blade views, and handle form submissions. Alpine is used for small interactions such as dropdowns, menus, and dialogs.

## Requirements

- PHP 8.2 or newer
- Composer
- Node.js and npm
- SQLite for local development, or another Laravel-supported database

## Install

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
```

The default setup uses SQLite, so a separate database server is not required for the normal local path.

## Run The App

Start Vite:

```bash
npm run dev
```

Start Laravel:

```bash
php artisan serve
```

Open:

```text
http://localhost:8000
```

Seeded users:

| Email | Password | Role |
| --- | --- | --- |
| `admin@example.com` | `password` | Administrator |
| `user@example.com` | `password` | Basic User |

## Useful Commands

Run migrations and seeders:

```bash
php artisan migrate:fresh --seed
```

Build production assets:

```bash
npm run build
```

Run tests:

```bash
php artisan test
```

List routes:

```bash
php artisan route:list
```

Clear compiled views:

```bash
php artisan view:clear
```

## Project Layout

Important folders:

```text
app/Http/Controllers        Web, API, auth, settings, component controllers
app/Http/Requests           Form request validation and authorization
app/Models                  Eloquent models
app/Policies                Authorization policies
app/Services                Business logic and transactions
config                      App configuration
database/migrations         Schema
database/seeders            Seed data
resources/views             Blade pages and components
resources/js                App JavaScript entrypoint
routes/web.php              Session-authenticated web routes
routes/api.php              Sanctum API routes
docs                        Project documentation
```

## Web UI Pattern

The web UI should follow the Contacts module pattern:

1. `routes/web.php` defines session-authenticated routes.
2. A web controller returns Blade views and handles form posts.
3. Form requests validate and authorize create/update data.
4. Policies protect controller actions.
5. Services handle transactional business logic.
6. Blade views render pages using server-provided data.
7. Alpine handles small UI behaviors, not the primary data layer.

API controllers live separately under `app/Http/Controllers/Api` and return JSON for API clients or future API-driven screens.

## Permissions

Permissions are seeded in `database/seeders/PermissionSeeder.php`.

Common permission keys use this shape:

```text
module.read
module.create
module.write
module.unlink
```

Examples:

```text
contacts.read
contacts.create
contacts.write
contacts.unlink
```

Use policies in web controllers:

```php
$this->authorize('viewAny', Contact::class);
$this->authorize('update', $contact);
```

Use form request `authorize()` for create/update submissions:

```php
return $this->user()->hasPermission('contacts.create');
```

## Company Context

The app supports active company context through `App\Services\Company\CompanyContextService`.

When a model has a `company_id` column, list queries and relation lookups should filter by:

```php
$activeCompanyIds = $companyContext->getActiveCompanyIds();
```

The service validates active company IDs against the authenticated user's allowed companies. Do not trust posted company IDs by themselves; validate them in form requests.

## Dynamic Relation Lookup

Reusable relation selectors are documented in:

```text
docs/components/dynamic_relation_lookup.md
```

Use it for fields that should search another table with pagination instead of loading all options into the page.

## Documentation Index

- `docs/getting_started.md`: local setup and project overview.
- `docs/implement_new_module.md`: how to create a new module following Contacts.
- `docs/components/dynamic_relation_lookup.md`: reusable relation selector.
