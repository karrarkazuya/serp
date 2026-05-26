@extends('layouts.app')
@section('title', __('employees.positions_title'))

@php
    $quickFilters = [
        ['label' => __('common.active'),   'url' => route('employees.positions.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'url' => route('employees.positions.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'url' => route('employees.positions.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $positionGroups = [
        ['label' => __('employees.assignment_type'), 'url' => route('employees.positions.index', array_merge(request()->except('page'), ['group_by' => 'assignment_type']))],
        ['label' => __('employees.data_status'),     'url' => route('employees.positions.index', array_merge(request()->except('page'), ['group_by' => 'data_status']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.positions.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.positions_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\EmployeePosition::class"
            :action="route('employees.positions.index')"
            :quick-filters="$quickFilters"
            :group-by="$positionGroups"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ collect($groups)->sum('count') }} records</span>
            @elseif(isset($positions) && $positions->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $positions->firstItem() }}-{{ $positions->lastItem() }} / {{ $positions->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($positions) && $positions->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @elseif(isset($positions))
                    <a href="{{ $positions->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($positions) && $positions->hasMorePages())
                    <a href="{{ $positions->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @elseif(isset($positions))
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('employees.no_positions')">
        <x-slot:columns>
            <x-sortable-th column="organizational_structure" :label="__('employees.organizational_structure')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="assignment_type"          :label="__('employees.assignment_type')"         class="px-3 py-2" />
            <x-sortable-th column="affective_date"           :label="__('employees.affective_date')"          class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.data_status') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.financial_specialization') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_employee') }}</th>
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $pos)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.positions.show', $pos) }}'">
                <td class="px-4 py-2.5">
                    <span class="text-sm font-medium text-gray-800">{{ $pos->organizational_structure ?? '—' }}</span>
                    @if(!$pos->active)
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50 ms-1">{{ __('common.archived') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $pos->assignment_type ?? '—' }}</td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $pos->affective_date?->format('d M Y') ?? '—' }}</td>
                <td class="px-3 py-2.5">
                    @if($pos->data_status)
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $pos->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $pos->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                    </span>
                    @else —
                    @endif
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">
                    {{ $pos->financial_specialization ? number_format($pos->financial_specialization, 2) : '—' }}
                </td>
                <td class="px-3 py-2.5">
                    <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                        {{ $pos->employees_count }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_positions') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$positions" :empty-text="__('employees.no_positions')">
        <x-slot:columns>
            <x-sortable-th column="organizational_structure" :label="__('employees.organizational_structure')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="assignment_type"          :label="__('employees.assignment_type')"         class="px-3 py-2" />
            <x-sortable-th column="affective_date"           :label="__('employees.affective_date')"          class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.data_status') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.financial_specialization') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_employee') }}</th>
        </x-slot:columns>

        @foreach($positions as $pos)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.positions.show', $pos) }}'">
            <td class="px-4 py-2.5">
                <span class="text-sm font-medium text-gray-800">{{ $pos->organizational_structure ?? '—' }}</span>
                @if(!$pos->active)
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50 ms-1">{{ __('common.archived') }}</span>
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $pos->assignment_type ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $pos->affective_date?->format('d M Y') ?? '—' }}</td>
            <td class="px-3 py-2.5">
                @if($pos->data_status)
                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $pos->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $pos->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                </span>
                @else —
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">
                {{ $pos->financial_specialization ? number_format($pos->financial_specialization, 2) : '—' }}
            </td>
            <td class="px-3 py-2.5">
                <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                    {{ $pos->employees_count }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
