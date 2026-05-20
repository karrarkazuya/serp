Scaffold a complete new module named **$ARGUMENTS** following the project conventions defined in `docs/implement_new_module.md`. Read that file first. Use the Contacts module as the reference pattern.

The module name provided is: **$ARGUMENTS**

Derive the following from it before starting:
- `ModuleName` — PascalCase singular (e.g. `Invoice`)
- `module_name` — snake_case singular (e.g. `invoice`)
- `module-name` — kebab-case singular (e.g. `invoice`)
- `module_names` — snake_case plural for table name (e.g. `invoices`)
- `module.permission` — permission prefix (e.g. `invoices`)
- `route.prefix` — route name prefix (e.g. `invoices`)

Ask the following before generating anything:
1. Does this module have a `company_id` (is it company-scoped)? (yes/no)
2. Does it use the chatter (`HasChatter` trait)? (yes/no)
3. What are the main fields? (list name + type, e.g. "title:string, status:string, amount:decimal")
4. Does it need archive/unarchive? (yes/no)
5. Does it need a configuration sub-section? (yes/no)

Once the user answers, generate all files in this order:

## Files to create

1. **Migration** — `database/migrations/YYYY_MM_DD_HHMMSS_create_{table}_table.php`
   - Include: id, uuid, company_id (if company-scoped), all provided fields, active, created_by, updated_by, timestamps
   - Use `nullOnDelete()` for foreign keys

2. **Model** — `app/Models/{ModuleName}/{ModuleName}.php`
   - fillable, casts, relationships, scopeActive, scopeForCompanies (if company-scoped)
   - HasChatter trait if requested
   - chatterTracked array for fields that should be logged on change

3. **Policy** — `app/Policies/{ModuleName}Policy.php`
   - viewAny, view, create, update, delete, comment

4. **Form Requests** — `app/Http/Requests/{ModuleName}/Store{ModuleName}Request.php` and `Update{ModuleName}Request.php`
   - authorize() checks permission
   - rules() validates all fields; company-scoped IDs validated against getActiveCompanyIds()

5. **Service** — `app/Services/{ModuleName}/{ModuleName}Service.php`
   - create, update, archive/unarchive (if requested)
   - Chatter logging on each operation
   - No DB::transaction — that belongs in the controller

6. **Controller** — `app/Http/Controllers/{ModuleName}/{ModuleName}Controller.php`
   - Methods: read, show, create, store, edit, write, unlink
   - Add archive/unarchive if requested
   - Add addComment if chatter enabled
   - Every method: company gate if company-scoped, DB::transaction on writes, $this->authorize()

7. **Routes** — output the route block to add to `routes/web.php`
   - Every route has explicit permission middleware
   - Fixed sub-routes before /{model} to avoid binding conflicts

8. **Views**:
   - `resources/views/{module}/index.blade.php` — uses `<x-list>` and `<x-search>`
   - `resources/views/{module}/show.blade.php` — includes `<x-chatter>` if enabled
   - `resources/views/{module}/create.blade.php` — uses `_form` partial
   - `resources/views/{module}/edit.blade.php` — uses `_form` partial
   - `resources/views/{module}/_form.blade.php` — Odoo inline style for simple, card style for complex

9. **Permissions** — output the entries to add to `database/seeders/PermissionSeeder.php`

10. **AppServiceProvider registration** — output the observer registration line for `AppServiceProvider`

11. **Navbar** — output the navigation entry to add to `resources/views/components/navbar.blade.php`

12. **Relation dropdown config** — if any relation fields exist, output the config entry for `config/relation_dropdowns.php`

## After generating

Print this verification checklist the user should run:
```bash
php -l app/Http/Controllers/{ModuleName}/{ModuleName}Controller.php
php artisan route:list --path={module}
php artisan view:cache
php artisan view:clear
```

Remind the user to:
- Run `php artisan db:seed --class=PermissionSeeder` to seed permissions
- Register the policy in `AuthServiceProvider` if auto-discovery does not pick it up
