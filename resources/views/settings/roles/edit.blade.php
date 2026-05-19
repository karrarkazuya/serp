@extends('layouts.app')
@section('title', __('common.edit') . ' — ' . $role->name)
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full">
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3">
        @include('components.breadcrumb', ['items' => [
            ['label' => __('settings.title'), 'url' => route('settings.index')],
            ['label' => __('settings.roles'), 'url' => route('settings.roles.index')],
            ['label' => $role->name],
        ]])
        <div class="ms-auto flex gap-2">
            <a href="{{ route('settings.roles.index') }}"
               class="px-3 py-2 text-sm text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50">{{ __('common.cancel') }}</a>
            <button form="role-form" type="submit"
                    class="px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 shadow-sm">{{ __('common.save') }}</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-6">
        <form id="role-form" method="POST" action="{{ route('settings.roles.update', $role) }}" class="max-w-3xl mx-auto space-y-6">
            @csrf @method('PUT')
            @include('settings.roles._form', ['role' => $role, 'permissions' => $permissions, 'assignedIds' => $assignedIds])
        </form>
    </div>
</div>
@endsection
