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

## The 7 Non-Negotiable Rules

Violating any of these is a bug, not a style issue.

### 1. List Views — `<x-list>` and `<x-search>` only

Every index/list view must use `<x-list>` for the table and `<x-search>` for filters. Never hand-build a table or custom search form. Use `@foreach` inside `<x-list>`, not `@forelse` — the empty state is handled by the component.

### 2. Chatter-enabled models — include `<x-chatter>` on show page

Any model using the `HasChatter` trait must have `<x-chatter>` on its show page. See `docs/components/chatter.md`.

### 3. Forms must follow Odoo design — two layouts only

- **Inline / border-bottom style** (simple models) — reference: `resources/views/contacts/_form.blade.php`
- **Card / section style** (complex models with grouped fields) — reference: `resources/views/settings/companies/_form.blade.php`

Do not invent new form layouts.

### 4. Relation fields — `<x-relation-dropdown>` only

Never use a raw `<select>` populated from a full table query. All relational fields must use `<x-relation-dropdown>`. The target table must be registered in `config/relation_dropdowns.php`. See `docs/components/dynamic_relation_lookup.md`.

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

### 7. All state-changing operations — `DB::transaction` in the controller

Every controller method that writes to the database must wrap in `DB::transaction`. This includes store, write, archive, unarchive, unlink, addComment, and any custom mutation. A single-call transaction still provides atomicity and rollback.

```php
$record = DB::transaction(fn () => $this->fooService->create($data));
```

### 8. All file uploads — `FileService` only. All file serving — `/files/{uuid}` only.

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

## New Module Checklist

When building a new module, follow `docs/implement_new_module.md` using Contacts as the reference. Quick checklist:

- [ ] Migration: `id`, `uuid`, `company_id` (if applicable), business fields, `active`, `created_by`, `updated_by`, timestamps
- [ ] Model: fillable, casts, relationships, `scopeActive`, `scopeForCompanies` (if company-scoped)
- [ ] Register model with `AuditableObserver` in `AppServiceProvider`
- [ ] Permissions seeded in `PermissionSeeder` and assigned in `RoleSeeder`
- [ ] Policy: `viewAny`, `view`, `create`, `update`, `delete`, `comment`
- [ ] Form requests: authorize + validate; validate company-scoped IDs against `getActiveCompanyIds()`
- [ ] Service: create, update, archive, unarchive — business logic + chatter, no transactions
- [ ] Controller: follows naming above, wraps in `DB::transaction`, applies company gate
- [ ] Routes: permission middleware on every route, fixed sub-routes before `/{model}` to avoid binding conflicts
- [ ] Views: `<x-list>` + `<x-search>` on index, `<x-chatter>` on show, Odoo form style, `<x-relation-dropdown>` for relations
- [ ] Navigation: update `resources/views/components/navbar.blade.php`
- [ ] Register target tables in `config/relation_dropdowns.php` for any relation dropdown
- [ ] make sure you added $sortable, $searchable, $chatterTracked, $fillable and make sure they are linked and used

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

- `docs/implement_rules.md` — full detail on the 7 rules with code examples
- `docs/implement_new_module.md` — step-by-step new module guide (Contacts as reference)
- `docs/getting_started.md` — stack, setup, project layout
- `docs/components/dynamic_relation_lookup.md` — relation dropdown docs
- `config/relation_dropdowns.php` — registered lookup tables
- `database/seeders/PermissionSeeder.php` — all permission definitions
- `app/Observers/AuditableObserver.php` — sets uuid/created_by/updated_by
- `app/Services/Company/CompanyContextService.php` — active company IDs
- `app/Services/FileService.php` — **all** file uploads and deletions go through here
- `app/Http/Controllers/FileController.php` — single unified file-serving endpoint (`GET /files/{uuid}`)

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
