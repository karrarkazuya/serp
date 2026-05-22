# `<x-toolbar>` Component

The page toolbar rendered at the top of every show, create, and edit page. Provides a consistent white bar with breadcrumb, action buttons, and optional record navigation.

## Component files

- `app/View/Components/Toolbar.php`
- `resources/views/components/toolbar.blade.php`

---

## Props

| Prop | Type | Default | Description |
|---|---|---|---|
| `new-href` | `string\|null` | `null` | Renders a purple "New" button at the far inline-start. Pass `null` to omit. |
| `position` | `int\|null` | `null` | Current record position (e.g. `3`). Enables the pagination block. |
| `total` | `int\|null` | `null` | Total records in the current filtered set. |
| `prev-href` | `string\|null` | `null` | URL for the ‹ arrow. Pass `null` to render a disabled arrow. |
| `next-href` | `string\|null` | `null` | URL for the › arrow. Pass `null` to render a disabled arrow. |

RTL arrow direction is handled automatically by the component.

---

## Named slots

| Slot | Required | Description |
|---|---|---|
| `breadcrumb` | Yes | Inner content of the breadcrumb section. Wrapped in `<div class="shrink-0 flex flex-col leading-tight">` by the component. |
| `actions` | No | Action buttons rendered after the breadcrumb. Render as direct flex children — wrap in `<div class="flex items-center gap-2">` for grouped buttons. |

---

## Usage patterns

### Create page

```blade
<x-toolbar>
    <x-slot:breadcrumb>
        <a href="{{ route('contacts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('contacts.title') }}</a>
        <span class="text-sm font-semibold text-gray-800">{{ __('contacts.new_contact') }}</span>
    </x-slot:breadcrumb>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('contacts.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
            <button form="contact-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
        </div>
    </x-slot:actions>
</x-toolbar>
```

### Edit page

```blade
<x-toolbar>
    <x-slot:breadcrumb>
        <a href="{{ route('contacts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('contacts.title') }}</a>
        <div class="flex items-center gap-1">
            <a href="{{ route('contacts.show', $contact) }}" class="text-sm font-semibold text-gray-800 hover:text-purple-700">{{ $contact->name }}</a>
            <span class="text-xs text-gray-400">/</span>
            <span class="text-sm text-gray-500">{{ __('common.edit') }}</span>
        </div>
    </x-slot:breadcrumb>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            <a href="{{ route('contacts.show', $contact) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
            <button form="contact-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
        </div>
    </x-slot:actions>
</x-toolbar>
```

### Show page (simple — no pagination)

```blade
<x-toolbar>
    <x-slot:breadcrumb>
        <a href="{{ route('employees.jobs.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.jobs_title') }}</a>
        <span class="text-sm font-semibold text-gray-800">{{ $job->name }}</span>
    </x-slot:breadcrumb>
    <x-slot:actions>
        <div class="flex items-center gap-2">
            @can('update', $job)
            <a href="{{ route('employees.jobs.edit', $job) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan
            @can('update', $job)
            <form method="POST" action="{{ $job->active ? route('employees.jobs.archive', $job) : route('employees.jobs.unarchive', $job) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    {{ $job->active ? __('common.archive') : __('common.unarchive') }}
                </button>
            </form>
            @endcan
            @can('delete', $job)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.jobs.delete', $job) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                    </form>
                    <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">{{ __('common.cancel') }}</button>
                </div>
            </div>
            @endcan
        </div>
    </x-slot:actions>
</x-toolbar>
```

### Show page (main model — with New button and pagination)

The `new-href` prop is set conditionally using a `@can` PHP block so the button only appears if the user has permission.

```blade
@can('create', \App\Models\Contacts\Contact::class)
    @php $newHref = route('contacts.create'); @endphp
@endcan

<x-toolbar
    :new-href="$newHref ?? null"
    :position="$recordPosition ?: null"
    :total="$recordTotal ?? null"
    :prev-href="$prevId ? route('contacts.show', $prevId) : null"
    :next-href="$nextId ? route('contacts.show', $nextId) : null">
    <x-slot:breadcrumb>
        <a href="{{ route('contacts.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('contacts.title') }}</a>
        <span class="text-sm font-semibold text-gray-800">{{ $contact->name }}</span>
    </x-slot:breadcrumb>
    <x-slot:actions>
        @can('update', $contact)
        <a href="{{ route('contacts.edit', $contact) }}" class="shrink-0 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        ...
        @endcan
    </x-slot:actions>
</x-toolbar>
```

---

## What the component handles automatically

- The outer `<div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">` wrapper
- Breadcrumb wrapping in `<div class="shrink-0 flex flex-col leading-tight">`
- Pagination block with `ms-auto` push and RTL arrow direction (`‹`/`›` swap)
- RTL locale detection via `app()->getLocale() === 'ar'`

## What you still own in the view

- The content of the `breadcrumb` slot (links, spans, text)
- The content of the `actions` slot (buttons, forms, `@can` guards)
- The `new-href` permission check (use `@can` block + `@php $newHref = ...` before the component)
