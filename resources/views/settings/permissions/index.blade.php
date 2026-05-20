@extends('layouts.app')
@section('title', __('settings.permissions'))
@include('settings._sidebar')

@section('content')
@php
    $permGroups = [
        ['label' => __('settings.permission_module'), 'url' => route('settings.permissions.index', array_merge(request()->except('page'), ['group_by' => 'module']))],
        ['label' => __('common.created_by'), 'url' => route('settings.permissions.index', array_merge(request()->except('page'), ['group_by' => 'created_by']))],
    ];
@endphp
<div class="flex flex-col h-full">

    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 flex-wrap shrink-0">
        <span class="text-lg font-semibold text-gray-700">{{ __('settings.permissions') }}</span>

        <x-search
            :model="\App\Models\Security\Permission::class"
            :action="route('settings.permissions.index')"
            :group-by="$permGroups"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                {{ $permissions->flatten()->count() }} {{ __('settings.permissions') }}
            </span>
        </div>
    </div>

    <x-list :empty-text="__('settings.no_permissions')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('settings.permission_name')" class="px-6" :default="true" />
            <x-sortable-th column="key" :label="__('settings.permission_key')" class="px-4" />
            <x-sortable-th column="description" :label="__('settings.permission_desc')" class="px-4" />
        </x-slot:columns>

        @foreach($permissions as $module => $perms)
        <tr class="bg-gray-50">
            <td colspan="3" class="px-6 py-2">
                <div class="flex items-center gap-2">
                    <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">{{ ucfirst($module) }}</span>
                    <span class="text-xs text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded-full">{{ $perms->count() }}</span>
                </div>
            </td>
        </tr>
        @foreach($perms as $perm)
        <tr class="hover:bg-[#714B67]/5 transition-colors">
            <td class="px-6 py-2.5 text-sm font-medium text-gray-800">{{ $perm->name }}</td>
            <td class="px-4 py-2.5">
                <code class="text-xs bg-[#714B67]/10 text-[#714B67] px-2 py-0.5 rounded font-mono">{{ $perm->key }}</code>
            </td>
            <td class="px-4 py-2.5 text-sm text-gray-500">{{ $perm->description ?: '—' }}</td>
        </tr>
        @endforeach
        @endforeach
    </x-list>
</div>
@endsection
