@extends('layouts.app')
@section('title', __('employees.requests_title') . ' #' . $employeeRequest->id)

@php
    $r       = $employeeRequest;
    $canMgr  = auth()->user()->can('approveAsManager', $r);
    $canHr   = auth()->user()->can('approveAsHr',      $r);
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            @if($fromMy ?? false)
                <a href="{{ route('employees.my-requests') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.my_requests_title') }}</a>
            @else
                <a href="{{ route('employees.requests.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.requests_title') }}</a>
            @endif
            <span class="text-sm font-semibold text-gray-800">#{{ $r->id }} — {{ $r->employee?->name }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if(session('error'))
            <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">{{ session('error') }}</div>
        @endif
        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-4">
                <h1 class="text-2xl font-bold text-gray-900">{{ $r->employee?->name }}</h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $r->state_color }}-100 text-{{ $r->state_color }}-700">{{ __('employees.request_state_' . $r->state) }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-50 text-purple-700">{{ __('employees.' . $r->type) }}</span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-3">
                @foreach([
                    [__('employees.request_subtype'),       $r->subtype?->name],
                    [__('common.company'),                  $r->company?->name],
                    [__('employees.request_from'),          $r->start_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d, Y H:i')],
                    [__('employees.request_to'),            $r->end_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d, Y H:i')],
                    [__('employees.request_duration_days'),  $r->type === 'leave' ? number_format((float) $r->duration_days, 2) : null],
                    [__('employees.request_duration_hours'), $r->type !== 'leave' ? number_format((float) $r->duration_hours, 2) : null],
                    ['Title',       $r->title],
                    ['Description', $r->description],
                ] as [$label, $value])
                @if($value !== null && $value !== '')
                <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                    <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                    <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
                </div>
                @endif
                @endforeach

                @if($r->attachment)
                <div class="flex items-center gap-4 py-1.5 border-b border-gray-100 sm:col-span-2">
                    <span class="w-44 shrink-0 text-sm text-gray-500">Attachment</span>
                    <a href="{{ route('files.serve', $r->attachment) }}" class="text-sm text-purple-700 hover:underline">Open</a>
                </div>
                @endif
            </div>
        </div>

        {{-- Approval lanes --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            @foreach(['manager' => __('employees.request_manager_approval'), 'hr' => __('employees.request_hr_approval')] as $role => $heading)
            @php
                $status = $r->{$role . '_status'};
                $by     = $r->{$role . '_decision_by'};
                $at     = $r->{$role . '_decision_at'};
                $why    = $r->{$role . '_decision_reason'};
                $color  = ['pending' => 'gray', 'approved' => 'green', 'rejected' => 'red'][$status];
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">{{ $heading }}</h3>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-700">{{ __('employees.request_state_' . $status) }}</span>
                </div>
                @if($at)
                    <p class="text-xs text-gray-500 mb-2">{{ $at->format('M d, Y H:i') }} · {{ ($role === 'manager' ? $r->managerDecisionUser : $r->hrDecisionUser)?->name }}</p>
                    @if($why)<p class="text-sm text-gray-700 whitespace-pre-line">{{ $why }}</p>@endif
                @endif

                @if(!$r->isLocked() && (($role === 'manager' && $canMgr) || ($role === 'hr' && $canHr)))
                <div x-data="{ rejecting: false }" class="mt-3 space-y-2">
                    <form method="POST" action="{{ route('employees.requests.decide', $r) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="hidden" name="decision" value="approve">
                        <button class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded">{{ __('employees.request_action_approve') }}</button>
                        <button type="button" @click="rejecting = !rejecting" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('employees.request_action_reject') }}</button>
                    </form>
                    <form x-show="rejecting" style="display:none" method="POST" action="{{ route('employees.requests.decide', $r) }}" class="space-y-2">
                        @csrf
                        <input type="hidden" name="decision" value="reject">
                        <textarea name="reason" required rows="2" placeholder="{{ __('employees.request_reject_reason_placeholder') }}" class="w-full border border-gray-200 rounded p-2 text-sm"></textarea>
                        <button class="px-3 py-1.5 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded">{{ __('employees.request_action_reject') }}</button>
                    </form>
                </div>
                @endif
            </div>
            @endforeach
        </div>

        @if($r->attendances->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.linked_attendance_rows') }}</h3>
            </div>
            <table class="w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.attendance_date') }}</th>
                        <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.check_in') }}</th>
                        <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.check_out') }}</th>
                        <th class="text-end px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.expected_hours') }}</th>
                        <th class="text-end px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.worked_hours_label') }}</th>
                        @if($r->type === 'overtime')
                        <th class="text-end px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.approved_overtime_hours_label') }}</th>
                        @endif
                        <th class="text-end px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($r->attendances->sortBy('attendance_date') as $a)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-gray-800">{{ $a->attendance_date->format('M d, Y') }}</td>
                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $a->check_in?->format('H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-600 whitespace-nowrap">{{ $a->check_out?->format('H:i') ?? '—' }}</td>
                        <td class="px-3 py-2 text-gray-800 text-end">{{ number_format((float) $a->expected_hours, 2) }}</td>
                        <td class="px-3 py-2 text-gray-800 text-end">{{ number_format((float) $a->worked_hours, 2) }}</td>
                        @if($r->type === 'overtime')
                        <td class="px-3 py-2 text-blue-700 font-medium text-end">{{ number_format((float) $a->approved_overtime_hours, 2) }}</td>
                        @endif
                        <td class="px-3 py-2 text-end">
                            <a href="{{ route('employees.attendances.show', $a) }}" class="text-xs text-purple-700 hover:text-purple-900">{{ __('employees.view_attendance') }} →</a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\EmployeeRequest"
                :model-id="$r->id"
                :can-comment="auth()->user()->can('comment', $r)"
                :comment-url="route('employees.requests.comment', $r)"
            />
        </div>
    </div>
</div>
@endsection
