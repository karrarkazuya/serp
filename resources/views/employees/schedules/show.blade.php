@extends('layouts.app')
@section('title', $schedule->name)

@php
    $dayNamesMap = \App\Models\Employees\ResourceCalendar::$dayNames;

    // Infer modifier config from attendance lines
    $modDays     = $schedule->attendances->pluck('day_of_week')->unique()->sort()->values();
    $firstLine   = $schedule->attendances->first();
    $modStart    = $firstLine ? $firstLine->hour_from_formatted : null;
    $modEnd      = $firstLine ? $firstLine->hour_to_clock : null;
    $modOvernight = $firstLine && $firstLine->is_next_day;

    // Format decimal hours as HH:MM
    $fmtHours = fn(?float $v) => $v !== null
        ? sprintf('%02d:%02d', (int) $v, (int) round(($v - floor($v)) * 60))
        : '—';
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">

    {{-- Top bar --}}
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.schedules.index') }}"
               class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.schedules_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $schedule->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.schedules.edit', $schedule) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        @endcan

        @can('delete', \App\Models\Employees\Employee::class)
        <div x-data="{ confirming: false }">
            <button type="button" x-show="!confirming" @click="confirming = true"
                    class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                <form method="POST" action="{{ route('employees.schedules.delete', $schedule) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                </form>
                <button type="button" @click="confirming = false"
                        class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">{{ __('common.cancel') }}</button>
            </div>
        </div>
        @endcan
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        {{-- Main card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6"
             x-data="{ tab: 'working_hours' }">

            {{-- Title --}}
            <div class="border-b border-gray-200 pb-4 mb-5">
                <h1 class="text-xl font-semibold text-gray-900">{{ $schedule->name }}</h1>
            </div>

            {{-- Fields grid --}}
            <div class="grid grid-cols-2 gap-x-16 mb-6">
                {{-- Left --}}
                <div class="divide-y divide-gray-100">
                    <div class="flex items-center py-2.5">
                        <span class="w-48 text-sm text-gray-500 shrink-0">{{ __('employees.flexible_hours') }}</span>
                        @if($schedule->flexible_hours)
                            <svg class="w-4 h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                            </svg>
                        @else
                            <span class="w-4 h-4 rounded border border-gray-300 inline-block"></span>
                        @endif
                    </div>
                    <div class="flex items-center py-2.5">
                        <span class="w-48 text-sm text-gray-500 shrink-0">{{ __('employees.company_full_time') }}</span>
                        <span class="text-sm text-gray-900">
                            {{ $schedule->company_hours_per_week ? $fmtHours($schedule->company_hours_per_week) . ' ' . __('employees.hours_per_week') : '—' }}
                        </span>
                    </div>
                    <div class="flex items-center py-2.5">
                        <span class="w-48 text-sm text-gray-500 shrink-0">{{ __('employees.avg_hour_per_day') }}</span>
                        <span class="text-sm text-gray-900">
                            {{ $schedule->hours_per_day ? $fmtHours($schedule->hours_per_day) : '—' }}
                        </span>
                    </div>
                </div>

                {{-- Right --}}
                <div class="divide-y divide-gray-100">
                    <div class="flex items-center py-2.5">
                        <span class="w-28 text-sm text-gray-500 shrink-0">{{ __('common.company') }}</span>
                        <span class="text-sm text-gray-900">{{ $schedule->company?->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center py-2.5">
                        <span class="w-28 text-sm text-gray-500 shrink-0">{{ __('employees.timezone') }}</span>
                        <span class="text-sm text-gray-900">{{ $schedule->timezone ?? '—' }}</span>
                    </div>
                </div>
            </div>

            {{-- Tab headers --}}
            <div class="flex border-b border-gray-200 mb-5">
                <button type="button" @click="tab = 'working_hours'"
                        :class="tab === 'working_hours'
                            ? 'border-b-2 border-gray-900 text-gray-900 font-medium -mb-px'
                            : 'text-purple-600 hover:text-purple-800'"
                        class="px-4 pb-2.5 text-sm transition-colors">{{ __('employees.working_hours') }}</button>
                <button type="button" @click="tab = 'modifier'"
                        :class="tab === 'modifier'
                            ? 'border-b-2 border-gray-900 text-gray-900 font-medium -mb-px'
                            : 'text-purple-600 hover:text-purple-800'"
                        class="px-4 pb-2.5 text-sm transition-colors">{{ __('employees.modifier_tab') }}</button>
                @if($schedule->employees->isNotEmpty())
                <button type="button" @click="tab = 'employees'"
                        :class="tab === 'employees'
                            ? 'border-b-2 border-gray-900 text-gray-900 font-medium -mb-px'
                            : 'text-purple-600 hover:text-purple-800'"
                        class="px-4 pb-2.5 text-sm transition-colors">
                    {{ __('employees.employees_tab') }}
                    <span class="ml-1 text-xs text-gray-400">({{ $schedule->employees->count() }})</span>
                </button>
                @endif
            </div>

            {{-- Working Hours tab --}}
            <div x-show="tab === 'working_hours'">
                @if($schedule->attendances->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('employees.no_schedule_hours') }}</p>
                @else
                    <div class="grid grid-cols-[1fr_130px_150px] gap-2 pb-2 mb-1 border-b border-gray-200">
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.day') }}</div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.from') }}</div>
                        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('employees.to') }}</div>
                    </div>
                    @foreach($schedule->attendances as $line)
                    <div class="grid grid-cols-[1fr_130px_150px] gap-2 items-center py-2 border-b border-gray-50">
                        <div class="text-sm text-gray-900 font-medium">{{ $line->day_name }}</div>
                        <div class="text-sm text-gray-600">{{ $line->hour_from_formatted }}</div>
                        <div class="flex items-center gap-1.5">
                            <span class="text-sm text-gray-600">{{ $line->hour_to_clock }}</span>
                            @if($line->is_next_day)
                            <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-1.5 py-0.5 rounded">+1</span>
                            @endif
                        </div>
                    </div>
                    @endforeach
                @endif
            </div>

            {{-- Modifier tab (read-only) --}}
            <div x-show="tab === 'modifier'" style="display:none">
                @if($schedule->attendances->isEmpty())
                    <p class="text-sm text-gray-400 py-6 text-center">{{ __('employees.no_modifier') }}</p>
                @else
                    <div class="grid grid-cols-2 gap-x-16">
                        {{-- Working Days --}}
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('employees.working_days') }}</p>
                            <div class="flex gap-10">
                                <div class="space-y-3">
                                    @foreach(array_slice($dayNamesMap, 0, 4, true) as $num => $dayLabel)
                                    <div class="flex items-center gap-2.5">
                                        <input type="checkbox" disabled
                                               {{ $modDays->contains($num) ? 'checked' : '' }}
                                               class="w-4 h-4 rounded border-gray-300 text-purple-600">
                                        <span class="text-sm text-gray-700">{{ $dayLabel }}</span>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="space-y-3">
                                    @foreach(array_slice($dayNamesMap, 4, 3, true) as $num => $dayLabel)
                                    <div class="flex items-center gap-2.5">
                                        <input type="checkbox" disabled
                                               {{ $modDays->contains($num) ? 'checked' : '' }}
                                               class="w-4 h-4 rounded border-gray-300 text-purple-600">
                                        <span class="text-sm text-gray-700">{{ $dayLabel }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- Working Hours --}}
                        <div>
                            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">{{ __('employees.working_hours') }}</p>
                            <div class="space-y-3">
                                <div class="flex items-center gap-6">
                                    <span class="text-sm text-gray-500 w-24">{{ __('employees.start_time') }}</span>
                                    <span class="text-sm text-gray-900 font-medium">{{ $modStart ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-6">
                                    <span class="text-sm text-gray-500 w-24">{{ __('employees.end_time') }}</span>
                                    <span class="text-sm text-gray-900 font-medium">{{ $modEnd ?? '—' }}</span>
                                    @if($modOvernight)
                                    <span class="text-xs font-semibold text-amber-700 bg-amber-100 px-2 py-0.5 rounded">{{ __('employees.next_day') }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Employees tab --}}
            @if($schedule->employees->isNotEmpty())
            <div x-show="tab === 'employees'" style="display:none">
                <div class="divide-y divide-gray-100">
                    @foreach($schedule->employees as $emp)
                    <div class="flex items-center gap-3 py-2.5">
                        <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                            {{ mb_strtoupper(mb_substr($emp->name, 0, 1)) }}
                        </div>
                        <div class="flex-1 min-w-0">
                            <a href="{{ route('employees.show', $emp) }}"
                               class="text-sm font-medium text-gray-900 hover:text-purple-700">{{ $emp->name }}</a>
                            @if($emp->job)
                            <p class="text-xs text-gray-500">{{ $emp->job->name }}</p>
                            @endif
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        {{-- Chatter --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\ResourceCalendar"
                :model-id="$schedule->id"
                :can-comment="auth()->user()->can('update', \App\Models\Employees\Employee::class)"
                :comment-url="route('employees.schedules.comment', $schedule)"
            />
        </div>
    </div>
</div>
@endsection
