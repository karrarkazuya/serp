@extends('layouts.app')
@section('title', $attendance->employee?->name . ' — ' . $attendance->attendance_date?->format('M d, Y'))

@php $unit = __('employees.hours_unit'); @endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.attendances.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.attendances_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $attendance->employee?->name }} — {{ $attendance->attendance_date?->format('M d, Y') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $attendance)
                <a href="{{ route('employees.attendances.edit', $attendance) }}"
                   class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
                @endcan

                {{-- No delete on purpose: attendance is immutable history. --}}
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">{{ $attendance->employee?->name }}</h1>
                    <p class="text-sm text-gray-500 mt-0.5">{{ $attendance->attendance_date?->format('l, M d, Y') }}</p>
                </div>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $attendance->status_color }}-100 text-{{ $attendance->status_color }}-700">
                    @php
                        $statusKey = $attendance->is_day_off ? 'day_off' : ($attendance->is_absence ? 'absence' : 'present');
                    @endphp
                    {{ __('employees.attendance_status_' . $statusKey) }}
                </span>
            </div>

            @if($attendance->request)
            {{-- Surface the linked request so HR knows why expected_hours
                 was reduced (time off) or why the row is tagged not-absence
                 (leave). One-click navigation to the parent request. --}}
            <div class="mb-4 rounded-lg border border-purple-200 bg-purple-50/40 p-3 text-sm flex items-center justify-between">
                <span class="text-gray-700">
                    @if($attendance->request->type === 'time_off')
                        {{ __('employees.request_attendance_note', ['hours' => number_format((float) $attendance->request->duration_hours, 2)]) }}
                    @elseif($attendance->request->type === 'overtime')
                        {{ __('employees.request_attendance_overtime_note') }}
                    @else
                        {{ __('employees.request_attendance_leave_note') }}
                    @endif
                    <span class="text-gray-500"> — {{ $attendance->request->subtype?->name }}</span>
                </span>
                <a href="{{ route('employees.requests.show', $attendance->request) }}" class="text-purple-700 hover:text-purple-900 font-semibold">{{ __('employees.view_request') }} →</a>
            </div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8">
                <div>
                    @foreach([
                        [__('employees.work_location'), $attendance->employee?->department?->name],
                        [__('employees.job_position'),  $attendance->employee?->job?->name],
                        [__('employees.schedule'),      $attendance->resourceCalendar?->name],
                        [__('common.company'),          $attendance->company?->name],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                    </div>
                    @endforeach
                </div>
                <div>
                    @foreach([
                        [__('employees.check_in'),           $attendance->check_in?->format('H:i:s')],
                        [__('employees.check_out'),          $attendance->check_out?->format('H:i:s')],
                        [__('employees.expected_check_in'),  $attendance->expected_check_in?->format('H:i')],
                        [__('employees.expected_check_out'), $attendance->expected_check_out?->format('H:i')],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.expected_hours') }}</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) $attendance->expected_hours, 2) }} {{ $unit }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-3">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.worked_hours_label') }}</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ number_format((float) $attendance->worked_hours, 2) }} {{ $unit }}</p>
                </div>
                <div class="rounded-lg border border-green-200 bg-green-50/40 p-3">
                    <p class="text-xs font-semibold text-green-600 uppercase tracking-wide">{{ __('employees.overtime_hours') }}</p>
                    <p class="text-xl font-bold text-green-700 mt-1">{{ number_format((float) $attendance->overtime_hours, 2) }} {{ $unit }}</p>
                </div>
                <div class="rounded-lg border border-red-200 bg-red-50/40 p-3">
                    <p class="text-xs font-semibold text-red-600 uppercase tracking-wide">{{ __('employees.shortage_hours') }}</p>
                    <p class="text-xl font-bold text-red-700 mt-1">{{ number_format((float) $attendance->shortage_hours, 2) }} {{ $unit }}</p>
                </div>
            </div>

            @if((float) $attendance->approved_overtime_hours > 0)
            {{-- approved_overtime_hours is a separate bucket — distinct from
                 the over-working overtime above. Per spec: keep both. --}}
            <div class="mt-3 rounded-lg border border-blue-200 bg-blue-50/40 p-3 flex items-center justify-between">
                <span class="text-xs font-semibold text-blue-600 uppercase tracking-wide">{{ __('employees.approved_overtime_hours_label') }}</span>
                <span class="text-xl font-bold text-blue-700">{{ number_format((float) $attendance->approved_overtime_hours, 2) }} {{ $unit }}</span>
            </div>
            @endif

            @if($attendance->notes)
            <div class="mt-6">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.attendance_notes') }}</p>
                <p class="text-sm text-gray-800 mt-1 whitespace-pre-line">{{ $attendance->notes }}</p>
            </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\Attendance"
                :model-id="$attendance->id"
                :can-comment="auth()->user()->can('comment', $attendance)"
                :comment-url="route('employees.attendances.comment', $attendance)"
            />
        </div>
    </div>
</div>
@endsection
