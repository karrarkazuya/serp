# Search Component

## Purpose

The search component provides an Odoo-style search bar for module list views. It combines free-text search, reusable custom filters, filter chips, quick filters, group-by shortcuts, and relation-aware lookup values in one Blade component.

Use it instead of hand-built search inputs in list pages.

## Files

- Blade component view: `resources/views/components/search.blade.php`
- Blade component class: `app/View/Components/Search.php`
- Query helper: `app/Helpers/SearchFilters.php`
- Model configuration: model-level `$searchable` arrays

## Query Parameters

The component submits regular GET parameters:

```text
search      Free-text search across searchable string fields.
filters     JSON array of advanced filter rules.
```

Example:

```http
GET /contacts?search=ani&filters=[{"field":"name","operator":"contains","value":"Ani"}]
```

Existing list parameters such as `sort`, `direction`, `view`, `type`, and `filter` are preserved unless explicitly removed by the page.

## Model Configuration

Every model used by the search component should define a public `$searchable` array.

```php
public array $searchable = [
    'name' => [
        'label' => 'Name',
        'column' => 'name',
        'type' => 'string',
    ],
    'created_by' => [
        'label' => 'Created by',
        'column' => 'created_by',
        'type' => 'relation',
        'relation' => [
            'table' => 'users',
            'field' => 'name',
        ],
    ],
    'created_at' => [
        'label' => 'Created on',
        'column' => 'created_at',
        'type' => 'datetime',
    ],
];
```

Supported field types:

- `string`, `text`, `email`
- `integer`, `decimal`, `number`
- `date`, `datetime`
- `boolean`
- `relation`

The array key is the public field key used in the filter JSON. The `column` value is the database column used by the query helper.

## Operators

Operators are type-aware:

- String fields: `contains`, `does not contain`, `=`, `!=`, `is in`, `is not in`, `is set`, `is not set`, `starts with`, `ends with`
- Numeric fields: `=`, `!=`, `>`, `<`, `>=`, `<=`, `between`, `is in`, `is not in`, `is set`, `is not set`
- Date and datetime fields: `=`, `!=`, `>`, `<`, `>=`, `<=`, `between`, `is set`, `is not set`
- Boolean fields: `=`, `!=`, `is set`, `is not set`
- Relation fields: `=`, `!=`, `is in`, `is not in`, `is set`, `is not set`

Do not pass request values directly to `where` or `orderBy`. Always route custom filters through `SearchFilters`, which resolves fields and operators from the model allowlist.

For relation fields, `=` and `!=` select one related record. `is in` and `is not in` support selecting multiple related records and submit an array of IDs.

## Controller Usage

Use the helper before sorting and pagination:

```php
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;

$query = Contact::query()->with(['company', 'creator']);

SearchFilters::apply($query, $request);
SortsTable::apply($query, $request);

$contacts = $query->paginate(24)->withQueryString();
```

If a list has existing status filters such as `filter=archived`, keep those separately. Advanced filters only cover the model `$searchable` fields.

## Blade Usage

Basic usage:

```blade
<x-search
    :model="\App\Models\Contacts\Contact::class"
    :action="route('contacts.index')"
/>
```

Preserve page-specific parameters:

```blade
<x-search
    :model="\App\Models\Contacts\Contact::class"
    :action="route('contacts.index')"
    :preserve="['view' => $view]"
/>
```

Quick filters and group-by shortcuts:

```blade
<x-search
    :model="\App\Models\Contacts\Contact::class"
    :action="route('contacts.index')"
    :quick-filters="[
        ['label' => 'Individuals', 'url' => route('contacts.index', array_merge(request()->except('page'), ['type' => 'individual']))],
        ['label' => 'Companies', 'url' => route('contacts.index', array_merge(request()->except('page'), ['type' => 'company']))],
        ['label' => 'Archived', 'url' => route('contacts.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
    ]"
    :group-by="[
        ['label' => 'Company', 'url' => route('contacts.index', array_merge(request()->except('page'), ['group_by' => 'company_id']))],
        ['label' => 'Country', 'url' => route('contacts.index', array_merge(request()->except('page'), ['group_by' => 'country']))],
    ]"
/>
```

When a group is selected, the component shows a `Group By` chip in the search box and submits `group_by=<key>`. Removing the chip clears only the group-by parameter while preserving the free-text search and active filter chips.

Quick filters can define `clear_params` when removing a chip needs to set a replacement query value instead of only deleting the active parameter. This is useful for default filters such as `Active`, where removing the chip should set `filter=all`.

## AND And OR Filters

Separate filter chips are applied as `AND` conditions.

Inside the custom filter dialog, `New Rule` adds another rule to the same filter chip. Rules inside that one chip are applied as `OR` when the dialog says `Match any`, matching Odoo's custom filter behavior.

Example:

```json
[
  {
    "match": "any",
    "rules": [
      {"field": "name", "operator": "contains", "value": "Anita"},
      {"field": "name", "operator": "contains", "value": "Audrey"}
    ]
  }
]
```

That produces one chip like:

```text
Name contains Anita or Name contains Audrey
```

## Relation Fields

Relation filters use the dynamic relation lookup endpoint to fetch display values. The target table must be registered in:

```text
config/relation_dropdowns.php
```

Example:

```php
'users' => [
    'read' => 'users.read',
    'write' => 'users.write',
    'create_permission' => 'users.create',
    'route' => 'settings.users.index',
    'create' => 'settings.users.create',
    'color' => null,
    'fields' => ['name', 'email'],
],
```

Then expose the relation in `$searchable`:

```php
'created_by' => [
    'label' => 'Created by',
    'column' => 'created_by',
    'type' => 'relation',
    'relation' => ['table' => 'users', 'field' => 'name'],
],
```

## Implementation Steps For A New Module

1. Add a `$searchable` array to the model.
2. Add relation target tables to `config/relation_dropdowns.php` when needed.
3. Call `SearchFilters::apply($query, $request)` in the list controller before sorting and pagination.
4. Replace the list page's hand-built search input with `<x-search>`.
5. Keep module-specific quick filters as `quick-filters`.
6. Verify search, custom filters, pagination, and sorting preserve each other through query strings.
