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
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 flex-wrap shrink-0">
        @can('create', \App\Models\Security\Role::class)
        <a href="{{ route('settings.roles.create') }}"
           class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0 transition-colors">
            {{ __('settings.new_role') }}
        </a>
        @endcan

        <span class="text-lg font-semibold text-gray-700">{{ __('settings.roles') }}</span>

        <x-search
            :model="\App\Models\Security\Role::class"
            :action="route('settings.roles.index')"
            :quick-filters="$roleQuickFilters"
            :group-by="$roleGroups"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            @if($roles->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $roles->firstItem() }}-{{ $roles->lastItem() }} / {{ $roles->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            <div class="flex items-center gap-1">
                @if($roles->onFirstPage())
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $roles->previousPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($roles->hasMorePages())
                    <a href="{{ $roles->nextPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
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
        <tr class="hover:bg-[#714B67]/5 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.roles.edit', $role) }}'">
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
                       class="text-xs text-gray-500 hover:text-[#714B67] transition-colors px-2 py-1 rounded hover:bg-[#714B67]/5">{{ __('common.edit') }}</a>
                    @endcan
                    @can('delete', $role)
                    @if($role->key !== 'admin')
                    <form method="POST" action="{{ route('settings.roles.delete', $role) }}"
                          @submit.prevent="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })">
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
