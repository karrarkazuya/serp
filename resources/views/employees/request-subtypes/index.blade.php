@extends('layouts.app')
@section('title', __('employees.subtypes_title'))

@php
    $quickFilters = [
        ['label' => __('common.active'),   'params' => ['filter' => ''],         'url' => route('employees.request-subtypes.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'params' => ['filter' => 'archived'], 'url' => route('employees.request-subtypes.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'params' => ['filter' => 'all'],      'url' => route('employees.request-subtypes.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\RequestSubtype::class)
        <a href="{{ route('employees.request-subtypes.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('employees.new_subtype') }}</a>
        @endcan
        @can('export', \App\Models\Employees\RequestSubtype::class)
        <x-export
            :fields="config('exportable.attendance_subtypes.fields', [])"
            :export-url="route('export')"
            model-key="attendance_subtypes"
        />
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('employees.subtypes_title') }}</span>
        <x-search :model="\App\Models\Employees\RequestSubtype::class" :action="route('employees.request-subtypes.index')" :quick-filters="$quickFilters" />
        <div class="ms-auto flex items-center gap-2 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600">{{ $groups->sum('count') }}</span>
            @elseif(isset($subtypes) && $subtypes->total() > 0)
                <span class="text-sm font-semibold text-gray-600">{{ $subtypes->firstItem() }}-{{ $subtypes->lastItem() }} / {{ $subtypes->total() }}</span>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('employees.no_subtypes') }}">
        <x-slot:columns>
            <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.subtype_name') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.subtype_type') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('common.company') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.subtype_factor') }}</th>
        </x-slot:columns>
        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $subtype)
            @include('employees.request-subtypes._row', ['subtype' => $subtype, 'grouped' => true])
            @endforeach
        </tbody>
        @empty
        <tbody><tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_subtypes') }}</td></tr></tbody>
        @endforelse
    </x-list>
    @else
    <x-list :paginator="$subtypes"
            :selectable="true"
            :total-count="$subtypes->total()"
            empty-text="{{ __('employees.no_subtypes') }}">
        <x-slot:columns>
            <x-sortable-th column="name"    :label="__('employees.subtype_name')"        class="px-4 py-2" :default="true" />
            <x-sortable-th column="type"    :label="__('employees.subtype_type')"        class="px-3 py-2" />
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('common.company') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.subtype_factor') }}</th>
        </x-slot:columns>
        @foreach($subtypes as $subtype)
        @include('employees.request-subtypes._row', ['subtype' => $subtype, 'grouped' => false, 'selectable' => true])
        @endforeach
    </x-list>
    @endif
</div>
@endsection
