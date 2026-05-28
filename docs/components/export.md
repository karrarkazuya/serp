# Export System

The export system has four cooperating pieces:

| Piece | File |
|---|---|
| `<x-export>` modal | `app/View/Components/Export.php` + `resources/views/components/export.blade.php` |
| `<x-list :selectable>` | `app/View/Components/TableList.php` + `resources/views/components/list.blade.php` |
| Model whitelist | `config/exportable.php` |
| Endpoint | `app/Http/Controllers/ExportController.php` + `app/Services/ExportService.php` |

---

## `<x-export>` component

### Props

| Prop | Type | Default | Description |
|---|---|---|---|
| `fields` | `array` | `[]` | Available export columns. Each entry: `['key' => string, 'label' => string, 'column' => string]`. |
| `preset` | `array` | `[]` | Pre-selected columns for "Fields to export". Defaults to first 8 from `fields`. |
| `export-url` | `string` | `''` | POST URL — always `route('export')`. |
| `model-key` | `string` | `''` | Key matching an entry in `config/exportable.php` (e.g. `'contacts'`). |

### Trigger

The modal is opened by dispatching the `export:open` Alpine window event:

```js
$dispatch('export:open', {
    mode: 'selected',       // 'selected' | 'all'
    ids: [1, 2, 3],         // IDs when mode === 'selected' and !selectAllPages
    selectAllPages: false,  // true = export all records ignoring IDs
})
```

This is done automatically by `<x-list :selectable>` when the user clicks "Export" or "Export All" from the Actions dropdown.

### Export request (POST /export)

Fields sent to the server:

| Field | Description |
|---|---|
| `model` | Model key (e.g. `contacts`) |
| `format` | `xlsx` or `csv` |
| `import_compatible` | `1` to use field keys as headers instead of labels |
| `select_all` | `1` to export all records matching current filters |
| `query_string` | URL-encoded current query string (used when `select_all=1`) |
| `ids[]` | Selected record IDs (used when `select_all=0`) |
| `fields[]` | Selected column keys |

---

## `<x-list :selectable>` changes

When `:selectable="true"` and `:total-count="..."` are set, the list:

1. Wraps in an Alpine `x-data` component managing `selected[]` and `selectAllPages`.
2. Renders a checkbox `<th>` as the first header column (select/deselect all on page).
3. Shows a purple selection action bar above the table when `selected.length > 0`.
4. Provides "Actions → Export / Export All" that dispatches `export:open`.
5. Provides "Actions → Delete" (when `:bulk-delete-url` is set) with an inline red confirm bar.

### Bulk delete

Pass `:bulk-delete-url="route('module.bulk-delete')"` to enable the Delete action. The component submits a hidden `DELETE` form to that URL with:

| Field | Value |
|---|---|
| `select_all` | `1` if "Select all N" was used, otherwise `0` |
| `query_string` | URL-encoded current query string (used when `select_all=1`) |
| `ids[]` | Selected IDs (used when `select_all=0`) |

The controller receives those fields and must iterate through the IDs, attempting to delete each one through its normal unlink logic (authorization + business rule checks). Items that cannot be deleted should be skipped and reported in a flash message.

```blade
<x-list :paginator="$records"
        :selectable="true"
        :total-count="$records->total()"
        :can-export="auth()->user()->can('export', Model::class)"
        :can-delete="auth()->user()->can('delete', Model::class)"
        :bulk-delete-url="route('module.bulk-delete')">
```

The Actions dropdown renders when at least one of `:can-export` or `:can-delete` is true. `:can-delete` gates the Delete menu item; `:bulk-delete-url` is the POST target — both must be provided for the Delete action to appear. The route still carries its own `permission:module.unlink` middleware as a second layer.

**Row checkbox — required in parent view:**
```blade
<td class="w-10 px-3 py-2 text-center" @click.stop>
    <input type="checkbox"
           class="list-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
           x-model="selected"
           value="{{ $record->id }}">
</td>
```

- Must be the **first `<td>`** in every row.
- Class `list-checkbox` is required for the select-all header checkbox to find the page checkboxes.
- `@click.stop` prevents the row `onclick` navigation from firing on checkbox click.
- `x-model="selected"` shares state with the parent `x-data` (the list component wrapper).

---

## `config/exportable.php`

Each entry defines:

```php
'module_key' => [
    'class'          => \App\Models\Foo\Bar::class,   // Eloquent model
    'permission'     => 'module.export',               // hasPermission() key
    'company_scoped' => true,                          // adds whereIn('company_id', ...)
    'filename'       => 'bars',                        // download filename (no ext)
    'fields'         => [
        ['key' => 'id',    'label' => 'ID',    'column' => 'id'],
        ['key' => 'name',  'label' => 'Name',  'column' => 'name'],
        // ...
    ],
],
```

`key` is what's sent from the browser and validated server-side. `column` is the actual DB column selected. Only columns listed here can ever appear in an export.

---

## Adding export to a new module

1. **`config/exportable.php`** — add entry with class, permission, company_scoped, fields.

2. **Permission** — add `module.export` to `CoreSeeder::seedPermissions()`:
   ```php
   ['name' => 'Export Foos', 'key' => 'module.export', 'module' => 'module', 'description' => '...'],
   ```

3. **Policy** — add `export()` method:
   ```php
   public function export(User $user): bool
   {
       return $user->hasPermission('module.export');
   }
   ```

4. **Index Blade view** — place `<x-export>` before `<x-list>`, enable `:selectable`, add checkbox `<td>` to each row:
   ```blade
   @can('export', \App\Models\Foo\Bar::class)
   <x-export
       :fields="config('exportable.module_key.fields', [])"
       :export-url="route('export')"
       model-key="module_key"
   />
   @endcan

   <x-list :paginator="$records"
           :selectable="true"
           :total-count="$records->total()">
       <x-slot:columns>
           {{-- <th> columns --}}
       </x-slot:columns>

       @foreach($records as $record)
       <tr ... onclick="...">
           <td class="w-10 px-3 py-2 text-center" @click.stop>
               <input type="checkbox" class="list-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
                      x-model="selected" value="{{ $record->id }}">
           </td>
           {{-- other <td> cells --}}
       </tr>
       @endforeach
   </x-list>
   ```

---

## Supported models (config/exportable.php)

| Key | Model | Permission |
|---|---|---|
| `contacts` | `Contact` | `contacts.export` |
| `employees` | `Employee` | `employees.export` |
| `workflow.tickets` | `Ticket` | `workflow.tickets.export` |
| `users` | `User` | `users.export` |

Add more entries to `config/exportable.php` following the same pattern.
