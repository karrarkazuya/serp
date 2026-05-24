Audit the entire Laravel codebase in this project against the 12 non-negotiable rules defined in `.claude/CLAUDE.md` (read it first to refresh the exact rules, then inspect the codebase).

Check every violation — do not stop at the first one found per rule. Report findings grouped by rule number, each with the file path and line number. If a rule has no violations, say "✓ No violations".

## What to check

### Rule 1 — List views must use `<x-list>` and `<x-search>`
- Find every `index.blade.php` under `resources/views/`
- Flag any that use `<table>` or `<div>` directly instead of `<x-list>`
- Flag any that use `@forelse` instead of `@foreach`
- Flag any custom search inputs instead of `<x-search>`

### Rule 2 — Chatter-enabled models must have `<x-chatter>` on their show page
- Find all models that use `HasChatter` trait
- For each, check that the corresponding show view includes `<x-chatter>`

### Rule 3 — Forms must use one of the two approved Odoo layouts
- Check create/edit/form views for custom layouts not matching either approved style
- Reference: `resources/views/contacts/_form.blade.php` (inline) and `resources/views/settings/companies/_form.blade.php` (card)

### Rule 4 — Relation fields must use `<x-relation-dropdown>`
- Find any `<select>` tags in Blade views that are populated via `@foreach` over a full table
- These should be `<x-relation-dropdown>` instead

### Rule 5 — Tables with `company_id` must apply company filtering in every method
- Find all models whose migration has a `company_id` column
- For each, find its controller(s)
- Check that every method (read, show, create, store, edit, write, archive, unarchive, unlink, and custom methods) calls `getActiveCompanyIds()` and filters/gates by it
- A method that mutates or reads a company-scoped record without the gate is a security bug — flag it clearly

### Rule 6 — Every route must have `permission:` middleware
- Read `routes/web.php` (and `routes/api.php` if it has protected routes)
- Find any route inside an authenticated group that is missing a `->middleware('permission:...')` call
- Do not flag public/guest routes

### Rule 7 — All state-changing controller methods must use `DB::transaction`
- Find all controller methods named: store, write, archive, unarchive, unlink, addComment, and any custom methods that call a service or execute DB writes
- Flag any that do not wrap their logic in `DB::transaction`

### Rule 8 — Soft deletes on every application table + model
- Find every migration that creates an application table (excluding the documented Laravel-framework / pure-pivot exclusions in CLAUDE.md)
- Flag any that omit `$table->softDeletes()`
- Cross-check: every corresponding Eloquent model must `use SoftDeletes`

### Rule 9 — Export pipeline + `ExportService::safeValue()`
- For every model declared in `config/exportable.php`, confirm the matching index view has `<x-export>` + `<x-list :selectable>` + the row checkbox pattern
- Confirm a matching `export()` method on the policy and `module.export` permission in the seeder
- Flag any direct `$sheet->setValue()` / `fputcsv($handle, [..raw..])` outside `ExportService::safeValue()` / `setValueExplicit(..., TYPE_STRING)`

### Rule 10 — Files through `FileService` + `/files/{uuid}` + safe image validation
- Flag any controller call to `Storage::put()` / `$file->store()` / `Storage::delete()` for user uploads — must go through `FileService`
- Flag any module-specific file-serving route (e.g. a fresh `/foo/avatar/{uuid}`) — must use `route('files.serve', $uuid)`
- Flag any form-request validation that uses bare `'image'` for an upload field — must use `mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp` (SVG is stored-XSS via inline rendering)
- Flag any new file-serve helper that gates by parent-child relation only (e.g. `$doc->employee_id === $employee->id`) without also checking `$companyContext->getActiveCompanyIds()` — that's the EmployeeDocument IDOR pattern

### Rule 11 — Every form-request FK to a company-scoped table is company-scoped
- For every Form Request in `app/Http/Requests/**`, list each rule that uses `'exists:<table>,id'` or `Rule::exists('<table>', 'id')`
- For each, check whether `<table>` carries a `company_id` column (grep the migrations)
- If yes, the rule must restrict by `company_id` via `Rule::exists(...)->where(...)->whereIn('company_id', $active)`. Bare `exists:` on a company-scoped table is a cross-tenant FK injection bug — flag with file path + the offending FK name
- Inventory module: prefer the shared `App\Http\Requests\Inventory\Concerns\InventoryFkRules` trait

### Rule 12 — No raw column identifiers as user-facing labels
- Grep Blade views (`resources/views/**/*.blade.php`) for label text that matches a column-style identifier: snake_case strings rendered as `<label>`, `<th>`, `<option>`, search-filter chip text, group-by chip text, table-list headers
- For every model with `$searchable` / `$sortable` / `$chatterTracked`, confirm each entry has a `'label'` key (or is a string label, not just a column key)
- Flag any rendered text that prints a column name verbatim (e.g. `first_name`, `created_at`, `company_id`) instead of a human-readable label or `__('module.key')` translation

## Also check
- Controller method names: flag any that use non-standard names (e.g. `index`, `update`, `destroy`, `delete`, `save`) instead of the required names (`read`, `write`, `unlink`, etc.)
- Services: flag any service method that calls `DB::transaction` (transactions belong in controllers)
- Services: flag any service method that sets `uuid`, `created_by`, or `updated_by` (the observer owns those)

## Output format
Group findings by rule. For each violation include:
- File path (relative to project root)
- Line number if applicable
- One sentence describing the violation

End with a summary: X rules fully compliant, Y rules have violations (N total).
