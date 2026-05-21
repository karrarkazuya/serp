@extends('layouts.app')
@section('title', 'Challenges')

@php
    $quickFilters = [
        ['label' => 'Active',   'params' => ['filter' => ''],        'url' => route('employees.challenges.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => 'Archived', 'params' => ['filter' => 'archived'], 'url' => route('employees.challenges.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',      'params' => ['filter' => 'all'],      'url' => route('employees.challenges.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Challenge::class)
        <a href="{{ route('employees.challenges.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            New
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">Challenges</span>
        </div>

        <x-search
            :model="\App\Models\Employees\Challenge::class"
            :action="route('employees.challenges.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($challenges->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $challenges->firstItem() }}-{{ $challenges->lastItem() }} / {{ $challenges->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            <div class="flex items-center gap-1">
                @if($challenges->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $challenges->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($challenges->hasMorePages())
                    <a href="{{ $challenges->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$challenges" empty-text="No challenges found.">
        <x-slot:columns>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Description</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Status</th>
        </x-slot:columns>

        @foreach($challenges as $challenge)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.challenges.show', $challenge) }}'">
            <td class="px-4 py-2.5 text-sm font-semibold text-gray-900">{{ $challenge->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600 max-w-xs truncate">{{ $challenge->description ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">
                @if($challenge->active)
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Active</span>
                @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">Archived</span>
                @endif
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
