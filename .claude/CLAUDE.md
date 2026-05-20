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

---

## Workflow Module — Additional Context

The Workflow module is the most complex. Key specifics:

**Ticket input types** (11 total): `char`, `int`, `float`, `date`, `datetime`, `boolean`, `select`, `multiselect`, `textarea`, `file`, `label`

**File uploads**: stored on private `local` disk under `workflow/inputs/{ticket_id}/`. Served through `downloadInputFile()` which checks ownership + permission. MIME validated via `finfo` (reads file bytes). Allowed: images (jpg/png/gif/webp), pdf, office docs (doc/docx/xls/xlsx/ppt/pptx), text/csv, odt/ods. Max 10 MB. No zips or executables.

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
