@extends('layouts.app')
@section('title', __('settings.permissions'))
@include('settings._sidebar')

@section('content')
<div class="p-6 max-w-5xl">
    <div class="flex items-center justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-800">{{ __('settings.permissions') }}</h1>
            <p class="text-sm text-gray-500 mt-0.5">{{ __('settings.permissions_desc') }}</p>
        </div>
        <x-search
            :model="\App\Models\Security\Permission::class"
            :action="route('settings.permissions.index')"
            :group-by="[
                ['label' => __('settings.permission_module'), 'url' => route('settings.permissions.index', array_merge(request()->except('page'), ['group_by' => 'module']))],
                ['label' => __('common.created_by'), 'url' => route('settings.permissions.index', array_merge(request()->except('page'), ['group_by' => 'created_by']))],
            ]"
        />
    </div>

    @foreach($permissions as $module => $perms)
    <div class="bg-white rounded-xl border border-gray-200 mb-4">
        <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
            <h2 class="text-sm font-semibold text-gray-700 capitalize">{{ ucfirst($module) }}</h2>
            <span class="text-xs text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full">{{ $perms->count() }} {{ __('settings.permissions') }}</span>
        </div>
        <div class="overflow-auto">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <x-sortable-th column="name" :label="__('settings.permission_name')" class="px-5 py-2 text-gray-400" :default="true" />
                        <x-sortable-th column="key" :label="__('settings.permission_key')" class="px-4 py-2 text-gray-400" />
                        <x-sortable-th column="description" :label="__('settings.permission_desc')" class="px-4 py-2 text-gray-400" />
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($perms as $perm)
                    <tr class="hover:bg-gray-50">
                        <td class="px-5 py-2.5 text-sm font-medium text-gray-800">{{ $perm->name }}</td>
                        <td class="px-4 py-2.5">
                            <code class="text-xs bg-purple-50 text-purple-700 px-2 py-0.5 rounded font-mono">{{ $perm->key }}</code>
                        </td>
                        <td class="px-4 py-2.5 text-sm text-gray-500">{{ $perm->description ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>
@endsection
