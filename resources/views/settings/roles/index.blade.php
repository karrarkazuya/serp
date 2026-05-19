@extends('layouts.app')
@section('title', __('settings.roles'))
@include('settings._sidebar')

@section('content')
@php
    $roleQuickFilters = [
        ['label' => __('common.active'), 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['filters' => json_encode([['field' => 'active', 'operator' => '=', 'value' => '1', 'display' => 'Yes']])]))],
        ['label' => __('common.inactive'), 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['filters' => json_encode([['field' => 'active', 'operator' => '=', 'value' => '0', 'display' => 'No']])]))],
    ];
    $roleGroups = [
        ['label' => __('common.status'), 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['group_by' => 'active']))],
        ['label' => __('common.created_by'), 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['group_by' => 'created_by']))],
    ];
@endphp
<div class="flex flex-col h-full">
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3 flex-wrap">
        @include('components.breadcrumb', ['items' => [
            ['label' => __('settings.title'), 'url' => route('settings.index')],
            ['label' => __('settings.roles')],
        ]])

        @can('create', \App\Models\Security\Role::class)
        <a href="{{ route('settings.roles.create') }}"
           class="ms-auto flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('settings.new_role') }}
        </a>
        @endcan

        <x-search
            :model="\App\Models\Security\Role::class"
            :action="route('settings.roles.index')"
            :quick-filters="$roleQuickFilters"
            :group-by="$roleGroups"
        />
    </div>

    <x-list :paginator="$roles" :empty-text="__('settings.no_roles')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('common.name')" class="px-6" :default="true" />
            <x-sortable-th column="key" :label="__('settings.role_key')" />
            <x-sortable-th column="permissions" :label="__('settings.permissions')" />
            <x-sortable-th column="users" :label="__('settings.users')" />
            <x-sortable-th column="status" :label="__('common.status')" />
            <th class="text-end px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.actions') }}</th>
        </x-slot:columns>

        @foreach($roles as $role)
        <tr class="hover:bg-purple-50/30 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.roles.edit', $role) }}'">
            <td class="px-6 py-3">
                <div class="text-sm font-medium text-gray-900">{{ $role->name }}</div>
                @if($role->description)<div class="text-xs text-gray-500">{{ $role->description }}</div>@endif
            </td>
            <td class="px-4 py-3">
                <code class="text-xs bg-gray-100 text-gray-700 px-1.5 py-0.5 rounded font-mono">{{ $role->key }}</code>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $role->permissions_count }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $role->users_count }}</td>
            <td class="px-4 py-3">
                @if($role->active)
                    <span class="inline-flex items-center gap-1 text-xs text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('common.active') }}</span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400"><span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> {{ __('common.inactive') }}</span>
                @endif
            </td>
            <td class="px-4 py-3 text-end" onclick="event.stopPropagation()">
                <div class="flex items-center justify-end gap-2">
                    @can('update', $role)
                    <a href="{{ route('settings.roles.edit', $role) }}"
                       class="text-xs text-gray-500 hover:text-purple-600 transition-colors px-2 py-1 rounded hover:bg-purple-50">{{ __('common.edit') }}</a>
                    @endcan
                    @can('delete', $role)
                    @if($role->key !== 'admin')
                    <form method="POST" action="{{ route('settings.roles.delete', $role) }}"
                          onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                    </form>
                    @endif
                    @endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
