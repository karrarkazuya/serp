@extends('layouts.app')
@section('title', __('employees.locations_title'))

@php
    $quickFilters = [
        ['label' => __('common.active'),   'params' => ['filter' => ''],        'url' => route('employees.work-locations.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'params' => ['filter' => 'archived'], 'url' => route('employees.work-locations.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'params' => ['filter' => 'all'],      'url' => route('employees.work-locations.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.work-locations.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('employees.new_location') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.locations_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\WorkLocation::class"
            :action="route('employees.work-locations.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($locations->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $locations->firstItem() }}-{{ $locations->lastItem() }} / {{ $locations->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            <div class="flex items-center gap-1">
                @if($locations->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $locations->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($locations->hasMorePages())
                    <a href="{{ $locations->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$locations" :empty-text="__('employees.no_locations')">
        <x-slot:columns>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.name') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.location_address') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.company') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.employees') }}</th>
        </x-slot:columns>

        @foreach($locations as $location)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.work-locations.show', $location) }}'">
            <td class="px-4 py-2.5 text-sm font-semibold text-gray-900">{{ $location->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $location->address }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $location->company?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $location->employees_count }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
