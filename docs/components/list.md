# List Component

## Purpose

The list component renders an Odoo-style scrollable data table for module index pages. It handles the full table structure — wrapper, thead, tbody, row dividers, empty state, and pagination — so individual views only define columns and rows.

Use it instead of hand-built `<div class="flex-1 overflow-auto"><table>` blocks in list pages.

## Files

- Blade component view: `resources/views/components/list.blade.php`
- Blade component class: `app/View/Components/TableList.php`
- Registered alias: `Blade::component('list', TableList::class)` in `AppServiceProvider::boot()`

## Blade Usage

Basic table with sortable columns and click-to-navigate rows:

```blade
<x-list :paginator="$contacts" empty-text="No contacts found.">
    <x-slot:columns>
        <x-sortable-th column="name"  label="Name"  :default="true" />
        <x-sortable-th column="email" label="Email" />
        <th class="px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
    </x-slot:columns>

    @foreach($contacts as $contact)
    <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('contacts.show', $contact) }}'">
        <td class="px-4 py-2 font-medium text-gray-900">{{ $contact->name }}</td>
        <td class="px-3 py-2 text-gray-600">{{ $contact->email }}</td>
        <td class="px-3 py-2 text-gray-600">{{ $contact->company?->name ?? '—' }}</td>
    </tr>
    @endforeach
</x-list>
```

Table without a paginator (collection or no records object):

```blade
<x-list empty-text="No items.">
    <x-slot:columns>
        <x-sortable-th column="name" label="Name" :default="true" />
    </x-slot:columns>

    @foreach($items as $item)
    <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="...">
        <td class="px-4 py-2">{{ $item->name }}</td>
    </tr>
    @endforeach
</x-list>
```

Action column with `onclick` propagation stopped:

```blade
<x-list :paginator="$roles" empty-text="No roles found.">
    <x-slot:columns>
        <x-sortable-th column="name" label="Name" class="px-6" :default="true" />
        <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
    </x-slot:columns>

    @foreach($roles as $role)
    <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('settings.roles.edit', $role) }}'">
        <td class="px-6 py-3 text-sm font-medium text-gray-900">{{ $role->name }}</td>
        <td class="px-4 py-3 text-right" onclick="event.stopPropagation()">
            <a href="{{ route('settings.roles.edit', $role) }}" class="text-xs text-purple-600 hover:text-purple-700">Edit</a>
        </td>
    </tr>
    @endforeach
</x-list>
```

## Props

| Prop | Type | Default | Description |
|------|------|---------|-------------|
| `paginator` | LengthAwarePaginator or collection | `null` | Record source. Used to detect the empty state and render pagination links. |
| `empty-text` | string | `'No records found.'` | Message shown when the paginator or collection is empty. |
| `class` | string | `''` | Extra CSS classes merged onto the outer wrapper `<div>`. |

## Named Slots

| Slot | Description |
|------|-------------|
| `columns` | Content of the `<thead><tr>` row. Place `<x-sortable-th>` and plain `<th>` elements here. |
| *(default)* | Content of `<tbody>`. Place `<tr>` rows here. Use `@foreach`, not `@forelse` — the component handles the empty state. |

## Behavior

- The outer `<div class="flex-1 overflow-auto">` fills the remaining height and enables horizontal scroll on narrow viewports.
- `<thead>` is only rendered when the `columns` slot is provided.
- `<thead><tr>` always has `border-b border-gray-200 bg-gray-50`.
- `<tbody>` uses `divide-y divide-gray-100` for row separators — do not add `border-b` to individual `<tr>` elements.
- When `paginator` is provided and empty, the component renders the `empty-text` row spanning all columns (via `colspan="100"`) instead of the default slot.
- When `paginator` is a `LengthAwarePaginator` with more than one page, a `{{ $paginator->withQueryString()->links() }}` footer is rendered below the table wrapper.

## Sorting

Place `<x-sortable-th>` components in the `columns` slot. They work exactly as documented in `docs/components/tables_sort.md`. The list component does not add any sorting logic itself.

Example:

```blade
<x-slot:columns>
    <x-sortable-th column="name"       label="Name"       class="px-4 py-2" :default="true" />
    <x-sortable-th column="created_at" label="Created"    class="px-3 py-2" />
    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tags</th>
</x-slot:columns>
```

## Row Conventions

- Use `hover:bg-purple-50/30 cursor-pointer` on `<tr>` for clickable rows (standard Odoo style).
- Use `onclick="window.location='{{ route(...) }}'"` on `<tr>` to make the whole row navigate.
- For action cells that contain links or forms, add `onclick="event.stopPropagation()"` to prevent the row click from firing.
- Do not add `border-b border-gray-100` to `<tr>` — the component's `<tbody class="divide-y divide-gray-100">` handles separators.

## Current Uses

- Contacts list (`resources/views/contacts/index.blade.php`, list view).
- Contact tags list (`resources/views/contacts/tags/index.blade.php`).
- Companies list (`resources/views/settings/companies/index.blade.php`).
- Users list (`resources/views/settings/users/index.blade.php`).
- Roles list (`resources/views/settings/roles/index.blade.php`).
- Workflow tickets list (`resources/views/workflow/tickets/index.blade.php`, list view).
- Workflow procedures list (`resources/views/workflow/procedures/index.blade.php`, list view).
- Workflow tasks list (`resources/views/workflow/tasks/index.blade.php`).
- Workflow groups, departments, managers, workflow users (`resources/views/workflow/configuration/*/index.blade.php`).
- Ticket templates, procedure templates, template tasks (`resources/views/workflow/configuration/*/index.blade.php`).

## Implementation Steps For A New Module

1. Add `<x-list :paginator="$records" empty-text="No records found.">` around the table section of the index view.
2. Move `<x-sortable-th>` and `<th>` headers into `<x-slot:columns>`.
3. Replace `@forelse`/`@empty`/`@endforelse` with `@foreach`/`@endforeach` — the empty state is handled by the component.
4. Remove `border-b border-gray-100` from `<tr>` elements.
5. Remove the manual `<div class="flex-1 overflow-auto"><table>...</table></div>` wrapper.
6. Remove the manual `@if($records->hasPages())` pagination block — the component renders it automatically.
