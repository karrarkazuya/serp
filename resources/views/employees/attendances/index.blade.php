@extends('layouts.app')
@section('title', __('employees.attendances_title'))

@php
    $quickFilters = [
        ['label' => __('employees.attendance_filter_all'),      'params' => ['filter' => ''],         'url' => route('employees.attendances.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('employees.attendance_filter_absences'), 'params' => ['filter' => 'absences'], 'url' => route('employees.attendances.index', array_merge(request()->except('page'), ['filter' => 'absences']))],
        ['label' => __('employees.attendance_filter_overtime'), 'params' => ['filter' => 'overtime'], 'url' => route('employees.attendances.index', array_merge(request()->except('page'), ['filter' => 'overtime']))],
        ['label' => __('employees.attendance_filter_shortage'), 'params' => ['filter' => 'shortage'], 'url' => route('employees.attendances.index', array_merge(request()->except('page'), ['filter' => 'shortage']))],
        ['label' => __('employees.attendance_filter_day_off'),  'params' => ['filter' => 'day_off'],  'url' => route('employees.attendances.index', array_merge(request()->except('page'), ['filter' => 'day_off']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Attendance::class)
        <a href="{{ route('employees.attendances.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('employees.new_attendance') }}
        </a>
        @endcan

        @can('export', \App\Models\Employees\Attendance::class)
        <x-export
            :fields="config('exportable.attendance.fields', [])"
            :export-url="route('export')"
            model-key="attendance"
        />
        @endcan

        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('employees.attendances_title') }}</span>

        <x-search
            :model="\App\Models\Employees\Attendance::class"
            :action="route('employees.attendances.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $groups->sum('count') }}</span>
            @elseif($attendances->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $attendances->firstItem() }}-{{ $attendances->lastItem() }} / {{ $attendances->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if($attendances->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $attendances->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($attendances->hasMorePages())
                    <a href="{{ $attendances->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('employees.no_attendances') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.attendance_date') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.employee_name') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.check_in') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.check_out') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-end">{{ __('employees.worked_hours_label') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-end">{{ __('employees.overtime_hours') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-end">{{ __('employees.shortage_hours') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.attendance_status') }}</th>
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
            @foreach($group['items'] as $attendance)
            @include('employees.attendances._row', ['attendance' => $attendance, 'grouped' => true])
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_attendances') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>
    @else
    <x-list :paginator="$attendances"
            :selectable="true"
            :total-count="$attendances->total()"
            :can-export="auth()->user()->can('export', \App\Models\Employees\Attendance::class)"
            :empty-text="__('employees.no_attendances')">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('employees.attendance_date')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.employee_name') }}</th>
            <x-sortable-th column="check_in"  :label="__('employees.check_in')"  class="px-3 py-2" />
            <x-sortable-th column="check_out" :label="__('employees.check_out')" class="px-3 py-2" />
            <x-sortable-th column="worked"    :label="__('employees.worked_hours_label')" class="px-3 py-2 text-end" />
            <x-sortable-th column="overtime"  :label="__('employees.overtime_hours')"     class="px-3 py-2 text-end" />
            <x-sortable-th column="shortage"  :label="__('employees.shortage_hours')"     class="px-3 py-2 text-end" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wider text-start">{{ __('employees.attendance_status') }}</th>
        </x-slot:columns>

        @foreach($attendances as $attendance)
        @include('employees.attendances._row', ['attendance' => $attendance, 'grouped' => false, 'selectable' => true])
        @endforeach
    </x-list>
    @endif
</div>
@endsection
