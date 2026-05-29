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

### Required arrays

Every model that is displayed in a list view must define `$sortable` and `$searchable` arrays. These power `SortsTable::apply()` and `SearchFilters::apply()` respectively — without them, sorting and advanced filtering will not work.

**`$sortable`** maps a sort key (used in the URL query string) to the actual database column:

```php
public array $sortable = [
    'name'       => 'name',
    'status'     => 'status',
    'created_at' => 'created_at',
];
```

**`$searchable`** maps a field key to its search metadata. Supported types: `string`, `email`, `integer`, `decimal`, `boolean`, `date`, `datetime`, `relation`.

```php
public array $searchable = [
    'name'   => ['label' => 'Name',   'column' => 'name',   'type' => 'string'],
    'status' => ['label' => 'Status', 'column' => 'status', 'type' => 'string'],
    'active' => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
    'created_by' => [
        'label'    => 'Created by',
        'column'   => 'created_by',
        'type'     => 'relation',
        'relation' => ['table' => 'users', 'field' => 'name'],
    ],
    'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
];
```

### `chatterTracked`

Fields listed here are diffed on every `update()` call and logged to the chatter. Use the plain string form for scalar fields and the array form for foreign key fields so the chatter resolves the display name instead of showing a raw ID:

```php
public array $chatterTracked = [
    'number'     => 'Number',
    'status'     => 'Status',
    // FK field — resolve via the related table
    'company_id' => ['label' => 'Company', 'table' => 'companies', 'column' => 'name'],
];
```

### Full example with scopes

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
        'number'     => 'Number',
        'status'     => 'Status',
        'company_id' => ['label' => 'Company', 'table' => 'companies', 'column' => 'name'],
    ];

    public array $sortable = [
        'number'     => 'number',
        'status'     => 'status',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'number'     => ['label' => 'Number', 'column' => 'number', 'type' => 'string'],
        'status'     => ['label' => 'Status', 'column' => 'status', 'type' => 'string'],
        'active'     => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'number', 'status', 'active', 'created_by', 'updated_by',
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

    /** Strict variant — blocks access when no companies are active. Use for strictly company-scoped modules. */
    public function scopeForCompanies(Builder $query, array $companyIds): Builder
    {
        return empty($companyIds)
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('company_id', $companyIds);
    }
}
```

**`scopeForCompanies` variants:** use the strict variant above (returns no records when `$companyIds` is empty) for modules where data absolutely must not be shown without an active company. For modules that are loosely company-scoped or where showing all records when no company is selected is acceptable (e.g. Contacts), use the pass-through variant:

```php
public function scopeForCompanies(Builder $query, array $companyIds): Builder
{
    if (empty($companyIds)) return $query; // show all when no company filter
    return $query->whereIn('company_id', $companyIds);
}
```

## 3. Add Permissions

Add module permissions in `database/seeders/PermissionSeeder.php`:

```php
['name' => 'Read Invoices',   'key' => 'invoices.read',   'module' => 'invoices', 'description' => 'View invoices.'],
['name' => 'Create Invoices', 'key' => 'invoices.create', 'module' => 'invoices', 'description' => 'Create invoices.'],
['name' => 'Edit Invoices',   'key' => 'invoices.write',  'module' => 'invoices', 'description' => 'Edit and archive invoices.'],
['name' => 'Delete Invoices', 'key' => 'invoices.unlink', 'module' => 'invoices', 'description' => 'Delete invoices.'],
['name' => 'Export Invoices', 'key' => 'invoices.export', 'module' => 'invoices', 'description' => 'Export invoices to XLSX / CSV.'],
['name' => 'Import Invoices', 'key' => 'invoices.import', 'module' => 'invoices', 'description' => 'Bulk-import invoices from XLSX or CSV. Each row is validated and created through the same flow as a manual New Invoice.'],
```

> **Note**: the `module.export` and `module.import` permissions are scoped to the same bulk-data flows as the manual create. `import` is effectively a super-set of `create` (one upload can create thousands of rows), so treat it with the same care you'd use for `create`. Always seed both, and only assign them to roles whose users you actually want operating on bulk data.

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

Policies keep controller authorization consistent. The standard methods are `viewAny`, `view`, `create`, `update`, `delete`, `comment`. Add custom methods when domain logic requires state-specific gating beyond simple permission checks.

```php
namespace App\Policies;

use App\Models\Invoices\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

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

    // List-level Gate abilities — used by <x-list>'s :model auto-derive
    // path. The model parameter is intentionally absent — these are checked
    // before any record exists.

    public function export(User $user): bool
    {
        return $user->hasPermission('invoices.export');
    }

    public function import(User $user): bool
    {
        return $user->hasPermission('invoices.import');
    }
}
```

**Returning a denial message:** when you need to explain why access was denied (not just that it was), return `Response::deny('message')` instead of `false`. The message surfaces in the UI.

```php
public function update(User $user, Invoice $invoice): Response|bool
{
    if ($invoice->status === 'posted') {
        return Response::deny('Posted invoices cannot be edited.');
    }
    return $user->hasPermission('invoices.write');
}
```

**Custom policy methods:** add methods beyond the standard five when you need domain-specific state checks that differ from a simple write permission. For example, a `submit` method that gates the action on both permission AND record state. Call them via `$this->authorize('submit', $invoice)` in the controller.

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

The `update()` method must use `detectChanges()` to build a real diff — never pass an empty array to `logUpdated()`. The chatter is only logged when something actually changed.

```php
namespace App\Services\Invoices;

use App\Models\Invoices\Invoice;
use App\Services\Chatter\ChatterService;
use Illuminate\Support\Facades\DB;

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
        $changes = $this->detectChanges($invoice, $data);
        $invoice->update($data);
        if (!empty($changes)) {
            $this->chatterService->logUpdated($invoice, $changes, 'Invoice');
        }
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

    public function delete(Invoice $invoice): void
    {
        $this->chatterService->log($invoice, 'Invoice deleted.', 'system');
        $invoice->delete();
    }

    private function detectChanges(Invoice $invoice, array $data): array
    {
        $changes = [];

        foreach ($invoice->chatterTracked as $field => $definition) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($invoice->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;

            $label  = is_array($definition) ? $definition['label']             : $definition;
            $table  = is_array($definition) ? ($definition['table']  ?? null)  : null;
            $column = is_array($definition) ? ($definition['column'] ?? 'name') : null;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveDisplay($old ?: null, $table, $column),
                'to'    => $this->resolveDisplay($new ?: null, $table, $column),
            ];
        }

        return $changes;
    }

    private function resolveDisplay(?string $id, ?string $table, ?string $column = null): string
    {
        if ($id === null || $id === '') return '—';
        if (!$table) return $id;

        $row = DB::table($table)->where('id', $id)->first();
        if (!$row) return $id;

        if ($column) return (string) ($row->{$column} ?? $id);
        return (string) ($row->name ?? $row->title ?? $id);
    }
}
```

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

**Always call `SearchFilters::apply()` and `SortsTable::apply()` in `read()`** — these wire up the `<x-search>` and `<x-sortable-th>` components. Without them, filters and column sorting do nothing.

**Always call `->withQueryString()` on the paginator** — this preserves active search/sort/filter parameters in pagination links. Without it, navigating to page 2 loses all filters.

**Pre-select the company when only one is active** — in `create()`, check if there is exactly one active company and pass `$defaultCompanyId` to the view to pre-populate the company field.

```php
namespace App\Http\Controllers\Invoices;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
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

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $invoices = $query->paginate(24)->withQueryString();

        return view('invoices.index', compact('invoices'));
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($invoice->company_id) || in_array($invoice->company_id, $activeCompanyIds), 403);

        $invoice->load(['company', 'creator', 'updater']);

        return view('invoices.show', compact('invoice'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Invoice::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        // Pre-select the company when exactly one is active
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;
        $companies = Company::whereIn('id', $activeCompanyIds)->active()->orderBy('name')->get();

        return view('invoices.create', compact('companies', 'defaultCompanyId'));
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
        abort_unless(is_null($invoice->company_id) || in_array($invoice->company_id, $activeCompanyIds), 403);

        $invoice->load(['company']);
        $companies = Company::whereIn('id', $activeCompanyIds)->active()->orderBy('name')->get();

        return view('invoices.edit', compact('invoice', 'companies'));
    }

    public function write(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        DB::transaction(fn () => $this->invoiceService->update($invoice, $request->validated()));

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice updated successfully.');
    }

    public function archive(Request $_request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($invoice->company_id) || in_array($invoice->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->invoiceService->archive($invoice));

        return redirect()->route('invoices.index')->with('success', 'Invoice archived.');
    }

    public function unarchive(Request $_request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($invoice->company_id) || in_array($invoice->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->invoiceService->unarchive($invoice));

        return redirect()->route('invoices.show', $invoice)->with('success', 'Invoice restored.');
    }

    public function unlink(Request $_request, Invoice $invoice)
    {
        $this->authorize('delete', $invoice);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($invoice->company_id) || in_array($invoice->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->invoiceService->delete($invoice));

        return redirect()->route('invoices.index')->with('success', 'Invoice deleted.');
    }

    public function addComment(Request $request, Invoice $invoice)
    {
        $this->authorize('comment', $invoice);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($invoice->company_id) || in_array($invoice->company_id, $activeCompanyIds), 403);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $invoice->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
```

## 8. Add Routes

Add routes inside the authenticated group in `routes/web.php`:

```php
use App\Http\Controllers\Invoices\InvoiceController;

Route::prefix('invoices')->name('invoices.')->group(function () {
    Route::get('/', [InvoiceController::class, 'read'])->middleware('permission:invoices.read')->name('index');
    Route::get('/create', [InvoiceController::class, 'create'])->middleware('permission:invoices.create')->name('create');
    Route::post('/', [InvoiceController::class, 'store'])->middleware('permission:invoices.create')->name('store');
    Route::get('/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:invoices.read')->name('show');
    Route::get('/{invoice}/edit', [InvoiceController::class, 'edit'])->middleware('permission:invoices.write')->name('edit');
    Route::put('/{invoice}', [InvoiceController::class, 'write'])->middleware('permission:invoices.write')->name('update');
    Route::patch('/{invoice}/archive', [InvoiceController::class, 'archive'])->middleware('permission:invoices.write')->name('archive');
    Route::patch('/{invoice}/unarchive', [InvoiceController::class, 'unarchive'])->middleware('permission:invoices.write')->name('unarchive');
    Route::delete('/{invoice}', [InvoiceController::class, 'unlink'])->middleware('permission:invoices.unlink')->name('delete');
    Route::post('/{invoice}/comment', [InvoiceController::class, 'addComment'])->middleware('permission:invoices.write')->name('comment');
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

### Record navigation

Show pages should include previous/next navigation so users can move through the list without returning to the index. Compute the navigation data in the controller by fetching all IDs and finding the current record's position:

```php
public function show(Invoice $invoice)
{
    $this->authorize('view', $invoice);

    $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
    $allIds = Invoice::active()->forCompanies($activeCompanyIds)->orderBy('number')->pluck('id');

    $currentIndex   = $allIds->search($invoice->id);
    $prevId         = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
    $nextId         = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
    $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
    $recordTotal    = $allIds->count();

    return view('invoices.show', compact('invoice', 'prevId', 'nextId', 'recordPosition', 'recordTotal'));
}
```

Pass these four variables to the view. Use them to render prev/next links in the top bar.

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
- Reuse the same web form requests (`StoreInvoiceRequest`, `UpdateInvoiceRequest`).
- Use `$request->user()->getAllowedCompanyIds()` for company scoping (not `CompanyContextService`).
- Use a private `abortIfOutOfScope()` helper to gate single-record methods consistently.

```php
namespace App\Http\Controllers\Api\Invoices;

use App\Http\Controllers\Controller;
use App\Http\Requests\Invoices\StoreInvoiceRequest;
use App\Http\Requests\Invoices\UpdateInvoiceRequest;
use App\Models\Invoices\Invoice;
use App\Services\Invoices\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService) {}

    public function read(Request $request): JsonResponse
    {
        $allowedIds = $request->user()->getAllowedCompanyIds();
        $query = Invoice::query()->forCompanies($allowedIds)->active();

        if ($search = $request->get('search')) {
            $query->where('number', 'like', "%{$search}%");
        }

        return response()->json(
            $query->orderBy('number')->paginate($request->integer('per_page', 24))
        );
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->abortIfOutOfScope($invoice, request()->user());
        return response()->json($invoice->load(['company', 'creator']));
    }

    public function create(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = DB::transaction(fn () => $this->invoiceService->create($request->validated()));
        return response()->json(['message' => 'Invoice created.', 'data' => $invoice], 201);
    }

    public function write(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->abortIfOutOfScope($invoice, $request->user());
        $invoice = DB::transaction(fn () => $this->invoiceService->update($invoice, $request->validated()));
        return response()->json(['message' => 'Invoice updated.', 'data' => $invoice]);
    }

    public function unlink(Request $request, Invoice $invoice): JsonResponse
    {
        $this->abortIfOutOfScope($invoice, $request->user());
        DB::transaction(fn () => $this->invoiceService->delete($invoice));
        return response()->json(['message' => 'Invoice deleted.']);
    }

    private function abortIfOutOfScope(Invoice $invoice, \App\Models\User $user): void
    {
        $allowedIds = $user->getAllowedCompanyIds();
        if (!empty($allowedIds) && !in_array($invoice->company_id, $allowedIds, true)) {
            abort(403);
        }
    }
}
```

Do not move normal Blade page data loading into API controllers unless the screen is intentionally API-driven.

## 13. Configuration Sub-Module Pattern

Simple configuration records (like tags, categories, statuses) that live inside a parent module do not need a full policy or dedicated form requests. Use a lightweight controller with `abort_unless` and inline `$request->validate()`:

```php
namespace App\Http\Controllers\Invoices;

use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Models\Invoices\InvoiceCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InvoiceCategoryController extends Controller
{
    public function read(Request $request)
    {
        abort_unless($request->user()->hasPermission('invoices.read'), 403);

        $query = InvoiceCategory::query()->withCount('invoices');
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);

        $categories = $query->paginate(24)->withQueryString();

        return view('invoices.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->hasPermission('invoices.write'), 403);

        $category = DB::transaction(fn () => InvoiceCategory::create($this->validatedData($request)));

        return redirect()->route('invoices.categories.index')->with('success', 'Category created.');
    }

    public function write(Request $request, InvoiceCategory $category)
    {
        abort_unless($request->user()->hasPermission('invoices.write'), 403);

        DB::transaction(fn () => $category->update($this->validatedData($request, $category)));

        return redirect()->route('invoices.categories.index')->with('success', 'Category updated.');
    }

    public function unlink(Request $request, InvoiceCategory $category)
    {
        abort_unless($request->user()->hasPermission('invoices.unlink'), 403);

        if ($category->invoices()->exists()) {
            return back()->with('error', 'Categories assigned to invoices cannot be deleted.');
        }

        DB::transaction(fn () => $category->delete());

        return redirect()->route('invoices.categories.index')->with('success', 'Category deleted.');
    }

    private function validatedData(Request $request, ?InvoiceCategory $category = null): array
    {
        return $request->validate([
            'name'  => ['required', 'string', 'max:255', 'unique:invoice_categories,name' . ($category ? ',' . $category->id : '')],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);
    }
}
```

This pattern is appropriate when: the records are simple config with no sensitive access rules, the parent module's permissions are sufficient, and a full policy + form request would add no real value.

## 14. Bulk Import (CSV / XLSX)

The import system mirrors the export system. Once you opt in, the `<x-list :model="...">` component automatically renders an Import trigger in a small toolbar above the table — **no view changes**, no per-module controller. Adding a new module to the importer is a four-step config + permission + policy change.

See [.claude/CLAUDE.md](.claude/CLAUDE.md) "Rule 14" for the security contract.

### Step 1 — Add the policy ability

The `import` Gate ability is a list-level check (no model parameter). `<x-list>` calls `Gate::allows('import', Invoice::class)` and only renders the Import button when it passes.

```php
public function import(User $user): bool
{
    return $user->hasPermission('invoices.import');
}
```

### Step 2 — Seed the permission

In `database/seeders/CoreSeeder::seedPermissions()`:

```php
['name' => 'Import Invoices', 'key' => 'invoices.import', 'module' => 'invoices',
 'description' => 'Bulk-import invoices from XLSX or CSV. Each row is validated and created through the same flow as a manual New Invoice.'],
```

### Step 3 — Register in `config/importable.php`

```php
'invoices' => [
    'class'          => \App\Models\Invoices\Invoice::class,
    'permission'     => 'invoices.import',
    'company_scoped' => true,
    'filename'       => 'invoices-import-template',
    'request'        => \App\Http\Requests\Invoices\StoreInvoiceRequest::class,
    'service'        => \App\Services\Invoices\InvoiceService::class,
    'service_method' => 'create',
    'fields' => [
        ['key' => 'number',      'label' => 'Number',      'type' => 'string', 'required' => true, 'example' => 'INV-001'],
        ['key' => 'partner_id',  'label' => 'Customer',    'type' => 'relation', 'relation' => ['table' => 'contacts', 'lookup' => ['id', 'name']], 'required' => true],
        ['key' => 'date',        'label' => 'Date',        'type' => 'date',     'required' => true, 'example' => '2026-05-30'],
        ['key' => 'amount_total','label' => 'Total',       'type' => 'decimal',  'example' => '1234.56'],
        ['key' => 'state',       'label' => 'State',       'type' => 'enum', 'options' => ['draft','posted','cancelled'], 'default' => 'draft'],
    ],
],
```

### Step 4 — Verify

`php artisan route:list --json | grep import` should show:
- `POST /import` → `import`
- `GET /import/{modelKey}/template` → `import.template`

Log in as a user with `invoices.import` and load the invoices index page — an Import button should appear in the small toolbar above the table. Click it, download the XLSX template, fill in two rows, upload — both rows are created via `InvoiceService::create()` inside a single `DB::transaction`.

### Important contracts (do NOT bypass)

| Contract | Enforced by |
|---|---|
| Same validation as manual `store()` | `ImportService::processRows` instantiates `StoreInvoiceRequest` and validates each row against its `rules()`. **`authorize()` is intentionally bypassed** — authorization already happened at the controller level. |
| Same business logic as manual `store()` | The service create method is called (chatter logging, post-create side effects). The importer never writes to the DB directly. |
| Atomic batch | The controller wraps `processRows()` in `DB::transaction`. If any row throws, every prior row in the batch rolls back. |
| Cross-company FK protection | Rule 11 lives in the FormRequest's `rules()`. Because the importer reuses those rules, an importer in Company A cannot reference a Company B record by id. Relation-by-name lookups in `ImportService::coerceRelation` also filter by active company IDs. |
| Formula-injection defence (CWE-1236) | PhpSpreadsheet reads with `setReadDataOnly(true)`; we never call `getCalculatedValue()`. Cells like `=cmd|...` are passed through to validation as literal strings and rejected by the FormRequest. |
| Row cap | `ImportService::MAX_ROWS = 5000`. Do not raise this per-module; if you need bigger files, queue a chunked background job. |

### Field types reference

| `type` | Behaviour |
|---|---|
| `string` / `email` / `url` | Pass-through, FormRequest validates. |
| `integer` | `(int) $raw`. |
| `decimal` | Strips currency / spaces, casts to `float`. |
| `boolean` | `yes`/`no`/`1`/`0`/`true`/`false`/`y`/`n` (case-insensitive). |
| `date` / `datetime` | `Carbon::parse` → ISO format. Malformed values fall through so the FormRequest rejects them with a row-specific message. |
| `enum` | Matches case-insensitively against `options[]`. Use `default` for a fallback. |
| `array` | Splits by `separator` (default `;`). |
| `relation` | Numeric → id. Non-numeric → looked up by `relation.lookup` columns. **Auto-filters by `company_id` when present.** |

Add `'default' => 'value'` so blank columns fall back; add `'example' => '...'` to seed the downloaded template.

## 15. In-App Notifications

When an action should alert another user (assignment, completion, rejection), call `$user->notify()` from the service method. This sends an in-app notification visible in the notification bell.

```php
$user->notify(
    'Invoice assigned to you: ' . $invoice->number,  // title
    '',                                               // body (optional)
    route('invoices.show', $invoice)                  // URL to link to
);
```

Call this inside the service after the state change, never from the controller. Only notify users other than the currently authenticated user:

```php
if ($targetUser && $targetUser->id !== auth()->user()?->id) {
    $targetUser->notify('...', '...', route(...));
}
```

## 16. Verify

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
- Model has fillable fields, casts, relationships, `$sortable`, `$searchable`, `chatterTracked`, and scopes.
- Model registered with `AuditableObserver` in `AppServiceProvider`.
- Permissions seeded — including `module.export` and `module.import` if applicable.
- Role permissions updated.
- Policy created with standard + any custom methods + `export()` + `import()` (list-level abilities, no model parameter).
- Form requests authorize and validate correctly.
- Company-scoped IDs validated against active allowed companies.
- Service has create, update, archive, unarchive, delete — with `detectChanges()` in update, chatter on every operation, notifications where appropriate.
- Web controller: standard method names, `SearchFilters` + `SortsTable` in `read()`, `->withQueryString()` on paginator, `$defaultCompanyId` in `create()`, company gate in every method, `DB::transaction` on every write.
- Routes have permission middleware on every route, ordered correctly.
- Views follow Odoo UI, `<x-list>` + `<x-search>` on index, `<x-chatter>` on show, record navigation on show.
- Navigation updated.
- Dynamic relation config added when needed.
- Export registered in `config/exportable.php` (when applicable).
- Import registered in `config/importable.php` (when applicable). `<x-list :model="Model::class">` renders the Import trigger automatically; no per-view code.
- Tests or at least route/view/build checks pass.
