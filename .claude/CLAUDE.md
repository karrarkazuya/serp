# S-ERP — Claude Instructions

## Project Overview

S-ERP is an Odoo-inspired ERP built on **Laravel 13**, **Blade**, **Alpine.js**, and **Tailwind CSS**. The UI is server-rendered: controllers fetch data, return Blade views, and handle form submissions. Alpine handles small UI interactions only — dropdowns, modals, inline edits. It is not a data layer.

Local dev uses **SQLite**. PHP 8.2+ required.

**IMPORTANT:** The `ss_workflow/` folder at the project root is an Odoo Python module — it is not part of the Laravel app. Never read, edit, or reference anything inside `ss_workflow/` unless asked to.

---

## Architecture

| Layer | Responsibility |
|---|---|
| **Routes** (`routes/web.php`) | Define HTTP verbs, attach `permission:` middleware per route, name routes |
| **Form Requests** | Handle BOTH `authorize()` AND `rules()` — never skip either |
| **Controllers** | Own `DB::transaction`, call services, redirect |
| **Services** | Own business logic + chatter logging. No transactions. No audit fields. |
| **Policies** | Map policy methods to `hasPermission()` calls |
| **AuditableObserver** | Sets `uuid`, `created_by`, `updated_by` automatically — never set these in services or controllers |

Services must not call `DB::transaction`. Controllers must not contain business logic. This boundary is firm.

Register every new model with `AuditableObserver` in `AppServiceProvider`.

---

## Route Organization

Module routes live in `routes/modules/<module>.php` and are `require`d from inside the `Route::middleware('auth')->group()` block in `routes/web.php`. Never paste module routes directly into `routes/web.php`.

Current modules: `chat`, `contacts`, `employees`, `workflow`, `accounting`, `inventory`, `settings`. `routes/web.php` only holds shared/global routes (auth, profile, dashboard, files, export, notifications, chatter API, relation-dropdown lookup, company switcher).

**When adding a new module:**

1. Create `routes/modules/<name>.php`. Open with the controller `use` imports + `use Illuminate\Support\Facades\Route;` — no fully-qualified namespaces inline in route definitions.
2. Wrap the routes in a single `Route::prefix('<name>')->name('<name>.')->group(function () { ... });` so the prefix and name prefix attach automatically.
3. Add `require __DIR__.'/modules/<name>.php';` to the "Feature modules" block in `routes/web.php` (keep alphabetical where possible; ordering only matters if one module's routes reference another's bindings).
4. Every route still needs explicit `->middleware('permission:...')` per Rule 6. `auth` is inherited from the outer group in `web.php` — do NOT add it again per route.
5. Run `php artisan route:list --json | jq length` (or the equivalent) before and after to confirm the route count is the new total (no silent drops).

Reference: [routes/modules/contacts.php](routes/modules/contacts.php) is the smallest complete example; [routes/modules/employees.php](routes/modules/employees.php) is the most complex (nested sub-prefixes, `{employee}`-scoped helpers, sub-routes ordered before `/{employee}` to avoid binding conflicts).

---

## The 12 Non-Negotiable Rules

Violating any of these is a bug, not a style issue.

### 0. Page toolbars — `<x-toolbar>` only

Every show, create, and edit page must use `<x-toolbar>` for the top action bar. Never hand-build the `<div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">` wrapper manually.

```blade
<x-toolbar
    :new-href="$newHref ?? null"         {{-- optional New button --}}
    :position="$recordPosition ?: null"  {{-- optional record position --}}
    :total="$recordTotal ?? null"        {{-- optional total for pagination --}}
    :prev-href="$prevId ? route('...', $prevId) : null"
    :next-href="$nextId ? route('...', $nextId) : null">
    <x-slot:breadcrumb>
        <a href="{{ route('...') }}" class="text-xs text-purple-600 hover:text-purple-700">Section</a>
        <span class="text-sm font-semibold text-gray-800">Record name</span>
    </x-slot:breadcrumb>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            {{-- cancel/save for create/edit; edit/archive/delete for show --}}
        </div>
    </x-slot:actions>
</x-toolbar>
```

The component handles: outer wrapper, breadcrumb div, RTL pagination arrows, `ms-auto` push on the pagination block. See `docs/components/toolbar.md` for all usage patterns.

### 1. List Views — `<x-list>` and `<x-search>` only

Every index/list view must use `<x-list>` for the table and `<x-search>` for filters. Never hand-build a table or custom search form. Use `@foreach` inside `<x-list>`, not `@forelse` — the empty state is handled by the component.

Every selectable list **must** pass `:model="ModelClass::class"` so the component auto-derives `canExport` and `canDelete` from the model's Gate policy. Never pass `:can-export` or `:can-delete` manually unless you are explicitly overriding the auto-derived value for a non-standard reason.

### 2. Chatter-enabled models — include `<x-chatter>` on show page

Any model using the `HasChatter` trait must have `<x-chatter>` on its show page. See `docs/components/chatter.md`.

### 3. Forms must follow Odoo design — two layouts only

- **Inline / border-bottom style** (simple models) — reference: `resources/views/contacts/_form.blade.php`
- **Card / section style** (complex models with grouped fields) — reference: `resources/views/settings/companies/_form.blade.php`

Do not invent new form layouts.

### 4. Relation fields — `<x-relation-dropdown>` only

Never use a raw `<select>` populated from a full table query. All relational fields must use `<x-relation-dropdown>`. The target table must be registered in `config/relation_dropdowns.php`. See `docs/components/dynamic_relation_lookup.md`.

The `relation` prop drives the "Search More" modal behavior automatically:
- `many2one` / `one2one` (`multiple=false`): clicking a row immediately selects and closes.
- `many2many` / `one2many` (`multiple=true`): staging mode — checkboxes, Select All on current page, "Add (N)" button applies all at once. Closing without clicking "Add" discards pending changes.

### 5. Tables with `company_id` — company filtering in every controller method

If a model has `company_id`, every controller method (read, show, create, store, edit, write, archive, unarchive, unlink, any custom action) must filter/gate by active companies:

```php
$activeCompanyIds = $this->companyContext->getActiveCompanyIds();
// In list queries:
$query->whereIn('company_id', $activeCompanyIds);
// In single-record methods:
abort_unless(in_array($record->company_id, $activeCompanyIds), 403);
```

Missing this in even one method is a data isolation security bug.

### 6. Permissions — route middleware on every route

Every route must have an explicit `permission:` middleware. Do not rely on `@can` in Blade or policy checks alone as the sole gate.

```php
Route::get('/',       [FooController::class, 'read'])   ->middleware('permission:foo.read')   ->name('index');
Route::get('/create', [FooController::class, 'create']) ->middleware('permission:foo.create') ->name('create');
Route::post('/',      [FooController::class, 'store'])  ->middleware('permission:foo.create') ->name('store');
Route::get('/{foo}',  [FooController::class, 'show'])   ->middleware('permission:foo.read')   ->name('show');
Route::get('/{foo}/edit', [FooController::class, 'edit'])       ->middleware('permission:foo.write')  ->name('edit');
Route::put('/{foo}',      [FooController::class, 'write'])      ->middleware('permission:foo.write')  ->name('update');
Route::patch('/{foo}/archive',   [FooController::class, 'archive'])   ->middleware('permission:foo.write')  ->name('archive');
Route::patch('/{foo}/unarchive', [FooController::class, 'unarchive']) ->middleware('permission:foo.write')  ->name('unarchive');
Route::delete('/{foo}',  [FooController::class, 'unlink'])      ->middleware('permission:foo.unlink') ->name('delete');
Route::post('/{foo}/comment', [FooController::class, 'addComment']) ->middleware('permission:foo.write') ->name('comment');
```

Permission key format: `module.read`, `module.create`, `module.write`, `module.unlink`.
For nested sub-modules: `workflow.tickets.read`, `workflow.tickets.write`, etc.

**Role assignment is gated by its own permission, not by `users.write`.** Only `users.assign_roles` may attach or detach roles on a user account; `users.write` covers everyday profile edits (name, email, password, active state). Anyone holding `users.assign_roles` can effectively grant admin, so treat it as a super-power. The role picker in [resources/views/settings/users/_form.blade.php](resources/views/settings/users/_form.blade.php) is hidden unless the actor has it; the controller drops `roles[]` from the payload otherwise. Do not collapse the two permissions.

**System roles are immutable at runtime.** `Role::SYSTEM_KEYS` (currently `['admin']`) marks seeder-owned roles. `Role::isSystem()` is the check. `RoleController::write()` strips `key` / `active` / `permissions` from the payload for system roles; `unlink()` rejects deletion outright. Do not add a code path that mutates them — `User::isAdmin()` matches by `key`, so renaming the key would demote every admin instantly and deactivating it would lock everyone out.

### 7. Every DB create or edit — `DB::transaction` in the controller

Every controller method that performs **any** database create or edit must wrap in `DB::transaction` — not only multi-step state changes. This covers every insert, update, and delete: single-row creates, single-row updates, toggles, pivots, increments, one-line writes, plus store, write, archive, unarchive, unlink, addComment, and any custom mutation. A single-call transaction still provides atomicity and rollback, and it keeps the rule mechanical: if the method writes to the DB at all, it is wrapped. No exceptions for "small" or "single-line" edits.

**Transaction scope — wrap the whole sequence, not each call.** If a controller method calls multiple services, or writes across multiple aggregates/tables, the transaction must wrap the entire sequence in a single `DB::transaction` block — not one transaction per service call. Nested or back-to-back `DB::transaction` calls inside the same method are a bug: they fragment the atomicity boundary and allow partial writes to commit when a later step fails. One method = one transaction covering all writes.

```php
// ✅ Correct — one transaction wraps both writes
DB::transaction(function () use ($data) {
    $order = $this->orderService->create($data);
    $this->inventoryService->reserve($order);
});

// ❌ Wrong — two independent transactions; if reserve() fails, the order is already committed
$order = DB::transaction(fn () => $this->orderService->create($data));
DB::transaction(fn () => $this->inventoryService->reserve($order));
```

```php
$record = DB::transaction(fn () => $this->fooService->create($data));
```

### 8. Soft deletes — every application table and model

Every application database table must have a `deleted_at` column (`$table->softDeletes()`), and every corresponding Eloquent model must use the `SoftDeletes` trait.

**Excluded** (never add `deleted_at` to these):
- Laravel framework tables: `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `personal_access_tokens`
- Pure pivot tables with composite-only primary keys (no `id` column): e.g. `contact_tag`, `workflow_user_group`, `role_permission`, `account_move_line_taxes`, `inventory_product_routes`, etc.

**Migration pattern:**
```php
Schema::table('my_table', function (Blueprint $table) {
    $table->softDeletes();
});
```

**Model pattern:**
```php
use Illuminate\Database\Eloquent\SoftDeletes;

class MyModel extends Model
{
    use SoftDeletes;
    // ...
}
```

When adding a new table, always add `$table->softDeletes()` in the migration and `use SoftDeletes;` in the model. This is non-negotiable — missing it is a data-loss bug.

### 9. Export / Bulk Actions — `<x-export>` + `<x-list :selectable :model>` + `config/exportable.php`

Every list view that supports bulk actions (export, bulk delete) must use the component system. Never hand-roll export logic, bulk-delete endpoints, or custom action bars.

#### How bulk action permissions work

`<x-list>` accepts a `:model="ModelClass::class"` prop. The component calls `Gate::allows('export', $model)` and `Gate::allows('delete', $model)` automatically to derive `$canExport` and `$canDelete`. The Actions dropdown and bulk-delete confirm bar appear only for users who have the relevant permission — no per-view `@can` checks needed.

**Adding a new bulk action in the future** only requires updating `TableList.php` + `list.blade.php` — no view changes across the codebase.

#### Pieces

| Piece | Role |
|---|---|
| `<x-list :selectable="true" :model="Model::class" :total-count="$paginator->total()">` | Checkbox column, selection action bar, "Actions" dropdown (Export / Delete), auto-derived from policy |
| `<x-export :fields="..." :export-url="route('export')" model-key="...">` | Alpine modal: field picker, format toggle, hidden POST form |
| `config/exportable.php` | Server-side whitelist — defines allowed columns and permission key per model |
| `POST /export` (`ExportController`) | Generic endpoint: validates model key, checks permission, builds query, returns file download |
| Controller `bulkUnlink` method + `:bulk-delete-url` | Per-module opt-in for bulk delete; "Yes, delete" button is disabled until this is wired |

#### Adding export to a list view

**1. Add entry to `config/exportable.php`:**
```php
'contacts' => [
    'class'          => \App\Models\Contacts\Contact::class,
    'permission'     => 'contacts.export',
    'company_scoped' => true,
    'filename'       => 'contacts',
    'fields'         => [
        ['key' => 'name',  'label' => 'Name',  'column' => 'name'],
        ['key' => 'email', 'label' => 'Email', 'column' => 'email'],
        // ...
    ],
],
```

**2. Add `export` policy method** (no model instance needed — list-level check):
```php
public function export(User $user): bool
{
    return $user->hasPermission('contacts.export');
}
```

**3. Seed the permission** in `CoreSeeder::seedPermissions()`:
```php
['name' => 'Export Contacts', 'key' => 'contacts.export', 'module' => 'contacts', 'description' => '...'],
```

**4. In the index Blade view:**
```blade
@can('export', \App\Models\Contacts\Contact::class)
<x-export
    :fields="config('exportable.contacts.fields', [])"
    :export-url="route('export')"
    model-key="contacts"
/>
@endcan

<x-list :paginator="$records"
        :selectable="true"
        :model="\App\Models\Contacts\Contact::class"
        :total-count="$records->total()"
        :bulk-delete-url="route('contacts.bulk-delete')">
    <x-slot:columns>
        {{-- your <th> columns here --}}
    </x-slot:columns>

    @foreach($records as $record)
    <tr ... onclick="window.location='...'">
        <td class="w-10 px-3 py-2 text-center" @click.stop>
            <input type="checkbox"
                   class="list-checkbox rounded border-gray-300 text-purple-600"
                   x-model="selected"
                   value="{{ $record->id }}">
        </td>
        {{-- your <td> cells here --}}
    </tr>
    @endforeach
</x-list>
```

Key rules:
- Always pass `:model="ModelClass::class"` — never pass `:can-export` or `:can-delete` manually.
- Checkbox `<td>` must come first in every row, with `@click.stop` to prevent row-click firing.
- Checkbox must have class `list-checkbox` (used by the select-all logic in the component).
- `x-model="selected"` works because the checkbox is a descendant of the `x-data` wrapper rendered by `<x-list :selectable>`.
- Never expose unlisted columns: the ExportController validates every field key against `config/exportable.php`.
- `company_scoped: true` means the controller applies `whereIn('company_id', $activeCompanyIds)` automatically.
- `:bulk-delete-url` is optional — omitting it shows the Delete action (if policy allows) but disables the "Yes, delete" button with a tooltip. This lets the UI communicate the intent while the controller is being wired.

#### Adding bulk delete to a module

**1. Policy `delete` method must accept a nullable model** (list-level check uses `null`):
```php
public function delete(User $user, ?Contact $contact = null): bool
{
    if ($contact === null) {
        return $user->hasPermission('contacts.unlink'); // list-level: permission only
    }
    return $user->hasPermission('contacts.unlink') && $this->withinActiveCompany($contact);
}
```

**2. Add a `bulkUnlink` controller method:**
```php
public function bulkUnlink(Request $request): RedirectResponse
{
    $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
    $selectAll = $request->boolean('select_all');
    $ids = $request->input('ids', []);

    DB::transaction(function () use ($selectAll, $ids, $activeCompanyIds) {
        $query = Contact::whereIn('company_id', $activeCompanyIds);
        if (!$selectAll) {
            $query->whereIn('id', $ids);
        }
        foreach ($query->get() as $contact) {
            if (Gate::allows('delete', $contact)) {
                $this->contactService->delete($contact);
            }
            // items that fail the gate are silently skipped (UI warns "some may be skipped")
        }
    });

    return redirect()->route('contacts.index')->with('success', 'Selected records deleted.');
}
```

**3. Add the route** (named so `:bulk-delete-url` can reference it):
```php
Route::delete('/bulk', [ContactController::class, 'bulkUnlink'])
    ->middleware('permission:contacts.unlink')
    ->name('bulk-delete');
```

**4. Pass the URL to `<x-list>`:**
```blade
<x-list ... :bulk-delete-url="route('contacts.bulk-delete')">
```

**Never bypass `ExportService::safeValue()` / `setValueExplicit(..., TYPE_STRING)`.** Every cell goes through `safeValue()` which prefixes leading `=`/`+`/`-`/`@`/`\t`/`\r` with a single quote, and XLSX cells use `setValueExplicit` so PhpSpreadsheet's default value binder doesn't re-parse `=...` as a formula (CWE-1236, formula injection). Direct `setValue()` or raw `fputcsv` arrays let any user-controllable exported field (contact name, employee name, ticket description, account label) execute as a formula in Excel / LibreOffice / Google Sheets when a manager opens the file. If a future column genuinely needs to render a formula, build it server-side via a dedicated path — never from user input.

See `docs/components/export.md` for the full component reference.

### 10. All file uploads — `FileService` only. All file serving — `/files/{uuid}` only.

**Never** store uploaded files directly in controllers or hand-roll file-serving routes. Every file upload in the application must go through `App\Services\FileService::store()`. Every file is served through the single route `GET /files/{uuid}` (`files.serve`) handled by `App\Http\Controllers\FileController`.

#### Uploading

```php
// In a controller (inside DB::transaction or wrapping try/catch):
$fileRecord = $this->fileService->store(
    file: $request->file('avatar'),
    directory: 'avatars/contacts',
    permissionKey: 'contacts.read',
    // context: $model,   ← only when ticket/chat-room ownership check is needed
);
$data['avatar'] = $fileRecord->uuid; // store UUID in the model column
```

#### Image uploads — never use bare `'image'` validation

Laravel's `'image'` rule allows `image/svg+xml`. SVG can carry `<script>`/`<foreignObject>`/`on*` handlers, and any browser that renders the file inline will execute them in the app origin → stored XSS. The `FileService::store()` boundary rejects SVG too (defense-in-depth), and `FileController::serve()` forces `Content-Disposition: attachment` + `Content-Security-Policy: sandbox` for any legacy SVG. But **request-layer validation must surface clear user-facing errors**.

Use this exact pattern for any image field:

```php
// ✅ Correct
'avatar' => 'nullable|file|max:2048|mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp',

// ❌ Wrong — silently accepts SVG (Laravel's `image` rule includes it)
'avatar' => 'nullable|image|max:2048',
```

`FileService::store()` signature:
```php
public function store(
    UploadedFile $file,
    string $directory,
    ?string $permissionKey,  // e.g. 'contacts.read', 'employees.read', 'workflow.tickets.read'
    ?Model $context = null,  // Ticket or ChatRoom for ownership-scoped files
    ?Model $source  = null,  // record that owns this file — used by the garbage collector
    string $disk = 'local',
): \App\Models\File
```

- Returns an `\App\Models\File` Eloquent record. Store its `uuid` in the owning model's column.
- Automatically generates a thumbnail for images (≤ 200 px wide, JPEG) stored in `thumbs/` next to the original.
- File metadata (disk, path, mime, size, uploader) lives entirely in the `files` table.
- `source` stores the owning record's table + id in `files.source_type` / `files.source_id`. Always pass it so the garbage collector can detect orphaned files. For **create** operations (source doesn't exist yet), pass `null` and update the file record immediately after saving the source model: `$fileRecord->update(['source_type' => $model->getTable(), 'source_id' => $model->id]);`

#### Permission keys per module

| Upload context | `permissionKey` | `context` | `source` |
|---|---|---|---|
| Contact avatar | `contacts.read` | `null` | `$contact` |
| Employee avatar | `employees.read` | `null` | `$employee` |
| Employee document / contract image | `employees.read` | `null` | `$document` / `$contract` |
| Company logo | `settings.read` | `null` | `$company` |
| Workflow ticket input file | `workflow.tickets.read` | `$ticket` | `$ticket` |
| Ticket chat attachment | `workflow.tickets.read` | `$ticket` | `$message` |
| Chat room attachment | `null` (membership-only) | `$chatRoom` | `$message` |

#### Serving & thumbnail

```blade
{{-- Full file (inline for images, download for others) --}}
<a href="{{ route('files.serve', $model->avatar) }}">View</a>

{{-- Thumbnail (images only; 404 for non-images / no thumb) --}}
<img src="{{ route('files.thumbnail', $model->avatar) }}">
```

#### Access control in `FileController`

1. Requires `auth` middleware.
2. If `permission_key` is set → `$user->hasPermission($file->permission_key)` must pass.
3. If `context` is a `Ticket` → user must pass `Ticket::forUser($user)` scope.
4. If `context` is a `ChatRoom` → user must be a member of the room.

#### Deleting files

Always delete through `FileService::delete(File $file)` — this removes both the disk file and the DB record. Never call `Storage::delete()` directly.

```php
if ($model->avatar) {
    $this->fileService->deleteByUuid($model->avatar);
}
```

### 11. Cross-company FK rules — every form-request relation must be company-scoped

Rule 5 scopes **reads** by `company_id`. Rule 11 scopes **writes**: every form request that validates a foreign-key value into a company-scoped table must reject IDs outside the actor's active companies. Bare `'exists:table,id'` on a company-scoped table is a cross-tenant FK injection bug — it lets a user in Company A wire an A-owned record (Contract, Picking, Employee, Lot, etc.) to a B-owned record (department, location, product, supplier contact, manager). The parent row carries `company_id = A` (gated) but the FK link crosses tenants and downstream flows (replenishment, payroll, audit trails) silently pick it up.

```php
// ❌ Wrong — accepts a contact / location / product from any company
'partner_id'      => ['nullable', 'exists:contacts,id'],
'location_src_id' => ['required', 'exists:inventory_locations,id'],

// ✅ Correct — scoped to active companies
$activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
$contactRule = Rule::exists('contacts', 'id')->where(function ($q) use ($activeCompanyIds) {
    empty($activeCompanyIds)
        ? $q->whereRaw('1 = 0')
        : $q->whereIn('company_id', $activeCompanyIds);
});
'partner_id' => ['nullable', $contactRule],
```

**Empty `$activeCompanyIds` must deny all** — matches how list pages render nothing when the user has no allowed companies. The `whereRaw('1 = 0')` branch is mechanical, don't omit it.

**Shared records** — some tables intentionally hold rows with `company_id = null` (supplier/customer/transit locations, shared service products, shared routes). For those, the rule must also accept null:
```php
$locationRule = Rule::exists('inventory_locations', 'id')->where(function ($q) use ($activeCompanyIds) {
    $q->whereNull('company_id');
    if (!empty($activeCompanyIds)) $q->orWhereIn('company_id', $activeCompanyIds);
});
```

**Inventory module** uses a shared trait so we don't paste this everywhere: `App\Http\Requests\Inventory\Concerns\InventoryFkRules`. New Inventory requests must `use InventoryFkRules;` and call `inventoryLocationRule()` / `inventoryProductRule()` / `companyScopedExists()` / `contactInActiveCompaniesRule()`. Don't paste raw `Rule::exists(...)` for inventory tables.

**Hierarchy FKs need a cycle guard.** Form validation only checks that the target exists — it doesn't catch `A→B→A` loops. For any hierarchy field (`parent_id`, `manager_id`, `coach_id`, `expense_manager_id`, `attendance_manager_id`, `related_contacts`), add a bounded-walk check at the controller before the transaction:

```php
if (array_key_exists('parent_id', $data) && $data['parent_id']) {
    $parentId = (int) $data['parent_id'];
    if ($parentId === $contact->id || $this->isDescendantOf($parentId, $contact->id)) {
        return back()->withInput()->with('error', 'Selected parent would create a circular hierarchy.');
    }
}
```

Reference implementations: `ContactController::isDescendantOf`, `EmployeeController::isEmployeeDescendantOf` (64-step bounded walk so already-corrupted data can't hang the request).

### 12. Never expose raw column names as user-facing text in views

Form field labels, search filter labels, table column headers, group-by labels, error messages, and every other piece of human-readable text rendered in Blade must use a written-out label — `First Name`, `Created on`, `Company` — not the raw column identifier (`first_name`, `created_at`, `company_id`). The `name=`/`id=`/`value=` attributes on inputs are free to mirror the column (Eloquent's mass-assignment depends on it); the **visible** text the user reads must not.

```blade
{{-- ❌ Wrong — raw column rendered as the label --}}
<label for="first_name">first_name</label>
<input type="text" name="first_name" id="first_name">

{{-- ✅ Correct — human label, column name lives only in the form attribute --}}
<label for="first_name">{{ __('employees.first_name') }}</label>
<input type="text" name="first_name" id="first_name">
```

Where the labels live:
- **Form fields**: hard-coded English string OR a `__('module.key')` translation. Never `{{ $field }}` where `$field` is a column.
- **List / search / group-by**: the model's `$searchable` / `$sortable` arrays must always include a `'label' => 'Human Label'` key — the component reads `label`, not the array key. A new searchable entry without a `label` is a Rule 12 violation.
- **Table headers**: use the same label source as search (`$fields[$col]['label']`) or a hard-coded human string.
- **Chatter change logs**: `$chatterTracked` maps `column => 'Human Label'`. The label is what shows in the audit feed — `name` is fine, `internal_reference` should be `'Internal Reference'`, not `'internal_reference'`.
- **Validation messages**: when overriding `messages()` on a FormRequest, address the field by its human label too, not the column.

Why: the visible UI must read as the business domain, not the schema. Renaming a column (or supporting a second language) must not require Blade rewrites; an attacker reading view source must not learn the column layout for free.

### 13. Enum-like columns must declare `options` in `$searchable`

Any column whose stored value is one of a fixed set (DB enum, `string(16)` holding `'draft'`/`'posted'`/..., priority `'1'/'2'/'3'`, etc.) must declare its options in the model's `$searchable` array. Filters and exports both consume this single source of truth:

- **Filter dropdown** — fields with non-empty `options` auto-upgrade to type `'select'` and render as a `<select>` in the Add Custom Filter modal (single-value for `=`/`!=`, multi-value for `in`/`not_in`). Without `options`, the user gets a free-text input and has to remember the exact stored value.
- **Export label mapping** — `ExportService` cross-references the model's `$searchable` and emits the human label (`"Pending"`) instead of the raw enum value (`"pending"`) in the exported XLSX/CSV. Without `options`, the export bleeds DB internals.

```php
// ❌ Wrong — stored value leaks to both the filter input and the export cells
public array $searchable = [
    'state'    => ['label' => 'State',    'column' => 'state',    'type' => 'string'],
    'priority' => ['label' => 'Priority', 'column' => 'priority', 'type' => 'string'],
];

// ✅ Correct — single source of truth, picked up by filter + export automatically
public array $searchable = [
    'state'    => ['label' => 'State',    'column' => 'state', 'options' => [
        'draft' => 'Draft', 'pending' => 'Pending', 'completed' => 'Completed',
        'rejected' => 'Rejected', 'skipped' => 'Skipped', 'closed' => 'Closed',
    ]],
    'priority' => ['label' => 'Priority', 'column' => 'priority', 'options' => [
        '1' => 'Normal', '2' => 'Medium', '3' => 'High',
    ]],
];

// ✅ Even better — when the model already has a const map, reference it
//    so the labels stay in one place across the model, filter, and export.
public array $searchable = [
    'state' => ['label' => 'State', 'column' => 'state', 'options' => self::STATES],
];
```

Accepted `options` shapes (`SearchFilters::normalizeOptions` coerces all three):
- Already normalized — `[['value' => 'draft', 'label' => 'Draft'], …]`
- Associative — `['draft' => 'Draft', 'done' => 'Done']` (also works for stringy-int keys: `['1' => 'Normal', '2' => 'Medium']`)
- Plain list — `['draft', 'pending', 'done']` (label is auto-title-cased)

`'type' => 'select'` is optional — declaring `options` on a `string` field is enough to opt in; the helper promotes the type automatically. Setting `'type' => 'boolean'` keeps the Yes/No dropdown intact even if `options` is present.

The export path picks this up with **no per-model exportable.php change** — adding options to `$searchable` is the only step needed for both the filter UI and the labelled export. Skipping this is a Rule 12 violation: the user-facing pill/cell ends up showing the raw enum identifier instead of the business label.

---

## Controller Method Naming

Use exactly these names — do not invent synonyms:

| Method | Purpose |
|---|---|
| `read` | Index/list |
| `show` | Detail view |
| `create` | Create form |
| `store` | Handle create form POST |
| `edit` | Edit form |
| `write` | Handle edit form PUT/PATCH |
| `archive` | Soft-archive a record |
| `unarchive` | Restore archived record |
| `unlink` | Hard delete |
| `addComment` | Add chatter comment |

---

## Tree Views — `<x-tree>` component

Any hierarchical index view (org chart, department tree, category tree, etc.) must use `<x-tree>`. Never hand-build a recursive tree in a view.

### Usage

```blade
<x-tree :nodes="$treeNodes" empty-text="No records found." />
```

### Node structure

Build nodes in the controller, never in the view. Each node:

```php
[
    'id'          => int,
    'name'        => string,
    'url'         => string,        // route to detail page
    'avatar'      => string|null,   // image URL or null
    'initials'    => string,        // 2-char fallback, e.g. 'JD'
    'subtitle'    => string|null,   // secondary label (job title, etc.)
    'meta'        => string|null,   // tertiary label (department, etc.)
    'badge'       => string|null,   // optional status chip text
    'badge_color' => string|null,   // 'green'|'blue'|'orange'|'red'|'gray'
    'children'    => [...same],     // empty array for leaf nodes
]
```

### Building the tree (two-pass pattern)

```php
private function buildTree(Collection $records): array
{
    $map = [];
    foreach ($records as $r) {
        $map[$r->id] = [
            'id' => $r->id, 'name' => $r->name,
            'url' => route('module.show', $r),
            'avatar' => null,
            'initials' => mb_strtoupper(mb_substr($r->name, 0, 2)),
            'subtitle' => null, 'meta' => null,
            'badge' => null, 'badge_color' => 'gray',
            'children' => [],
        ];
    }
    $childrenOf = []; $roots = [];
    foreach ($records as $r) {
        if ($r->parent_id && isset($map[$r->parent_id])) {
            $childrenOf[$r->parent_id][] = $r->id;
        } else {
            $roots[] = $r->id;
        }
    }
    $build = function (int $id) use (&$build, &$map, $childrenOf): array {
        $node = $map[$id];
        foreach ($childrenOf[$id] ?? [] as $childId) {
            $node['children'][] = $build($childId);
        }
        return $node;
    };
    return array_map($build, $roots);
}
```

### Controller: adding tree alongside kanban/list

- Detect `$view = $request->query('view', 'kanban')` before the paginator.
- For `tree`: fetch all filtered records (no pagination, limit 500), build nodes, return `compact('treeNodes', 'total', 'view')`.
- For kanban/list: paginate normally, return `compact('employees', 'view')`.
- In Blade: use `$view = $view ?? request('view', 'kanban')` so both paths work.
- Skip pagination arrows when `$view === 'tree'`; show `$total` count instead.
- Add a tree toggle button next to kanban/list buttons.

### Component files

- `app/View/Components/Tree.php`
- `resources/views/components/tree.blade.php` — wrapper, passes `$nodes` array
- `resources/views/components/_tree-node.blade.php` — recursive Blade partial (includes itself for children)

Nodes start collapsed. Click the chevron to expand. Children are indented with a left-border connector line.

---

## New Module Checklist

When building a new module, follow `docs/implement_new_module.md` using Contacts as the reference. Quick checklist:

- [ ] Migration: `id`, `uuid`, `company_id` (if applicable), business fields, `active`, `created_by`, `updated_by`, timestamps, **`softDeletes()`**
- [ ] Model: fillable, casts, relationships, `scopeActive`, `scopeForCompanies` (if company-scoped), **`use SoftDeletes`**
- [ ] Register model with `AuditableObserver` in `AppServiceProvider`
- [ ] Permissions seeded in `PermissionSeeder` and assigned in `RoleSeeder`
- [ ] Policy: `viewAny`, `view`, `create`, `update`, `delete`, `comment`, **`export`**. For company-scoped models, `use App\Policies\Concerns\ScopesByCompany;` and gate model-bound abilities with `&& $this->withinActiveCompany($model)` — this makes `@can` checks fail-closed for cross-tenant records without relying on the controller. Reference: [EmployeePolicy.php](app/Policies/Employees/EmployeePolicy.php).
- [ ] Form requests: authorize + validate. **Every FK to a company-scoped table** uses `Rule::exists(...)->whereIn('company_id', $activeCompanyIds)` (Rule 11), not bare `'exists:table,id'`. Image fields use explicit `mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp` — never bare `'image'` (Rule 10).
- [ ] Service: create, update, archive, unarchive — business logic + chatter, no transactions
- [ ] Controller: follows naming above, wraps in `DB::transaction`, applies company gate
- [ ] Routes: create `routes/modules/<name>.php`, add `require __DIR__.'/modules/<name>.php';` to `routes/web.php` (see "Route Organization" above). Permission middleware on every route, fixed sub-routes before `/{model}` to avoid binding conflicts
- [ ] Views: `<x-list :selectable="true" :model="Model::class">` + `<x-search>` on index, `<x-chatter>` on show, Odoo form style, `<x-relation-dropdown>` for relations
- [ ] Navigation: update `resources/views/components/navbar.blade.php`
- [ ] Register target tables in `config/relation_dropdowns.php` for any relation dropdown
- [ ] make sure you added $sortable, $searchable, $chatterTracked, $fillable and make sure they are linked and used
- [ ] For every enum-like column (DB enum, fixed string set, priority codes), declare `'options' => [...]` in `$searchable` (Rule 13). The filter dropdown and export labels both read from it — no extra exportable.php entry needed.
- [ ] Export: add entry to `config/exportable.php`, seed `module.export` permission, add `export()` to policy (nullable model param), add `<x-export>` + row checkboxes to index view
- [ ] Bulk delete (opt-in): make policy `delete()` accept `?Model $model = null`, add `bulkUnlink` controller method, add bulk-delete route, pass `:bulk-delete-url` to `<x-list>`
- [ ] Group By: add `GroupsQuery` import + group-by detection block to `read()` (before `SortsTable::apply()`), add `@if(isset($groups))` / `@else` branching in the index view with `<x-list :grouped="true">` and `<tbody x-data>` blocks

---

## Workflow Module — Additional Context

The Workflow module is the most complex. Key specifics:

**Ticket input types** (11 total): `char`, `int`, `float`, `date`, `datetime`, `boolean`, `select`, `multiselect`, `textarea`, `file`, `label`

**File uploads**: all file input values go through `FileService::store()` with `permissionKey = 'workflow.tickets.read'` and `context = $ticket`. The returned File UUID is stored in `workflow_record_inputs.value_file_path`. Served via the unified `GET /files/{uuid}` route which checks both permission and ticket ownership. MIME validated inside `FileService` via `finfo`. Allowed: images (jpg/png/gif/webp), pdf, office docs (doc/docx/xls/xlsx/ppt/pptx), text/csv, odt/ods. Max 10 MB. No zips or executables.

**Input value architecture**:
- Template definitions: `workflow_template_inputs` (owned by `ticket_template` or `procedure_step`)
- Options: `workflow_template_input_options`
- Actual values: `workflow_record_inputs` (polymorphic via `record_type` / `record_id`)
- Multiselect pivot: `workflow_record_input_multiselect`

**Ownership scoping** in Workflow: Tickets are company-scoped AND user-scoped. Use `Ticket::scopeForUser()` and `CompanyContextService`. Never skip the company gate.

---

## Key Files

- `docs/implement_rules.md` — full detail on the rules with code examples
- `docs/implement_new_module.md` — step-by-step new module guide (Contacts as reference)
- `docs/getting_started.md` — stack, setup, project layout
- `docs/components/dynamic_relation_lookup.md` — relation dropdown docs
- `config/relation_dropdowns.php` — registered lookup tables
- `database/seeders/PermissionSeeder.php` — all permission definitions
- `app/Observers/AuditableObserver.php` — sets uuid/created_by/updated_by
- `app/Services/Company/CompanyContextService.php` — active company IDs
- `app/Services/FileService.php` — **all** file uploads and deletions go through here
- `app/Http/Controllers/FileController.php` — single unified file-serving endpoint (`GET /files/{uuid}`)
- `config/exportable.php` — export model whitelist (class, permission, fields per module)
- `app/Http/Controllers/ExportController.php` — generic `POST /export` endpoint
- `app/Services/ExportService.php` — XLSX / CSV file generation via PhpSpreadsheet
- `docs/components/export.md` — export component usage guide

---

## Group By — `GroupsQuery` helper + `<x-list :grouped>` mode

Every list controller that supports group-by must use `App\Helpers\GroupsQuery` and the `<x-list :grouped="true">` component. Never hand-build grouping logic in a controller or view.

### Controller pattern (8 lines)

Add this block **after** any active/state filters and **before** `SortsTable::apply()`. For multi-view controllers (list + kanban / tree), gate it with `$view === 'list'`:

```php
use App\Helpers\GroupsQuery;

$groupBy = $request->query('group_by');
if ($groupBy) {                                     // add && $view === 'list' for multi-view controllers
    $fields = SearchFilters::fieldsFor(MyModel::class);
    if (isset($fields[$groupBy])) {
        $records = (clone $query)->with([...])->orderBy('name')->get();
        $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
        return view('module.index', compact('groups', 'view'));   // always pass $view
    }
}
```

### View pattern

Use `@if(isset($groups))` / `@else` inside the list branch. Guard any toolbar count/pagination that references `$paginator` the same way:

```blade
{{-- Toolbar count --}}
@if(isset($groups))
    <span>{{ $groups->sum('count') }} records</span>
@elseif(isset($records))
    <span>{{ $records->firstItem() }}-{{ $records->lastItem() }} / {{ $records->total() }}</span>
@endif

{{-- List area --}}
@if(isset($groups))
<x-list :grouped="true" empty-text="No records found.">
    <x-slot:columns>
        {{-- same <th>/<x-sortable-th> columns as normal list --}}
    </x-slot:columns>

    @forelse($groups as $group)
    <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
        <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
            <td colspan="99" class="px-4 py-2.5">
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                    <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                    </svg>
                    {{ $group['label'] }}
                    <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                </div>
            </td>
        </tr>
        @foreach($group['items'] as $record)
        <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('module.show', $record) }}'">
            {{-- your <td> cells here — NO checkbox column in grouped mode --}}
        </tr>
        @endforeach
    </tbody>
    @empty
    <tbody>
        <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">No records found.</td></tr>
    </tbody>
    @endforelse
</x-list>

@else
<x-list :paginator="$records" ...>
    {{-- normal paginated rows with checkboxes --}}
</x-list>
@endif
```

Key rules:
- `<x-list :grouped="true">` renders a bare `<table>` with no `<tbody>` wrapper — the slot injects multiple `<tbody x-data>` blocks (one per group). This is valid HTML and required for Alpine scope per group.
- Each group header `<tr>` and its record `<tr>` rows must be siblings inside the **same** `<tbody x-data="{ open: ... }">`. Sibling `<tr>` elements cannot share Alpine scope across `<tbody>` boundaries.
- Grouped mode has no checkboxes / export selection. The export `<x-export>` block can stay visible above (it reads selected IDs from the non-grouped path, so it's safe to leave in place).
- `GroupsQuery::apply()` sorts groups alphabetically by label; the `(No Value)` bucket always sorts last.
- The label for a group is resolved from: relation table lookup (for `type: relation`), options array (for select fields), boolean → Yes/No, or raw string. This is handled inside `GroupsQuery` — controllers never need to resolve labels.
- Always pass `$view` in the `compact()` return even when returning groups, so the view's view-switcher buttons render correctly.

---

## No Native Browser Dialogs

Never use `confirm()`, `alert()`, or `prompt()`. These block the thread, cannot be styled, and look inconsistent across browsers.

- **Confirmations** — use an inline Alpine.js toggle: the button flips `confirming = true`, which shows "Are you sure? / Yes / Cancel" inline. Cancel sets it back to `false`.
- **Errors and info** — use the Laravel flash session pattern (`back()->with('error', '...')`) so the message renders as a styled HTML element on the next page load.

```html
<!-- Example inline confirm pattern -->
<div x-data="{ confirming: false }">
    <button type="button" x-show="!confirming" @click="confirming = true" class="... text-red-600">Delete</button>
    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
        <span class="text-xs text-red-600">Are you sure?</span>
        <button type="button" @click="/* do the action */" class="... bg-red-600 text-white">Yes</button>
        <button type="button" @click="confirming = false" class="... text-gray-500">Cancel</button>
    </div>
</div>
```

---

## What Not To Do

- Do not set `uuid`, `created_by`, or `updated_by` in services or controllers — the observer owns those
- Do not put `DB::transaction` in services
- Do not use raw `<select>` for relation fields
- Do not use `@forelse` in list views — `<x-list>` handles the empty state
- Do not create new form layouts — use one of the two approved Odoo styles
- Do not trust posted `company_id` values — always validate against `getActiveCompanyIds()`
- Do not add API controllers for Blade page data — API controllers are only for actual API clients
- Do not touch anything inside `ss_workflow/`
- Do not store files directly with `Storage::put()` or `$file->store()` in controllers — always use `FileService::store()`
- Do not create module-specific file-serving routes (like `contacts.avatar`) — use `route('files.serve', $uuid)`
- Do not call `Storage::delete()` directly to remove user-uploaded files — use `FileService::delete()` or `FileService::deleteByUuid()`
- Do not validate image uploads with bare `'image'` — it includes `image/svg+xml` (stored XSS). Use `mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp` instead
- Do not use bare `'exists:table,id'` for any FK into a company-scoped table — see Rule 11. Form validation must reject cross-company values, not just the controller
- Do not allow role assignment under the `users.write` permission — it's `users.assign_roles` only (Rule 6)
- Do not mutate `key` / `active` / `permissions` of a system role at runtime (`Role::SYSTEM_KEYS`) — those fields belong to the seeder
- Do not bypass `ExportService::safeValue()` / `setValueExplicit(..., TYPE_STRING)` — raw `setValue` or `fputcsv` re-opens CSV/XLSX formula injection
- Do not add a new file-serve helper that only checks the parent-child relation (`$doc->employee_id === $employee->id`) without also gating by the actor's active companies — that's the EmployeeDocument IDOR pattern
- Do not render column identifiers as user-facing labels (`first_name`, `created_at`, `company_id`) — always use a written-out human label or `__('...')` translation (Rule 12)
- Do not leave enum-like fields as `'type' => 'string'` without `options` — that surfaces the raw enum (`'pending'`) in the filter pill and the export cell instead of the human label (`'Pending'`). Declare options in `$searchable` (Rule 13); the filter dropdown and `ExportService` both auto-pick up from there.
- Do not duplicate enum option lists into `config/exportable.php` — the export reads option labels from the model's `$searchable` (single source of truth). Adding `options` only to exportable.php leaves the filter dropdown broken; only `$searchable` updates fix both.
- Do not pass `:can-export` or `:can-delete` manually on `<x-list>` — always pass `:model="ModelClass::class"` and let the component auto-derive permissions from the Gate policy. Manual overrides are only for non-standard edge cases
- Do not write a new bulk action as a one-off per-view feature — add it to `TableList.php` + `list.blade.php` so all lists gain it automatically via the `:model` auto-derive path
