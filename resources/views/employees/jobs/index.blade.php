@extends('layouts.app')
@section('title', 'Job Positions')

@php
    $quickFilters = [
        ['label' => 'Active',   'params' => ['filter' => ''],         'url' => route('employees.jobs.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => 'Archived', 'params' => ['filter' => 'archived'],  'url' => route('employees.jobs.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',      'params' => ['filter' => 'all'],       'url' => route('employees.jobs.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.jobs.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            New
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">Job Positions</span>
        </div>

        <x-search
            :model="\App\Models\Employees\Job::class"
            :action="route('employees.jobs.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($jobs->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $jobs->firstItem() }}-{{ $jobs->lastItem() }} / {{ $jobs->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            <div class="flex items-center gap-1">
                @if($jobs->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $jobs->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($jobs->hasMorePages())
                    <a href="{{ $jobs->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$jobs" empty-text="No job positions found.">
        <x-slot:columns>
            <x-sortable-th column="name"       label="Job Position" class="px-4 py-2" :default="true" />
            <x-sortable-th column="department"  label="Department"   class="px-3 py-2" />
            <x-sortable-th column="company"     label="Company"      class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Employees</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Expected</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
        </x-slot:columns>

        @foreach($jobs as $job)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.jobs.show', $job) }}'">
            <td class="px-4 py-2.5 font-medium text-gray-900">
                <p class="text-sm font-semibold text-gray-900">{{ $job->name }}</p>
                @if(!$job->active)
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">Archived</span>
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $job->department?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $job->company?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $job->no_of_employee }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $job->expected_employees }}</td>
            <td class="px-3 py-2.5">
                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $job->state === 'open' ? 'text-green-700 bg-green-50' : 'text-gray-600 bg-gray-100' }}">
                    {{ $job->state === 'open' ? 'Recruiting' : 'Not Recruiting' }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
