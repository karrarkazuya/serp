@extends('layouts.app')
@section('title', __('employees.requests_title'))

@php
    $quickFilters = [
        ['label' => __('common.all'),                   'params' => ['filter' => ''],         'url' => route('employees.requests.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('employees.request_state_pending'),  'params' => ['filter' => 'pending'],  'url' => route('employees.requests.index', array_merge(request()->except('page'), ['filter' => 'pending']))],
        ['label' => __('employees.request_state_approved'), 'params' => ['filter' => 'approved'], 'url' => route('employees.requests.index', array_merge(request()->except('page'), ['filter' => 'approved']))],
        ['label' => __('employees.request_state_rejected'), 'params' => ['filter' => 'rejected'], 'url' => route('employees.requests.index', array_merge(request()->except('page'), ['filter' => 'rejected']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\EmployeeRequest::class)
        <a href="{{ route('employees.requests.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('employees.new_request') }}</a>
        @endcan
        @can('export', \App\Models\Employees\EmployeeRequest::class)
        <x-export
            :fields="config('exportable.attendance_requests.fields', [])"
            :export-url="route('export')"
            model-key="attendance_requests"
        />
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('employees.requests_title') }}</span>
        <x-search :model="\App\Models\Employees\EmployeeRequest::class" :action="route('employees.requests.index')" :quick-filters="$quickFilters" />
        <div class="ms-auto flex items-center gap-2 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600">{{ $groups->sum('count') }}</span>
            @elseif(isset($requests) && $requests->total() > 0)
                <span class="text-sm font-semibold text-gray-600">{{ $requests->firstItem() }}-{{ $requests->lastItem() }} / {{ $requests->total() }}</span>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('employees.no_requests') }}">
        <x-slot:columns>
            <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.employee_name') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_type') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_subtype') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_from') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_to') }}</th>
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_state') }}</th>
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
            @foreach($group['items'] as $r)
            @include('employees.requests._row', ['r' => $r, 'grouped' => true])
            @endforeach
        </tbody>
        @empty
        <tbody><tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_requests') }}</td></tr></tbody>
        @endforelse
    </x-list>
    @else
    <x-list :paginator="$requests"
            :selectable="true"
            :total-count="$requests->total()"
            :model="\App\Models\Employees\EmployeeRequest::class"
            empty-text="{{ __('employees.no_requests') }}">
        <x-slot:columns>
            <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.employee_name') }}</th>
            <x-sortable-th column="type"     :label="__('employees.request_type')" class="px-3 py-2" />
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_subtype') }}</th>
            <x-sortable-th column="start_at" :label="__('employees.request_from')" class="px-3 py-2" />
            <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_to') }}</th>
            <x-sortable-th column="state"    :label="__('employees.request_state')" class="px-3 py-2" />
        </x-slot:columns>
        @foreach($requests as $r)
        @include('employees.requests._row', ['r' => $r, 'grouped' => false, 'selectable' => true])
        @endforeach
    </x-list>
    @endif
</div>
@endsection
