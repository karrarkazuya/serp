@extends('layouts.app')
@section('title', 'Working Schedules')

@php
    $quickFilters = [
        ['label' => 'Active',   'params' => ['filter' => ''],        'url' => route('employees.schedules.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => 'Archived', 'params' => ['filter' => 'archived'], 'url' => route('employees.schedules.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',      'params' => ['filter' => 'all'],      'url' => route('employees.schedules.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.schedules.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            New
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">Working Schedules</span>
        </div>

        <x-search
            :model="\App\Models\Employees\ResourceCalendar::class"
            :action="route('employees.schedules.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($schedules->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $schedules->firstItem() }}-{{ $schedules->lastItem() }} / {{ $schedules->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            <div class="flex items-center gap-1">
                @if($schedules->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $schedules->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($schedules->hasMorePages())
                    <a href="{{ $schedules->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$schedules" empty-text="No working schedules found.">
        <x-slot:columns>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Company</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Timezone</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Work Days</th>
        </x-slot:columns>

        @foreach($schedules as $schedule)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.schedules.show', $schedule) }}'">
            <td class="px-4 py-2.5 text-sm font-semibold text-gray-900">{{ $schedule->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $schedule->company?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $schedule->timezone }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $schedule->attendances->count() }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
