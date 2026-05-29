# `<x-import>` — Bulk import

`<x-import>` is the user-facing trigger for the generic import system. **In normal use you do not place it manually** — `<x-list :model="...">` auto-renders it when the actor has `Gate::allows('import', $model)` and the model has a `config/importable.php` entry. This page documents how the system works so you can add new modules to it (and so you understand the surface a security review will need to audit).

For the high-level rule and security contract, see [.claude/CLAUDE.md](.claude/CLAUDE.md) "Rule 14".

## At a glance

- One Alpine modal — download sample template (XLSX / CSV) + upload form.
- One generic POST endpoint — `POST /import`. No per-module upload controllers.
- One generic template endpoint — `GET /import/{modelKey}/template?format=xlsx|csv`.
- One whitelist — `config/importable.php`. A model that isn't listed can't be imported, period.
- One service — `App\Services\ImportService`. Parses XLSX / CSV, coerces types, validates each row against the same `FormRequest::rules()` that the controller's `store()` uses, calls the configured service create method.
- One transaction — the whole batch runs inside `DB::transaction`. If any row throws, every prior row in the batch rolls back.

## Files

| Path | Role |
|---|---|
| `config/importable.php` | Module → import-config map. |
| `app/Services/ImportService.php` | Parsing, type coercion, validation, service dispatch. |
| `app/Http/Controllers/ImportController.php` | `POST /import` + `GET /import/{modelKey}/template`. |
| `app/View/Components/Import.php` + `resources/views/components/import.blade.php` | The modal + trigger button. |
| `app/View/Components/TableList.php` + `resources/views/components/list.blade.php` | Auto-renders `<x-import>` when `:model` is set + `Gate::allows('import', $model)` passes. |

## Auto-rendering — how `<x-list>` decides

`TableList::__construct` resolves three permission flags from the `:model` prop:

```php
$this->canExport      = $canExport ?? $this->gate('export');
$this->canDelete      = $canDelete ?? $this->gate('delete');
$this->importModelKey = $this->resolveImportModelKey();          // finds the config/importable.php key whose `class` matches
$this->canImport      = ($canImport ?? $this->gate('import')) && $this->importModelKey !== null;
```

`list.blade.php` then renders:

```blade
@if($canImport)
<div class="flex items-center justify-end gap-2 px-3 py-1.5 bg-gray-50 border-b border-gray-200 shrink-0">
    <x-import :model-key="$importModelKey" :import-url="route('import')" />
</div>
@endif
```

The toolbar appears in all three list modes (grouped, selectable, non-selectable). In selectable mode it hides while items are selected to keep the bulk-action bar uncluttered.

**You never manually drop `<x-import>` into a view.** If you find yourself doing that, the model is either missing from `config/importable.php` or the actor doesn't pass the `import` Gate — both of which are intentional.

## `config/importable.php` schema

| Key | Type | Required | Notes |
|---|---|---|---|
| `class` | string | yes | FQ Eloquent model. `<x-list>` matches `:model` against this. |
| `permission` | string | yes | Permission key checked in the controller + in `Policy::import()`. |
| `company_scoped` | bool | yes | When true, missing `company_id` defaults to the sole active company; multi-company actors must supply it. |
| `request` | string | yes | Must extend `Illuminate\Foundation\Http\FormRequest`. Its `rules()` is the per-row validator. **`authorize()` is intentionally bypassed.** |
| `service` | string | yes | Service class instantiated via the container. |
| `service_method` | string | optional (default `create`) | Service method called per row. Must accept a validated-data array. |
| `filename` | string | optional | Sample template base name (no extension). |
| `fields[]` | array | yes | Allowed columns + types. Order = template column order. |

Each entry in `fields[]`:

| Key | Type | Notes |
|---|---|---|
| `key` | string | Field name as passed to validation + service. |
| `label` | string | Header in the sample template. Importer also matches by label, so users can keep human headers. |
| `type` | string | `string` \| `integer` \| `decimal` \| `boolean` \| `date` \| `datetime` \| `email` \| `url` \| `enum` \| `array` \| `relation`. |
| `required` | bool | Adds `" *"` to the template header. Actual validation comes from the FormRequest. |
| `options` | array | For `enum`. Case-insensitive match; unknown values fall through to the FormRequest. |
| `separator` | string | For `array`. Default `;`. |
| `relation` | array | For `relation`. `['table' => 'companies', 'lookup' => ['id', 'name']]`. Auto-filters by `company_id` when present. |
| `default` | mixed | Fallback when the column is blank. |
| `example` | string | Sample value rendered in the template. |

## Important constraint — only `$fillable` fields, no pivot / has-many

The importer calls the configured `service_method($validated)`. Most module services have a `create` method that boils down to `Model::create($data)`, which only persists `$fillable` columns. Pivot / has-many / related-table data (tags, phone numbers, related contacts, line items, etc.) are typically attached by the **controller**, not the service — so listing those fields in `config/importable.php` would silently drop them on import.

**Two correct approaches when you need pivot / has-many data on import:**

1. **Don't list them in `importable.php`.** Users can import the parent record now and edit it to add pivot data afterwards. This is the path of least surprise and what the Contacts importer does (phones are deliberately omitted).
2. **Add a dedicated `createFromImport(array $data)` method on the service** that runs the full controller-equivalent flow (extract pivot keys, create parent, attach pivots, etc.), and reference it via `service_method => 'createFromImport'`. The existing controller `store()` can remain untouched; `createFromImport` is a wider entrypoint for the importer alone.

If a module's importable config grows beyond the model's `$fillable`, expect silent drops and add the wider service method.

## How each row flows through the system

1. Controller authorises (`hasPermission($config['permission'])`) — same key the route's Gate check uses.
2. `ImportService::parse()` reads the file with `setReadDataOnly(true)` (no formula evaluation) into a list of header → value arrays.
3. Controller opens `DB::transaction`.
4. `ImportService::processRows()` iterates:
    a. Map file headers (label OR key, case-insensitive, with trailing `*` stripped) to the canonical field key.
    b. Per-field type coercion (see types reference).
    c. Validate the coerced row against `$this->formRequestRules($config['request'])`. **We instantiate the FormRequest directly with `new $requestClass()` instead of `Container::make()`** to skip Laravel's `afterResolving` callback chain that would auto-call `authorize()` against a null user.
    d. Call `$service->{$method}($validated)`.
5. On any throw — row-specific message bubbles up, the transaction rolls back, nothing is persisted.
6. Controller flashes `success` ("Imported N records") or `error` ("Row X: …") back to the original list page via the optional `redirect` form field (sanitised to internal paths only).

## Security contract — non-negotiable

Identical to Rule 14 in CLAUDE.md. Restated here for the component-doc audience:

1. **Gate is checked at the controller**, not just the view. Hiding the button is UX-only.
2. **Validation reuses the FormRequest's `rules()`** — including Rule 11 cross-company FK rules. Bypassing this is a tenant-isolation bug.
3. **Atomic batch** — `DB::transaction` wraps `processRows()`. Per-row commits are forbidden.
4. **No formula evaluation** — `setReadDataOnly(true)` + never `getCalculatedValue()`.
5. **CWE-1236 escape stripped on import** — leading `'+` / `'=` / `'-` / `'@` (single-quote + formula trigger) is collapsed back to the literal so exports round-trip cleanly.
6. **Relation lookups filter by `company_id`** when the target table has it.
7. **MIME + size whitelist** — `mimes:csv,txt,xlsx,xls`, 10 MB cap.
8. **Per-row cap** — `ImportService::MAX_ROWS = 5000`.
9. **Open-redirect defence** — `redirect` form value must start with `/` and not `//`.
10. **Throttle** — `POST /import` capped at 10/min/user; `GET /import/{modelKey}/template` at 30/min/user.

## Adding a new module — the four steps

```php
// 1. Policy
public function import(User $user): bool
{
    return $user->hasPermission('invoices.import');
}

// 2. Seed
['name' => 'Import Invoices', 'key' => 'invoices.import', 'module' => 'invoices',
 'description' => 'Bulk-import invoices from XLSX or CSV. Each row is validated and created through the same flow as a manual New Invoice.'],

// 3. Register
'invoices' => [
    'class'          => Invoice::class,
    'permission'     => 'invoices.import',
    'company_scoped' => true,
    'request'        => StoreInvoiceRequest::class,
    'service'        => InvoiceService::class,
    'service_method' => 'create',
    'fields' => [
        ['key' => 'number', 'label' => 'Number', 'type' => 'string', 'required' => true],
        ['key' => 'partner_id', 'label' => 'Customer', 'type' => 'relation',
         'relation' => ['table' => 'contacts', 'lookup' => ['id', 'name']]],
        ['key' => 'date', 'label' => 'Date', 'type' => 'date', 'required' => true],
        ['key' => 'amount_total', 'label' => 'Total', 'type' => 'decimal'],
        ['key' => 'state', 'label' => 'State', 'type' => 'enum',
         'options' => ['draft','posted','cancelled'], 'default' => 'draft'],
    ],
],

// 4. Verify
//    php artisan route:list --json | jq '.[] | select(.name=="import" or .name=="import.template")'
//    Load the invoices index as a user with invoices.import — Import button appears.
//    Download template, fill 2 rows, upload — both rows persisted via InvoiceService::create()
//    inside a single DB::transaction.
```

No view edits — the `<x-list :model="Invoice::class">` already in your index renders the Import trigger automatically.

## Translations

The component renders `__('common.import_*')` strings. EN / AR exist for:

- `import`, `importing`, `import_data`
- `import_step1`, `import_step1_desc`
- `import_step2`, `import_step2_desc`
- `import_choose_file`
- `import_max_rows`
- `import_atomic_title`, `import_atomic_desc`

If you add a new language file, mirror these keys.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Import button doesn't appear on a list that should have it. | Check `Gate::allows('import', Model::class)` for the actor. Then check `config/importable.php` has an entry whose `class` matches the list's `:model` exactly. |
| Template downloads but headers look wrong. | The template uses field `label` strings. Fix `label` in `config/importable.php`; the parser will still accept either label or key on import. |
| "Row N: The X field is required" with no obvious cause. | The FormRequest is doing its job. The most common case is `company_id` blank for a multi-company user — they must provide it explicitly. |
| Import succeeds but no chatter entry appears. | Confirm your `service_method` actually calls `ChatterService::logCreated()`. The importer doesn't add chatter — it relies on the service doing it. |
| File rejected with "Could not read the file". | Almost always a corrupted XLSX or wrong extension. The MIME validator runs first; if that passes and the read still fails, the file is malformed. |
