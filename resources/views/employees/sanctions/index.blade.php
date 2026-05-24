@extends('layouts.app')
@section('title', __('employees.sanctions_title'))

@php
    $quickFilters = [
        ['label' => __('common.active'),   'url' => route('employees.sanctions.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'url' => route('employees.sanctions.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'url' => route('employees.sanctions.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.sanctions.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.sanctions_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\EmployeeSanction::class"
            :action="route('employees.sanctions.index')"
            :quick-filters="$quickFilters"
        />

        @can('export', \App\Models\Employees\Employee::class)
        <x-export
            :fields="config('exportable')['employees.sanctions']['fields'] ?? []"
            :export-url="route('export')"
            model-key="employees.sanctions"
        />
        @endcan

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $groups->sum('count') }}</span>
            @elseif(isset($records) && $records->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $records->firstItem() }}-{{ $records->lastItem() }} / {{ $records->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            @unless(isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($records) && $records->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ isset($records) ? $records->previousPageUrl() : '#' }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($records) && $records->hasMorePages())
                    <a href="{{ $records->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endunless
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('employees.no_sanctions')">
        <x-slot:columns>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_name') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_employee') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.affective_date') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.data_status') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_type') }}</th>
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
            @foreach($group['items'] as $record)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.sanctions.show', $record) }}'">
                <td class="px-4 py-2.5">
                    <p class="text-sm font-semibold text-gray-900">{{ $record->name ?? '—' }}</p>
                    @if(!$record->active)
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2.5">
                    <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                        <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                        </svg>
                        {{ $record->employees_count ?? 0 }}
                    </span>
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $record->affective_date?->format('d M Y') ?? '—' }}</td>
                <td class="px-3 py-2.5">
                    @if($record->data_status)
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $record->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $record->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                    </span>
                    @else —
                    @endif
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $record->document_type ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_sanctions') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$records" :selectable="true" :total-count="$records->total()" :empty-text="__('employees.no_sanctions')">
        <x-slot:columns>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_name') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_employee') }}</th>
            <x-sortable-th column="affective_date" :label="__('employees.affective_date')" class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.data_status') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_type') }}</th>
        </x-slot:columns>

        @foreach($records as $record)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.sanctions.show', $record) }}'">
            <td class="w-10 px-3 py-2 text-center" @click.stop>
                <input type="checkbox" class="list-checkbox rounded border-gray-300 text-purple-600" x-model="selected" value="{{ $record->id }}">
            </td>
            <td class="px-4 py-2.5">
                <p class="text-sm font-semibold text-gray-900">{{ $record->name ?? '—' }}</p>
                @if(!$record->active)
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                @endif
            </td>
            <td class="px-3 py-2.5">
                <span class="inline-flex items-center gap-1 text-sm text-gray-600">
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0"/>
                    </svg>
                    {{ $record->employees_count }}
                </span>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $record->affective_date?->format('d M Y') ?? '—' }}</td>
            <td class="px-3 py-2.5">
                @if($record->data_status)
                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $record->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $record->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                </span>
                @else —
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $record->document_type ?? '—' }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
