# S-ERP

An Odoo-inspired ERP system built with Laravel, Blade, Alpine.js, and Tailwind CSS.

---

## Quick Start

```bash
git clone <repo-url> serp
cd serp

composer install
npm install

cp .env.example .env
php artisan key:generate

# SQLite is used by default — no DB setup needed
php artisan migrate --seed

npm run dev
php artisan serve
```

Visit `http://localhost:8000` and log in:

| Email               | Password | Role          |
|---------------------|----------|---------------|
| admin@example.com   | password | Administrator |
| user@example.com    | password | Basic User    |

---

## Stack

| Layer       | Technology                      |
|-------------|----------------------------------|
| Backend     | Laravel 13 (PHP 8.2+)           |
| Auth        | Laravel Session Auth + Sanctum  |
| Frontend    | Blade + Alpine.js + Tailwind CSS |
| Database    | SQLite (local) / MySQL / Postgres|
| API Auth    | Laravel Sanctum (token-based)   |

---

## Architecture

The application uses server-rendered Blade pages for the web UI, plus separate JSON API endpoints for API clients:

1. Web routes and web controllers query data, return Blade views, and handle form submissions.
2. Blade receives server-side variables such as `$contacts` and renders HTML.
3. Alpine.js is used for lightweight UI interactions, not as the primary data layer.
4. API controllers return JSON for external clients, integrations, or future API-driven screens.
5. Services contain transactional business logic and chatter logging.

**Web controllers** use Gate policies and return Blade views:

```php
public function read(Request $request)
{
    $this->authorize('viewAny', Contact::class);

    $contacts = Contact::query()->orderBy('name')->paginate(24)->withQueryString();

    return view('contacts.index', compact('contacts'));
}
```

**API controllers** return JSON:

```php
return response()->json($contacts);
```

---

## Project Structure

```
serp/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/LoginController.php
│   │   │   ├── Contacts/ContactController.php
│   │   │   ├── Settings/
│   │   │   │   ├── SettingsController.php
│   │   │   │   ├── UserController.php
│   │   │   │   ├── RoleController.php
│   │   │   │   └── PermissionController.php
│   │   │   └── Api/
│   │   │       ├── Contacts/ContactController.php
│   │   │       ├── Settings/UserController.php
│   │   │       ├── Settings/RoleController.php
│   │   │       └── Chatter/ChatterController.php
│   │   ├── Middleware/CheckPermission.php
│   │   └── Requests/
│   │       ├── Contacts/{Store,Update}ContactRequest.php
│   │       └── Settings/{Store,Update}{User,Role}Request.php
│   ├── Models/
│   │   ├── User.php
│   │   ├── Contacts/Contact.php          (uses HasChatter)
│   │   ├── Security/Role.php
│   │   ├── Security/Permission.php
│   │   ├── Chatter/ChatterMessage.php
│   │   └── Settings/Setting.php
│   ├── Policies/
│   │   ├── ContactPolicy.php
│   │   ├── UserPolicy.php
│   │   ├── RolePolicy.php
│   │   ├── PermissionPolicy.php
│   │   └── SettingPolicy.php
│   ├── Services/
│   │   ├── Contacts/ContactService.php
│   │   └── Chatter/ChatterService.php
│   └── Traits/HasChatter.php
│
├── database/
│   ├── migrations/
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── PermissionSeeder.php
│       ├── RoleSeeder.php
│       ├── UserSeeder.php
│       ├── ContactSeeder.php
│       └── SettingSeeder.php
│
├── resources/views/
│   ├── layouts/{app,auth}.blade.php
│   ├── components/{navbar,sidebar,chatter,breadcrumb}.blade.php
│   ├── auth/login.blade.php
│   ├── contacts/{index,show,create,edit,_form}.blade.php
│   └── settings/
│       ├── index.blade.php
│       ├── _sidebar.blade.php
│       ├── users/{index,create,edit,_form}.blade.php
│       ├── roles/{index,create,edit,_form}.blade.php
│       └── permissions/index.blade.php
│
└── routes/
    ├── web.php    (session auth)
    └── api.php    (Sanctum token auth)
```

---

## Roles and Permissions

### How it works

1. **Permissions** are rows in the `permissions` table with a `key` like `contacts.read`.
2. **Roles** group permissions via the `role_permission` pivot table.
3. **Users** are assigned roles via the `user_role` pivot table.
4. **`User::hasPermission($key)`** checks if any active role has that permission. Users with the `admin` role bypass all checks.

### Available permissions

| Key              | Module   | Description                     |
|------------------|----------|---------------------------------|
| contacts.read    | contacts | View contacts                   |
| contacts.create  | contacts | Create contacts                 |
| contacts.write   | contacts | Edit and archive contacts       |
| contacts.unlink  | contacts | Delete contacts                 |
| users.read       | users    | View users                      |
| users.create     | users    | Create users                    |
| users.write      | users    | Edit users and assign roles     |
| users.unlink     | users    | Delete users                    |
| roles.read       | roles    | View roles                      |
| roles.create     | roles    | Create roles                    |
| roles.write      | roles    | Edit roles and assign perms     |
| roles.unlink     | roles    | Delete roles                    |
| settings.read    | settings | View settings                   |
| settings.write   | settings | Modify settings                 |

### Applying permissions

**Web controllers** — use Gate via `authorize()` for page access:
```php
$this->authorize('viewAny', Contact::class);  // uses ContactPolicy
```

**API routes** — use the `permission` middleware alias:
```php
Route::get('/contacts', [ContactController::class, 'read'])
    ->middleware('permission:contacts.read');
```

---

## Chatter / Logger System

Attach to any model by using the `HasChatter` trait.

### Setup

```php
use App\Traits\HasChatter;

class Invoice extends Model
{
    use HasChatter;
}
```

### Methods

```php
// Log an internal entry (auto-logged on create/update)
$invoice->logMessage('Invoice generated.', 'log');

// User comment
$invoice->logComment('Please review before sending.');

// System event
$invoice->logSystemMessage('Status changed to Paid.', [
    'old' => 'Draft', 'new' => 'Paid'
]);

// Relationships
$invoice->chatterMessages;  // All messages, newest first
$invoice->comments;         // Only user comments
```

### Message types

| Type    | When to use                             |
|---------|-----------------------------------------|
| log     | Auto-tracked field changes              |
| comment | User-written free-text comments         |
| system  | System events (archive, status change)  |

### ChatterService

Inject `ChatterService` into any controller or service:

```php
$this->chatterService->logCreated($model, 'Invoice');

$this->chatterService->logUpdated($model, [
    'email' => ['from' => 'old@example.com', 'to' => 'new@example.com'],
], 'Contact');

$this->chatterService->logArchived($model, 'Contact');
$this->chatterService->logUnarchived($model, 'Contact');
```

### Chatter in views

Classic server-rendered pages can use the Blade component:

```blade
@include('components.chatter', [
    'model'      => $invoice,
    'messages'   => $messages,
    'commentUrl' => route('invoices.comment', $invoice),
])
```

API-backed Alpine pages should fetch chatter from the relevant API endpoint and render messages client-side. The contacts show page follows this pattern.

---

## Adding a New Module

Complete example: **Invoices** module.

### Step 1 — Migration

```bash
php artisan make:migration create_invoices_table
```

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('number')->unique();
    $table->foreignId('contact_id')->constrained('contacts');
    $table->decimal('amount', 10, 2);
    $table->enum('status', ['draft', 'sent', 'paid'])->default('draft');
    $table->date('due_date')->nullable();
    $table->boolean('active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

### Step 2 — Model (`app/Models/Invoices/Invoice.php`)

```php
namespace App\Models\Invoices;

use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasChatter;

    protected $fillable = [
        'number', 'contact_id', 'amount', 'status',
        'due_date', 'active', 'created_by', 'updated_by',
    ];

    public function contact()
    {
        return $this->belongsTo(\App\Models\Contacts\Contact::class);
    }
}
```

### Step 3 — Policy (`app/Policies/InvoicePolicy.php`)

```php
public function viewAny(User $user): bool { return $user->hasPermission('invoices.read'); }
public function create(User $user): bool   { return $user->hasPermission('invoices.create'); }
public function update(User $user, Invoice $invoice): bool { return $user->hasPermission('invoices.write'); }
public function delete(User $user, Invoice $invoice): bool { return $user->hasPermission('invoices.unlink'); }
```

Register in `AppServiceProvider::boot()`:
```php
Gate::policy(Invoice::class, InvoicePolicy::class);
```

### Step 4 — Permissions

Add to `PermissionSeeder`:
```php
['name' => 'Read Invoices',   'key' => 'invoices.read',   'module' => 'invoices', 'description' => 'View invoices.'],
['name' => 'Create Invoices', 'key' => 'invoices.create', 'module' => 'invoices', 'description' => 'Create invoices.'],
['name' => 'Edit Invoices',   'key' => 'invoices.write',  'module' => 'invoices', 'description' => 'Edit invoices.'],
['name' => 'Delete Invoices', 'key' => 'invoices.unlink', 'module' => 'invoices', 'description' => 'Delete invoices.'],
```

Re-run: `php artisan db:seed --class=PermissionSeeder`

### Step 5 — Controller (`app/Http/Controllers/Invoices/InvoiceController.php`)

```php
namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Models\Invoices\Invoice;
use App\Services\Chatter\ChatterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private ChatterService $chatterService) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);
        $invoices = Invoice::with('contact')->paginate(25);
        return view('invoices.index', compact('invoices'));
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = DB::transaction(function () use ($request) {
            $invoice = Invoice::create(array_merge($request->validated(), [
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
            ]));
            $this->chatterService->logCreated($invoice, 'Invoice');
            return $invoice;
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created.');
    }

    public function write(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        DB::transaction(function () use ($request, $invoice) {
            $invoice->update(array_merge($request->validated(), ['updated_by' => auth()->id()]));
        });

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated.');
    }

    public function unlink(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);
        DB::transaction(fn () => $invoice->delete());
        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }
}
```

### Step 6 — Routes

In `routes/web.php`:
```php
Route::prefix('invoices')->name('invoices.')->middleware('auth')->group(function () {
    Route::get('/',            [InvoiceController::class, 'read'])->name('index');
    Route::get('/create',      [InvoiceController::class, 'create'])->name('create');
    Route::post('/',           [InvoiceController::class, 'store'])->name('store');
    Route::get('/{invoice}',   [InvoiceController::class, 'show'])->name('show');
    Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
    Route::put('/{invoice}',   [InvoiceController::class, 'write'])->name('update');
    Route::delete('/{invoice}',[InvoiceController::class, 'unlink'])->name('delete');
});
```

In `routes/api.php`:
```php
Route::prefix('invoices')->middleware('auth:sanctum')->group(function () {
    Route::get('/',            [ApiInvoiceController::class, 'read'])->middleware('permission:invoices.read');
    Route::post('/',           [ApiInvoiceController::class, 'create'])->middleware('permission:invoices.create');
    Route::get('/{invoice}',   [ApiInvoiceController::class, 'show'])->middleware('permission:invoices.read');
    Route::put('/{invoice}',   [ApiInvoiceController::class, 'write'])->middleware('permission:invoices.write');
    Route::delete('/{invoice}',[ApiInvoiceController::class, 'unlink'])->middleware('permission:invoices.unlink');
});
```

### Step 7 — Views

```
resources/views/invoices/
    index.blade.php      — list view (extend layouts.app)
    show.blade.php       — detail view with @include('components.chatter', [...])
    create.blade.php
    edit.blade.php
    _form.blade.php      — shared form partial
```

### Step 8 — Navbar

Add an entry in `resources/views/components/navbar.blade.php` following the existing Contacts/Settings pattern.

---

## Controller Naming Convention

All controllers use Odoo-style method names:

| Method  | HTTP | Route                | Purpose        |
|---------|------|----------------------|----------------|
| `read`  | GET  | /model               | List / index   |
| `show`  | GET  | /model/{id}          | Show one       |
| `create`| GET  | /model/create        | Create form    |
| `store` | POST | /model               | Save new       |
| `edit`  | GET  | /model/{id}/edit     | Edit form      |
| `write` | PUT  | /model/{id}          | Update record  |
| `unlink`| DELETE | /model/{id}        | Delete record  |

---

## API Reference

All API routes require a Bearer token (Laravel Sanctum).

```bash
# Obtain a token (add a custom endpoint or use Sanctum's mobile token endpoint)
POST /api/sanctum/token
```

### Contacts

| Method | Endpoint                    | Permission      |
|--------|-----------------------------|-----------------| 
| GET    | /api/contacts               | contacts.read   |
| POST   | /api/contacts               | contacts.create |
| GET    | /api/contacts/{id}          | contacts.read   |
| PUT    | /api/contacts/{id}          | contacts.write  |
| DELETE | /api/contacts/{id}          | contacts.unlink |
| PATCH  | /api/contacts/{id}/archive  | contacts.write  |
| GET    | /api/contacts/{id}/chatter  | contacts.read   |

### Users, Roles — same pattern under `/api/users` and `/api/roles`.

### Chatter

| Method | Endpoint     | Description                              |
|--------|--------------|------------------------------------------|
| GET    | /api/chatter | ?model_type=App\Models\Contacts\Contact&model_id=1 |
| POST   | /api/chatter | body, model_type, model_id, message_type |

---

## Switching Databases

### MySQL

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=serp_erp
DB_USERNAME=root
DB_PASSWORD=secret
```

```sql
CREATE DATABASE serp_erp CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### PostgreSQL

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=serp_erp
DB_USERNAME=postgres
DB_PASSWORD=secret
```

Run `php artisan migrate --seed` after updating `.env`.

---

## Production Checklist

- [ ] `APP_ENV=production` and `APP_DEBUG=false`
- [ ] Secure `APP_KEY` (already set by `php artisan key:generate`)
- [ ] Real database (MySQL or PostgreSQL)
- [ ] `SESSION_DRIVER=database` + `php artisan session:table && php artisan migrate`
- [ ] `CACHE_STORE=redis` or `database`
- [ ] `QUEUE_CONNECTION=database` or `redis`
- [ ] `php artisan optimize` (caches config, routes, views)
- [ ] Nginx/Apache → `public/` directory
- [ ] HTTPS via Let's Encrypt
- [ ] Set `SANCTUM_STATEFUL_DOMAINS` to your production domain

---

## Database Schema

```
users               — id, name, email, password, active, job_position, phone, avatar, company_id
companies           — id, name, email, phone, mobile, website, address fields, tax_id,
                      currency, logo, notes, active, created_by, updated_by
roles               — id, name, key, description, active
permissions         — id, name, key, module, description
role_permission     — role_id, permission_id  (pivot)
user_role           — user_id, role_id        (pivot)
user_company        — user_id, company_id     (pivot)
contacts            — id, company_id, parent_id, name, company_name, contact_type,
                      email, phone, mobile,
                      website, street, city, state, country, zip, tax_id,
                      job_position, notes, avatar, active, created_by, updated_by
tags                — id, name, color
contact_tag         — contact_id, tag_id      (pivot)
chatter_messages    — id, model_type, model_id, user_id, message_type, body, metadata(json)
settings            — id, key, value, group, type, label, description
```

---

## Credits

S-ERP — built with [Laravel](https://laravel.com), [Alpine.js](https://alpinejs.dev), and [Tailwind CSS](https://tailwindcss.com). Inspired by [Odoo](https://odoo.com)'s UX patterns.
# serp
