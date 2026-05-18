# Table Sorting

## Purpose

The table sorting component provides reusable click-to-sort headers for module list views. It keeps sorting behavior consistent without duplicating URL logic, arrow icons, or sortable column allowlists in each Blade table.

Current examples:

- Contacts list in `resources/views/contacts/index.blade.php`
- Contact tags list in `resources/views/contacts/tags/index.blade.php`
- Companies list in `resources/views/settings/companies/index.blade.php`
- Users list in `resources/views/settings/users/index.blade.php`
- Roles list in `resources/views/settings/roles/index.blade.php`
- Permissions list in `resources/views/settings/permissions/index.blade.php`

The component supports:

- Ascending and descending sorting
- Active column arrow state
- Preserving existing query parameters such as `search`, `filter`, `type`, and `view`
- Resetting pagination when sort changes
- Server-side sortable column allowlists
- Count-column sorting when the query uses `withCount`

## Files

- Blade component view: `resources/views/components/sortable-th.blade.php`
- Sorting helper: `app/Helpers/SortsTable.php`
- Sortable column definitions: model-level `$sortable` arrays

## Query Parameters

Sortable list views use:

```text
sort        Sort key from the model's $sortable array.
direction   asc or desc.
```

Example:

```http
GET /contacts?sort=email&direction=desc
```

The sort key is not used directly as a database column. The controller passes the query to `SortsTable`, and `SortsTable` resolves the key through the model's `$sortable` allowlist.

## Model Configuration

Every model used by a sortable table should define a public `$sortable` array.

Example:

```php
class Contact extends Model
{
    public array $sortable = [
        'name' => 'name',
        'email' => 'email',
        'phone' => 'phone',
        'city' => 'city',
        'country' => 'country',
        'company' => 'company_name',
    ];
}
```

The array key is the public sort key used in URLs and Blade. The value is the actual database column or selected/count alias passed to `orderBy`.

For count columns, make sure the controller query includes the count before sorting:

```php
$query = Role::withCount(['permissions', 'users']);
```

Then the model can expose:

```php
public array $sortable = [
    'permissions' => 'permissions_count',
    'users' => 'users_count',
];
```

## Controller Usage

For normal paginated Eloquent list queries:

```php
use App\Helpers\SortsTable;

$query = Contact::query();

SortsTable::apply($query, $request);

$contacts = $query->paginate(24)->withQueryString();
```

`SortsTable::apply()` reads sortable columns from the query model. If the requested sort key is missing or invalid, it falls back to the first key in the model's `$sortable` array.

For grouped lists, resolve the safe sort first and apply it manually where needed:

```php
$sort = SortsTable::resolve(Permission::class, $request);

$permissions = Permission::orderBy('module')
    ->orderBy($sort['column'], $sort['direction'])
    ->get()
    ->groupBy('module');
```

## Blade Usage

Use the component inside table headers:

```blade
<x-sortable-th column="name" label="Name" :default="true" />
<x-sortable-th column="email" label="Email" />
```

Optional classes can match local table spacing:

```blade
<x-sortable-th column="name" label="Company" class="px-6" :default="true" />
<x-sortable-th column="users" label="Users" />
```

For right-aligned sortable columns:

```blade
<x-sortable-th column="total" label="Total" align="right" />
```

## Component Props

- `column`: sort key from the model's `$sortable` array.
- `label`: visible header label.
- `default`: marks the default sorted column when the URL has no `sort` parameter.
- `align`: `left` or `right`.
- `class`: optional classes merged onto the `<th>`.

## Behavior

When the current column is active:

- Ascending sort shows an up arrow.
- Descending sort shows a down arrow.
- Clicking the same header toggles direction.

When the current column is not active:

- The arrow is hidden until hover.
- Clicking the header starts ascending sort.

The component preserves existing query parameters and removes `page` so changing sort starts from page one.

## Implementation Steps For A New Module

1. Add a public `$sortable` array to the model.
2. Put the default column first in the array.
3. In the controller, call `SortsTable::apply($query, $request)` before pagination.
4. Replace sortable table headers with `<x-sortable-th>`.
5. Add `:default="true"` to the header matching the first `$sortable` key.
6. For count columns, call `withCount()` before `SortsTable::apply()`.

## Notes

Keep sortable column definitions on the model, not in controllers. This keeps the list view contract near the model fields it exposes and prevents individual controllers from duplicating hardcoded sort allowlists.

Do not pass request `sort` values directly to `orderBy`. Always resolve them through `SortsTable` and the model `$sortable` array.
