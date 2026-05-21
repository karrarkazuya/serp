@extends('layouts.app')
@section('title', 'Departments')

@php
    $quickFilters = [
        ['label' => 'Active',   'params' => ['filter' => ''],         'url' => route('employees.departments.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => 'Archived', 'params' => ['filter' => 'archived'],  'url' => route('employees.departments.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',      'params' => ['filter' => 'all'],       'url' => route('employees.departments.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.departments.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            New
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">Departments</span>
        </div>

        <x-search
            :model="\App\Models\Employees\Department::class"
            :action="route('employees.departments.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($departments->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $departments->firstItem() }}-{{ $departments->lastItem() }} / {{ $departments->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            <div class="flex items-center gap-1">
                @if($departments->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $departments->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($departments->hasMorePages())
                    <a href="{{ $departments->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$departments" empty-text="No departments found.">
        <x-slot:columns>
            <x-sortable-th column="name"     label="Name"       class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Manager</th>
            <x-sortable-th column="company"  label="Company"    class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employees</th>
        </x-slot:columns>

        @foreach($departments as $department)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.departments.show', $department) }}'">
            <td class="px-4 py-2.5 font-medium text-gray-900">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $department->name }}</p>
                    @if($department->parent)
                        <p class="text-xs text-gray-400">{{ $department->parent->name }}</p>
                    @endif
                    @if(!$department->active)
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">Archived</span>
                    @endif
                </div>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->manager?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->company?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->employees_count }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
