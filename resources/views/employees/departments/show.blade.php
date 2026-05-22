@extends('layouts.app')
@section('title', $department->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    {{-- Top bar --}}
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.departments.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.departments_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $department->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', $department)
        <a href="{{ route('employees.departments.edit', $department) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        @endcan

        @can('update', $department)
        <form method="POST" action="{{ $department->active ? route('employees.departments.archive', $department) : route('employees.departments.unarchive', $department) }}">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                {{ $department->active ? __('common.archive') : __('common.unarchive') }}
            </button>
        </form>
        @endcan

        @can('delete', $department)
        <div x-data="{ confirming: false }">
            <button type="button" x-show="!confirming" @click="confirming = true"
                    class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                <form method="POST" action="{{ route('employees.departments.delete', $department) }}">
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

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        {{-- Detail card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            @if(!$department->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('employees.dept_is_archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.department_name') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $department->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.manager') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">
                        @if($department->manager)
                            <a href="{{ route('employees.show', $department->manager) }}" class="text-purple-600 hover:underline">{{ $department->manager->name }}</a>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.parent_department') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">
                        @if($department->parent)
                            <a href="{{ route('employees.departments.show', $department->parent) }}" class="text-purple-600 hover:underline">{{ $department->parent->name }}</a>
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.company') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $department->company?->name ?? '—' }}</p>
                </div>
                @if($department->note)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.notes') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5 whitespace-pre-line">{{ $department->note }}</p>
                </div>
                @endif
            </div>
        </div>

        {{-- Employees in this department --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('common.employees') }} ({{ $department->employees->count() }})</h3>
            </div>
            @if($department->employees->isEmpty())
                <p class="px-5 py-6 text-sm text-gray-400 text-center">{{ __('employees.dept_no_employees') }}</p>
            @else
                <ul class="divide-y divide-gray-100">
                    @foreach($department->employees as $emp)
                    <li class="px-5 py-3 flex items-center gap-3 hover:bg-purple-50/30">
                        <a href="{{ route('employees.show', $emp) }}" class="flex items-center gap-3 flex-1 min-w-0">
                            @if($emp->avatar_url)
                                <img src="{{ $emp->avatar_url }}" alt="{{ $emp->name }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                            @else
                                <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">{{ strtoupper(substr($emp->name, 0, 2)) }}</div>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate">{{ $emp->name }}</p>
                                <p class="text-xs text-gray-400 truncate">{{ $emp->job_title ?? $emp->job?->name }}</p>
                            </div>
                        </a>
                    </li>
                    @endforeach
                </ul>
            @endif
        </div>

        {{-- Sub-departments --}}
        @if($department->children->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.sub_departments') }} ({{ $department->children->count() }})</h3>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($department->children as $child)
                <li class="px-5 py-3 hover:bg-purple-50/30">
                    <a href="{{ route('employees.departments.show', $child) }}" class="text-sm font-medium text-purple-600 hover:underline">{{ $child->name }}</a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Chatter --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\Department"
                :model-id="$department->id"
                :can-comment="auth()->user()->can('comment', $department)"
                :comment-url="route('employees.departments.comment', $department)"
            />
        </div>
    </div>
</div>
@endsection
