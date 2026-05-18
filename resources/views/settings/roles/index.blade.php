@extends('layouts.app')
@section('title', 'Roles')
@include('settings._sidebar')

@section('content')
@php
    $roleQuickFilters = [
        ['label' => 'Active', 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['filters' => json_encode([['field' => 'active', 'operator' => '=', 'value' => '1', 'display' => 'Yes']])]))],
        ['label' => 'Inactive', 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['filters' => json_encode([['field' => 'active', 'operator' => '=', 'value' => '0', 'display' => 'No']])]))],
    ];
    $roleGroups = [
        ['label' => 'Status', 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['group_by' => 'active']))],
        ['label' => 'Created by', 'url' => route('settings.roles.index', array_merge(request()->except('page'), ['group_by' => 'created_by']))],
    ];
@endphp
<div class="flex flex-col h-full">
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3 flex-wrap">
        @include('components.breadcrumb', ['items' => [
            ['label' => 'Settings', 'url' => route('settings.index')],
            ['label' => 'Roles'],
        ]])

        @can('create', \App\Models\Security\Role::class)
        <a href="{{ route('settings.roles.create') }}"
           class="ml-auto flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Role
        </a>
        @endcan

        <x-search
            :model="\App\Models\Security\Role::class"
            :action="route('settings.roles.index')"
            placeholder="Search roles..."
            :quick-filters="$roleQuickFilters"
            :group-by="$roleGroups"
        />
    </div>

    <div class="flex-1 overflow-auto bg-white">
        <table class="w-full erp-table">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <x-sortable-th column="name" label="Name" class="px-6" :default="true" />
                    <x-sortable-th column="key" label="Key" />
                    <x-sortable-th column="permissions" label="Permissions" />
                    <x-sortable-th column="users" label="Users" />
                    <x-sortable-th column="status" label="Status" />
                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($roles as $role)
                <tr class="hover:bg-gray-50 transition-colors">
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
                            <span class="inline-flex items-center gap-1 text-xs text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active</span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-gray-400"><span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> Inactive</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            @can('update', $role)
                            <a href="{{ route('settings.roles.edit', $role) }}"
                               class="text-xs text-gray-500 hover:text-purple-600 transition-colors px-2 py-1 rounded hover:bg-purple-50">Edit</a>
                            @endcan
                            @can('delete', $role)
                            @if($role->key !== 'admin')
                            <form method="POST" action="{{ route('settings.roles.delete', $role) }}"
                                  onsubmit="return confirm('Delete this role?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded hover:bg-red-50">Delete</button>
                            </form>
                            @endif
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-400">No roles found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
