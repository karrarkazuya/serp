Audit the entire Laravel codebase in this project against the 7 non-negotiable rules defined in `docs/implement_rules.md`. Read that file first to refresh the exact rules, then inspect the codebase.

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
