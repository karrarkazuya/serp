# Implement A New Module

Use the Contacts module as the reference pattern for new business modules. A module should be server-rendered for the web UI, permission-protected, company-aware when needed, and split into model, request, controller, service, policy, routes, views, and seed data.

This guide uses `contacts` as the example module and `invoices` as the placeholder new module name.

## Target Structure

For a new `Invoices` module, create files in this shape:

```text
app/Models/Invoices/Invoice.php
app/Http/Controllers/Invoices/InvoiceController.php
app/Http/Requests/Invoices/StoreInvoiceRequest.php
app/Http/Requests/Invoices/UpdateInvoiceRequest.php
app/Policies/InvoicePolicy.php
app/Services/Invoices/InvoiceService.php
resources/views/invoices/index.blade.php
resources/views/invoices/show.blade.php
resources/views/invoices/create.blade.php
resources/views/invoices/edit.blade.php
resources/views/invoices/_form.blade.php
database/migrations/xxxx_xx_xx_xxxxxx_create_invoices_table.php
```

If the module has configuration records, follow the Contacts tags pattern:

```text
app/Http/Controllers/Invoices/Configuration/...
resources/views/invoices/configuration/...
```

or use a short module-local folder when the feature is small, as Contacts currently does with `resources/views/contacts/tags`.

## 1. Create Migration

Module tables should usually include:

- `id`
- `uuid`
- `company_id` if records belong to a company
- business fields
- `active` for archive/unarchive behavior
- `created_by`
- `updated_by`
- timestamps

Example:

```php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->uuid('uuid')->nullable()->unique();
    $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
    $table->string('number');
    $table->string('status')->default('draft');
    $table->boolean('active')->default(true);
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

`uuid`, `created_by`, and `updated_by` are set by `App\Observers\AuditableObserver`. Do not set these fields in services, model events, or model observers for individual modules. Register the new model with `AuditableObserver` in `App\Providers\AppServiceProvider`.

When no authenticated user exists, the observer uses the System user with ID `0`.

## 2. Create Model

Use scopes for common filtering. If the table has `company_id`, include a company scope.

```php
namespace App\Models\Invoices;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasChatter;

    public array $chatterTracked = [
        'number' => 'Number',
        'status' => 'Status',
        'company_id' => 'Company',
    ];

    protected $fillable = [
        'uuid',
        'company_id',
        'number',
        'status',
        'active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('active', false);
    }

    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        return empty($companyIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('company_id', $companyIds);
    }
}
```

## 3. Add Permissions

Add module permissions in `database/seeders/PermissionSeeder.php`:

```php
['name' => 'Read Invoices',   'key' => 'invoices.read',   'module' => 'invoices', 'description' => 'View invoices.'],
['name' => 'Create Invoices', 'key' => 'invoices.create', 'module' => 'invoices', 'description' => 'Create invoices.'],
['name' => 'Edit Invoices',   'key' => 'invoices.write',  'module' => 'invoices', 'description' => 'Edit and archive invoices.'],
['name' => 'Delete Invoices', 'key' => 'invoices.unlink', 'module' => 'invoices', 'description' => 'Delete invoices.'],
```

Then assign them in `database/seeders/RoleSeeder.php` as needed.

Run:

```bash
php artisan migrate:fresh --seed
```

or update seed data in the current database with:

```bash
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
```

## 4. Create Policy

Policies keep controller authorization consistent.

```php
namespace App\Policies;

use App\Models\Invoices\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('invoices.read');
    }

    public function view(User $user, Invoice $_invoice): bool
    {
        return $user->hasPermission('invoices.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('invoices.create');
    }

    public function update(User $user, Invoice $_invoice): bool
    {
        return $user->hasPermission('invoices.write');
    }

    public function delete(User $user, Invoice $_invoice): bool
    {
        return $user->hasPermission('invoices.unlink');
    }

    public function comment(User $user, Invoice $_invoice): bool
    {
        return $user->hasPermission('invoices.write');
    }
}
```

If policy auto-discovery does not pick it up, register it in `App\Providers\AuthServiceProvider`.

## 5. Create Form Requests

Use form requests for both authorization and validation. Validate company-scoped IDs against `CompanyContextService`, not only `exists`.

```php
namespace App\Http\Requests\Invoices;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('invoices.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        return [
            'company_id' => ['nullable', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'number' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'posted', 'cancelled'])],
        ];
    }
}
```

Create an `UpdateInvoiceRequest` with `invoices.write` authorization.

## 6. Create Service

Services own business operations and chatter logging. They should not own database transactions and should not set `uuid`, `created_by`, or `updated_by`. Transactions belong in controllers. Audit fields belong to `AuditableObserver`.

```php
namespace App\Services\Invoices;

use App\Models\Invoices\Invoice;
use App\Services\Chatter\ChatterService;

class InvoiceService
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function create(array $data): Invoice
    {
        $invoice = Invoice::create($data);

        $this->chatterService->logCreated($invoice, 'Invoice');

        return $invoice;
    }

    public function update(Invoice $invoice, array $data): Invoice
    {
        $invoice->update($data);
        $this->chatterService->logUpdated($invoice, [], 'Invoice');

        return $invoice->fresh();
    }

    public function archive(Invoice $invoice): Invoice
    {
        $invoice->update(['active' => false]);
        $this->chatterService->logArchived($invoice, 'Invoice');

        return $invoice;
    }

    public function unarchive(Invoice $invoice): Invoice
    {
        $invoice->update(['active' => true]);
        $this->chatterService->logUnarchived($invoice, 'Invoice');

        return $invoice;
    }
}
```

For production modules, copy the Contacts service change detection pattern instead of passing an empty changes array.

## 7. Create Web Controller

Use method names consistent with existing modules:

- `read`
- `show`
- `create`
- `store`
- `edit`
- `write`
- `archive`
- `unarchive`
- `unlink`
- `addComment`

Skeleton:

```php
namespace App\Http\Controllers\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\Invoices\Invoice;
use App\Models\Settings\Company;
use App\Services\Company\CompanyContextService;
use App\Services\Invoices\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(
        private readonly InvoiceService $invoiceService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Invoice::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Invoice::query()->with(['company', 'creator']);

        $query->forCompanies($activeCompanyIds);

        if ($search = $request->query('search')) {
            $query->where('number', 'like', "%{$search}%");
        }

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') !== 'all') {
            $query->active();
        }

        $invoices = $query->orderByDesc('id')->paginate(24)->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $invoice->load(['company', 'creator', 'updater']);
        $messages = $invoice->chatterMessages()->with('user')->latest()->get();

        return view('invoices.show', compact('invoice', 'messages'));
    }

    public function create()
    {
        $this->authorize('create', Invoice::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $companies = Company::whereIn('id', $activeCompanyIds)->active()->orderBy('name')->get();

        return view('invoices.create', compact('companies'));
    }

    public function store(StoreInvoiceRequest $request)
    {
        $invoice = DB::transaction(fn () => $this->invoiceService->create($request->validated()));

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice created successfully.');
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $companies = Company::whereIn('id', $activeCompanyIds)->active()->orderBy('name')->get();

        return view('invoices.edit', compact('invoice', 'companies'));
    }

    public function write(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        DB::transaction(fn () => $this->invoiceService->update($invoice, $request->validated()));

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
    }
}
```

Add archive/delete/comment methods when the module needs them.

## 8. Add Routes

Add routes inside the authenticated group in `routes/web.php`:

```php
use App\Http\Controllers\Invoices\InvoiceController;

Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'read'])->name('index');
    Route::get('/create', [InvoiceController::class, 'create'])->name('create');
    Route::post('/', [InvoiceController::class, 'store'])->name('store');
    Route::get('/{invoice}', [InvoiceController::class, 'show'])->name('show');
    Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->name('edit');
    Route::put('/{invoice}', [InvoiceController::class, 'write'])->name('update');
    Route::delete('/{invoice}', [InvoiceController::class, 'unlink'])->name('delete');
    Route::patch('/{invoice}/archive', [InvoiceController::class, 'archive'])->name('archive');
    Route::patch('/{invoice}/unarchive', [InvoiceController::class, 'unarchive'])->name('unarchive');
    Route::post('/{invoice}/comment', [InvoiceController::class, 'addComment'])->name('comment');
});
```

Put fixed sub-routes, such as `/configuration/...`, before `/{invoice}` so route model binding does not catch them.

## 9. Create Views

Follow the Contacts view pattern:

```text
resources/views/invoices/index.blade.php
resources/views/invoices/show.blade.php
resources/views/invoices/create.blade.php
resources/views/invoices/edit.blade.php
resources/views/invoices/_form.blade.php
```

Use the shared app layout:

```blade
@extends('layouts.app')
```

The index page should include:

- Odoo-style top bar
- `New` button if the user can create
- Search box
- Record count
- Table/list rows
- Pagination

The show page should include:

- Detail top bar
- Edit/archive/delete actions where allowed
- Record body
- Chatter component when the model uses `HasChatter`

Form pages should reuse `_form.blade.php`.

## 10. Add Navigation

Update `resources/views/components/navbar.blade.php` so the module appears in the app navigation. Keep module navigation consistent with Contacts:

- Module name in the top bar
- Optional `Configuration` dropdown for setup records
- Avoid duplicating the same label twice

## 11. Use Dynamic Relation Lookup

For relational fields, prefer the dynamic relation component instead of loading full lists into Blade.

Docs:

```text
docs/components/dynamic_relation_lookup.md
```

Add the target table to `config/relation_dropdowns.php`, then use:

```blade
<x-relation-dropdown
    table="contacts"
    field="name"
    name="contact_id"
    label="Contact"
    :selected="$invoice?->contact_id"
    relation="many2one"
/>
```

Also validate posted relation IDs in the form request.

## 12. Add Optional API Controller

Only add an API controller if an external client or API-driven screen needs JSON.

Web controllers:

- Return Blade views.
- Handle browser form submissions.

API controllers:

- Live under `app/Http/Controllers/Api`.
- Return JSON.
- Use API routes and API auth/middleware.

Do not move normal Blade page data loading into API controllers unless the screen is intentionally API-driven.

## 13. Verify

Run:

```bash
php -l app/Http/Controllers/Invoices/InvoiceController.php
php -l app/Http/Requests/Invoices/StoreInvoiceRequest.php
php -l app/Http/Requests/Invoices/UpdateInvoiceRequest.php
php artisan route:list --path=invoices
php artisan view:cache
npm run build
php artisan test
php artisan view:clear
```

## Module Checklist

- Migration created.
- Model has fillable fields, casts, relationships, and scopes.
- Permissions seeded.
- Role permissions updated.
- Policy created.
- Form requests authorize and validate correctly.
- Company-scoped IDs are validated against active allowed companies.
- Service handles transactions and chatter.
- Web controller follows Contacts method names.
- Routes are ordered correctly.
- Views follow the existing Odoo-inspired UI.
- Navigation updated.
- Dynamic relation config added when needed.
- Tests or at least route/view/build checks pass.
