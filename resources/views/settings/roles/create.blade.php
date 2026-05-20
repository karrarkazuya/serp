@extends('layouts.app')
@section('title', __('settings.new_role'))
@include('settings._sidebar')

@section('content')
<div class="flex flex-col h-full overflow-hidden">
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3 shrink-0">
        @include('components.breadcrumb', ['items' => [
            ['label' => __('settings.title'), 'url' => route('settings.index')],
            ['label' => __('settings.roles'), 'url' => route('settings.roles.index')],
            ['label' => __('settings.new_role')],
        ]])
        <div class="ms-auto flex gap-2">
            <a href="{{ route('settings.roles.index') }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded-md hover:bg-gray-50 transition-colors">{{ __('common.cancel') }}</a>
            <button form="role-form" type="submit"
                    class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] rounded-md hover:bg-[#5c3d55] shadow-sm transition-colors">{{ __('common.save') }}</button>
        </div>
    </div>

    <form id="role-form" method="POST" action="{{ route('settings.roles.store') }}"
          class="flex-1 overflow-hidden flex flex-col">
        @csrf
        @include('settings.roles._form', ['role' => null, 'permissions' => $permissions, 'assignedIds' => []])
    </form>
</div>
@endsection
