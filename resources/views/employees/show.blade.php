@extends('layouts.app')
@section('title', $employee->name)

@php
    $statusColor  = \App\Models\Employees\Employee::employmentStatusColor($employee->employment_status);
    $statusColors = ['green' => 'text-green-700 bg-green-100', 'blue' => 'text-blue-700 bg-blue-100', 'orange' => 'text-orange-700 bg-orange-100', 'red' => 'text-red-700 bg-red-100', 'gray' => 'text-gray-700 bg-gray-100'];
    $contractColors = ['draft' => 'text-gray-600 bg-gray-100', 'open' => 'text-green-700 bg-green-100', 'close' => 'text-red-700 bg-red-100', 'cancelled' => 'text-amber-700 bg-amber-100'];

    // Helper: decimal hours → "HH:MM" string
    $decToTime = fn($dec) => sprintf('%02d:%02d', (int)$dec, round(($dec - (int)$dec) * 60));

    // Planned schedule — generate next 30 days
    $scheduleDays = [];
    if ($employee->resourceCalendar && $employee->resourceCalendar->attendances->isNotEmpty()) {
        $attByDow = $employee->resourceCalendar->attendances->groupBy('day_of_week');
        for ($i = 0; $i < 30; $i++) {
            $date = now()->addDays($i);
            // Carbon: 0=Sun..6=Sat → our: 0=Sat,1=Sun,2=Mon..6=Fri
            $ourDow = ($date->dayOfWeek + 1) % 7;
            $lines  = $attByDow->get($ourDow, collect());
            $scheduleDays[] = ['date' => $date, 'lines' => $lines, 'dow' => $ourDow];
        }
    }

@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Employees\Employee::class)
        @php $newHref = route('employees.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('employees.show', $prevId) : null"
        :next-href="$nextId ? route('employees.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('employees.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $employee->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            @can('update', $employee)
            <a href="{{ route('employees.edit', $employee) }}" class="shrink-0 px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @if($employee->active)
            <form method="POST" action="{{ route('employees.archive', $employee) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('common.archive') }}</button>
            </form>
            @else
            <form method="POST" action="{{ route('employees.unarchive', $employee) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('common.restore') }}</button>
            </form>
            @endif
            @endcan
            @can('delete', $employee)
            <div x-data="{ confirming: false }" class="shrink-0">
                <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.delete', $employee) }}">
                        @csrf @method('DELETE')
                        <button class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                    </form>
                    <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-200 rounded">{{ __('common.cancel') }}</button>
                </div>
            </div>
            @endcan
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        @if(session('success'))
        <div class="mx-4 mt-4 px-4 py-2 bg-green-50 border border-green-200 rounded-lg text-sm text-green-700">{{ session('success') }}</div>
        @endif
        @if(session('error'))
        <div class="mx-4 mt-4 px-4 py-2 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700">{{ session('error') }}</div>
        @endif

        @if(!$employee->active)
        <div class="mx-4 mt-4">
            <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('employees.employee_archived') }}</div>
        </div>
        @endif

        {{-- Smart buttons --}}
        <div class="mx-4 mt-4 flex flex-wrap gap-2">
            <a href="#contracts" @click.prevent="document.getElementById('tab-contracts')?.click()" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm hover:border-purple-300 shadow-sm">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                <span class="font-semibold text-gray-700">{{ $employee->contracts->count() }}</span>
                <span class="text-gray-500">{{ __('employees.contracts_tab') }}</span>
            </a>
            <a href="#skills" @click.prevent="document.getElementById('tab-skills')?.click()" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm hover:border-purple-300 shadow-sm">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"/></svg>
                <span class="font-semibold text-gray-700">{{ $employee->skills->count() }}</span>
                <span class="text-gray-500">{{ __('employees.skills_tab') }}</span>
            </a>
            <a href="#documents" @click.prevent="document.getElementById('tab-documents')?.click()" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm hover:border-purple-300 shadow-sm">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span class="font-semibold text-gray-700">{{ $employee->documents->count() }}</span>
                <span class="text-gray-500">{{ __('employees.documents_tab') }}</span>
            </a>
            @if($employee->subordinates->count() > 0)
            <a href="{{ route('employees.index', ['parent_id' => $employee->id]) }}" class="flex items-center gap-2 px-3 py-1.5 bg-white border border-gray-200 rounded-lg text-sm hover:border-purple-300 shadow-sm">
                <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="font-semibold text-gray-700">{{ $employee->subordinates->count() }}</span>
                <span class="text-gray-500">{{ __('employees.subordinates_tab') }}</span>
            </a>
            @endif
        </div>

        {{-- Main card --}}
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="p-6">
                {{-- Header: Name + key fields + avatar --}}
                <div class="flex gap-6 items-start mb-4">
                    <div class="flex-1 min-w-0">
                        <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold mb-2 {{ $statusColors[$statusColor] ?? $statusColors['gray'] }}">
                            {{ \App\Models\Employees\Employee::employmentStatusLabel($employee->employment_status) }}
                        </span>
                        <h1 class="text-3xl font-bold text-gray-900 leading-tight">{{ $employee->name }}</h1>
                        @if($employee->job_title ?? $employee->job?->name)
                            <p class="text-sm text-gray-500 mt-0.5">{{ $employee->job_title ?? $employee->job?->name }}</p>
                        @endif
                    </div>
                    <div class="shrink-0">
                        @if($employee->avatar_url)
                            <img src="{{ $employee->avatar_url }}" alt="{{ $employee->name }}" class="w-28 h-28 object-cover rounded-xl border border-gray-200 shadow-sm">
                        @else
                            <div class="w-28 h-28 rounded-xl flex items-center justify-center text-4xl font-bold bg-purple-100 text-purple-700">
                                {{ strtoupper(substr($employee->name, 0, 2)) }}
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Two-column key fields (Odoo-style above tabs) --}}
                <div class="flex gap-6 mb-4">
                    <div class="flex-1 space-y-0">
                        @foreach([
                            [__('employees.scientific_title'), $employee->scientific_title],
                            [__('employees.full_name_ar'),  $employee->name_ar],
                            [__('employees.full_name_en'),  $employee->name_en],
                            [__('employees.family_name'),   $employee->family_name],
                            [__('employees.mother_name'),   $employee->mother_name],
                            [__('employees.work_email'),    $employee->work_email],
                            [__('employees.work_phone'),    $employee->work_phone],
                            [__('employees.work_mobile'),   $employee->work_mobile],
                            [__('common.company'),          $employee->company?->name],
                        ] as [$label, $value])
                        @if($value)
                        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                            <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
                        </div>
                        @endif
                        @endforeach
                        @if($employee->categories->isNotEmpty())
                        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                            <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.tags') }}</span>
                            <div class="flex flex-wrap gap-1.5 flex-1">
                                @foreach($employee->categories as $cat)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: {{ $cat->color }}">{{ $cat->name }}</span>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                    <div class="flex-1 space-y-0">
                        @if($employee->department)
                        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                            <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('common.department') }}</span>
                            <a href="{{ route('employees.departments.show', $employee->department) }}" class="flex-1 text-sm text-purple-600 hover:underline">{{ $employee->department->name }}</a>
                        </div>
                        @endif
                        @if($employee->job)
                        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                            <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.job_position') }}</span>
                            <a href="{{ route('employees.jobs.show', $employee->job) }}" class="flex-1 text-sm text-purple-600 hover:underline">{{ $employee->job->name }}</a>
                        </div>
                        @endif
                        @if($employee->manager)
                        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                            <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('common.manager') }}</span>
                            <a href="{{ route('employees.show', $employee->manager) }}" class="flex-1 text-sm text-purple-600 hover:underline">{{ $employee->manager->name }}</a>
                        </div>
                        @endif
                        @if($employee->coach)
                        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                            <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.coach') }}</span>
                            <a href="{{ route('employees.show', $employee->coach) }}" class="flex-1 text-sm text-purple-600 hover:underline">{{ $employee->coach->name }}</a>
                        </div>
                        @endif
                    </div>
                </div>

                {{-- Tabs --}}
                <div x-data="{ tab: 'work' }" class="border-t border-gray-200">
                    <div class="flex items-end gap-1 pt-3 border-b border-gray-200 overflow-x-auto">
                        @php
                            $tabs = [
                                ['work',     __('employees.work_info')],
                                ['private',  __('employees.private_info')],
                                ['hr',       __('employees.hr_settings')],
                                ['contracts',__('employees.contracts_tab')],
                                ['skills',   __('employees.skills_tab')],
                                ['documents',__('employees.documents_tab')],
                                ['schedule', __('employees.planned_schedule')],
                            ];
                            if (auth()->user()->hasPermission('attendance.requests.read')) {
                                $tabs[] = ['requests', __('employees.requests_title')];
                            }
                        @endphp
                        @foreach($tabs as [$key, $label])
                        <button type="button"
                                @click="tab = '{{ $key }}'"
                                id="tab-{{ $key }}"
                                class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white shrink-0"
                                :class="tab === '{{ $key }}' ? 'text-gray-900 border-gray-300 -mb-px pb-2.25' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                            {{ $label }}
                        </button>
                        @endforeach
                    </div>

                    <div class="min-h-64">

                        {{-- Work Information --}}
                        <div x-show="tab === 'work'" style="display:none" class="p-6">
                            <div class="flex gap-8">
                                <div class="flex-1">
                                    <p class="text-xs font-semibold text-gray-400 uppercase mb-2">{{ __('employees.location') }}</p>
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.work_location') }}</span>
                                        <span class="flex-1 text-sm">
                                            @if($employee->workLocation)
                                                <a href="{{ route('employees.work-locations.show', $employee->workLocation) }}" class="text-purple-600 hover:underline">{{ $employee->workLocation->name }}</a>
                                            @else <span class="text-gray-400">—</span>@endif
                                        </span>
                                    </div>

                                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.approvers') }}</p>
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.expense_approver') }}</span>
                                        <span class="flex-1 text-sm">
                                            @if($employee->expenseManager)
                                                <a href="{{ route('employees.show', $employee->expenseManager) }}" class="text-purple-600 hover:underline">{{ $employee->expenseManager->name }}</a>
                                            @else <span class="text-gray-400">—</span>@endif
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.attendance_approver') }}</span>
                                        <span class="flex-1 text-sm">
                                            @if($employee->attendanceManager)
                                                <a href="{{ route('employees.show', $employee->attendanceManager) }}" class="text-purple-600 hover:underline">{{ $employee->attendanceManager->name }}</a>
                                            @else <span class="text-gray-400">—</span>@endif
                                        </span>
                                    </div>

                                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.schedule') }}</p>
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.working_hours') }}</span>
                                        <span class="flex-1 text-sm">
                                            @if($employee->resourceCalendar)
                                                <a href="{{ route('employees.schedules.show', $employee->resourceCalendar) }}" class="text-purple-600 hover:underline">{{ $employee->resourceCalendar->name }}</a>
                                            @else <span class="text-gray-400">—</span>@endif
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.timezone') }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $employee->timezone ?: '—' }}</span>
                                    </div>
                                </div>

                                {{-- Organization Chart --}}
                                @if(count($chain) > 0 || $employee->subordinates->isNotEmpty())
                                <div class="flex-1">
                                    <p class="text-xs font-semibold text-gray-400 uppercase mb-3">{{ __('employees.org_chart') }}</p>
                                    <div class="space-y-1">
                                        @foreach($chain as $m)
                                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg hover:bg-gray-50">
                                            @if($m->avatar_url)
                                                <img src="{{ $m->avatar_url }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                                            @else
                                                <div class="w-8 h-8 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0">{{ strtoupper(substr($m->name, 0, 2)) }}</div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('employees.show', $m) }}" class="text-sm font-medium text-gray-900 hover:text-purple-700 truncate block">{{ $m->name }}</a>
                                                <p class="text-xs text-gray-400 truncate">{{ $m->job_title ?? $m->job?->name }}</p>
                                            </div>
                                            <span class="text-xs text-gray-400 shrink-0">{{ $m->subordinates_count }}</span>
                                        </div>
                                        @endforeach

                                        {{-- Current employee --}}
                                        <div class="flex items-center gap-2 px-3 py-2 rounded-lg bg-purple-50 border border-purple-200">
                                            @if($employee->avatar_url)
                                                <img src="{{ $employee->avatar_url }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                                            @else
                                                <div class="w-8 h-8 rounded-full bg-purple-200 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">{{ strtoupper(substr($employee->name, 0, 2)) }}</div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <p class="text-sm font-semibold text-purple-800 truncate">{{ $employee->name }}</p>
                                                <p class="text-xs text-purple-500 truncate">{{ $employee->job_title ?? $employee->job?->name }}</p>
                                            </div>
                                            <span class="text-xs text-purple-500 shrink-0">{{ $employee->subordinates->count() }}</span>
                                        </div>

                                        @foreach($employee->subordinates->take(5) as $sub)
                                        <div class="flex items-center gap-2 px-3 py-2 ml-6 rounded-lg hover:bg-gray-50">
                                            @if($sub->avatar_url)
                                                <img src="{{ $sub->avatar_url }}" class="w-7 h-7 rounded-full object-cover shrink-0">
                                            @else
                                                <div class="w-7 h-7 rounded-full bg-gray-200 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0">{{ strtoupper(substr($sub->name, 0, 2)) }}</div>
                                            @endif
                                            <div class="min-w-0 flex-1">
                                                <a href="{{ route('employees.show', $sub) }}" class="text-sm font-medium text-gray-900 hover:text-purple-700 truncate block">{{ $sub->name }}</a>
                                                <p class="text-xs text-gray-400 truncate">{{ $sub->job_title ?? $sub->job?->name }}</p>
                                            </div>
                                        </div>
                                        @endforeach
                                        @if($employee->subordinates->count() > 5)
                                        <p class="text-xs text-gray-400 ml-9">+{{ $employee->subordinates->count() - 5 }} more</p>
                                        @endif
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        {{-- Private Information --}}
                        <div x-show="tab === 'private'" style="display:none" class="p-6">
                            <div class="flex gap-8">
                                <div class="flex-1">
                                    <p class="text-xs font-semibold text-gray-400 uppercase mb-2">{{ __('employees.private_contact') }}</p>
                                    @if($employee->private_address)
                                    <div class="flex items-start gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.private_address') }}</span>
                                        <span class="flex-1 text-sm text-gray-800 whitespace-pre-line">{{ $employee->private_address }}</span>
                                    </div>
                                    @endif
                                    @foreach([
                                        [__('employees.private_email'),       $employee->private_email],
                                        [__('employees.private_phone'),       $employee->private_phone],
                                        [__('employees.private_mobile'),      $employee->private_mobile],
                                        [__('employees.home_work_distance'),  $employee->km_home_work ? $employee->km_home_work . ' ' . __('employees.km') : null],
                                        [__('employees.private_car_plate'),   $employee->private_car_plate],
                                    ] as [$label, $value])
                                    @if($value)
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value }}</span>
                                    </div>
                                    @endif
                                    @endforeach

                                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.emergency') }}</p>
                                    @foreach([
                                        [__('employees.contact_name'), $employee->emergency_contact],
                                        [__('common.phone'),           $employee->emergency_phone],
                                        [__('employees.relationship'), $employee->emergency_relation],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach

                                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.family_status') }}</p>
                                    @foreach([
                                        [__('employees.marital_status'),    $employee->marital_status ? ucfirst($employee->marital_status) : null],
                                        [__('employees.dependent_children'), $employee->children ?: null],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach
                                </div>

                                <div class="flex-1">
                                    <p class="text-xs font-semibold text-gray-400 uppercase mb-2">{{ __('employees.citizenship') }}</p>
                                    @foreach([
                                        [__('employees.nationality'),       $employee->nationality],
                                        [__('employees.identification_no'), $employee->identification_id],
                                        [__('employees.ssn_no'),            $employee->ssnid],
                                        [__('employees.passport_no'),       $employee->passport_id],
                                        [__('employees.gender'),            $employee->gender ? ucfirst($employee->gender) : null],
                                        [__('employees.date_of_birth'),     $employee->birthday?->format('d M Y')],
                                        [__('employees.place_of_birth'),    $employee->place_of_birth],
                                        [__('employees.country_of_birth'),  $employee->country_of_birth],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach

                                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.education') }}</p>
                                    @foreach([
                                        [__('employees.certificate_level'), $employee->certificate_level ? ucfirst($employee->certificate_level) : null],
                                        [__('employees.field_of_study'),    $employee->study_field],
                                        [__('employees.school'),            $employee->study_school],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach

                                    <p class="text-xs font-semibold text-gray-400 uppercase mt-4 mb-2">{{ __('employees.work_permit') }}</p>
                                    @foreach([
                                        [__('employees.visa_no'),           $employee->visa_no],
                                        [__('employees.work_permit_no'),    $employee->work_permit_no],
                                        [__('employees.visa_expiration'),   $employee->visa_expire?->format('d M Y')],
                                        [__('employees.permit_expiration'), $employee->work_permit_expiration_date?->format('d M Y')],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>

                        {{-- HR Settings --}}
                        <div x-show="tab === 'hr'" style="display:none" class="p-6">
                            <div class="flex gap-8">
                                <div class="flex-1">
                                    @foreach([
                                        [__('common.status'),             \App\Models\Employees\Employee::employmentStatusLabel($employee->employment_status)],
                                        [__('employees.employee_code'),   $employee->employee_code],
                                        [__('employees.hire_date'),       $employee->hire_date?->format('d M Y')],
                                        [__('employees.first_contract'),  $employee->first_contract_date?->format('d M Y')],
                                        [__('employees.end_date'),        $employee->end_date?->format('d M Y')],
                                        [__('employees.probation_start'), $employee->probation_start_date?->format('d M Y')],
                                        [__('employees.probation_end'),   $employee->probation_end_date?->format('d M Y')],
                                        [__('employees.departure_date'),  $employee->departure_date?->format('d M Y')],
                                        [__('employees.departure_reason'),$employee->departureReason?->name],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach
                                </div>
                                <div class="flex-1">
                                    @foreach([
                                        [__('employees.current_contract'), $employee->currentContract?->name],
                                        [__('employees.wage'),             $employee->wage ? number_format($employee->wage, 2) : null],
                                        [__('employees.payment_method'),   $employee->payment_method ? ucwords(str_replace('_', ' ', $employee->payment_method)) : null],
                                    ] as [$label, $value])
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                                    </div>
                                    @endforeach
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.linked_user') }}</span>
                                        <span class="flex-1 text-sm">
                                            @if($employee->user) <span class="text-gray-800">{{ $employee->user->name }}</span>
                                            @else <span class="text-gray-400">—</span>@endif
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
                                        <span class="w-44 shrink-0 text-sm text-gray-500">{{ __('employees.linked_contact') }}</span>
                                        <span class="flex-1 text-sm">
                                            @if($employee->contact)
                                                <a href="{{ route('contacts.show', $employee->contact) }}" class="text-purple-600 hover:underline">{{ $employee->contact->name }}</a>
                                            @else <span class="text-gray-400">—</span>@endif
                                        </span>
                                    </div>
                                    @if($employee->departure_description)
                                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-semibold text-gray-500 mb-1">{{ __('employees.departure_notes') }}</p>
                                        <p class="text-sm text-gray-700">{{ $employee->departure_description }}</p>
                                    </div>
                                    @endif
                                    @if($employee->notes)
                                    <div class="mt-4 p-3 bg-gray-50 rounded-lg">
                                        <p class="text-xs font-semibold text-gray-500 mb-1">{{ __('common.notes') }}</p>
                                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $employee->notes }}</p>
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Contracts --}}
                        <div x-show="tab === 'contracts'" id="contracts" style="display:none" class="p-6">
                            @can('create', \App\Models\Employees\Contract::class)
                            <div class="mb-4" x-data="{ open: false }">
                                <button type="button" @click="open = !open" class="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-purple-700 border border-purple-200 rounded hover:bg-purple-50">
                                    {{ __('employees.add_contract') }}
                                </button>
                                <div x-show="open" style="display:none" class="mt-3 p-4 border border-gray-200 rounded-lg bg-gray-50">
                                    <form method="POST" action="{{ route('employees.contracts.store', $employee) }}" enctype="multipart/form-data">
                                        @csrf
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('employees.contract_name') }}</label>
                                                <input type="text" name="name" required class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('employees.doc_type') }}</label>
                                                <select name="contract_type" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                                    @foreach(['full_time' => 'Full Time', 'part_time' => 'Part Time', 'temporary' => 'Temporary', 'internship' => 'Internship', 'contractor' => 'Contractor'] as $k => $v)
                                                        <option value="{{ $k }}">{{ $v }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('employees.start_date') }}</label>
                                                <input type="date" name="date_start" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('employees.end_date') }}</label>
                                                <input type="date" name="date_end" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('employees.wage') }}</label>
                                                <input type="number" name="wage" step="0.01" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:outline-none" placeholder="0.00">
                                            </div>
                                            <div>
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('common.status') }}</label>
                                                <select name="state" class="w-full px-2 py-1.5 text-sm border border-gray-300 rounded focus:ring-1 focus:ring-purple-500 focus:outline-none">
                                                    @foreach(['draft' => 'Draft', 'open' => 'Open', 'close' => 'Closed', 'cancelled' => 'Cancelled'] as $k => $v)
                                                        <option value="{{ $k }}">{{ $v }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                            <div class="sm:col-span-2">
                                                <label class="block text-xs text-gray-500 mb-1">{{ __('employees.contract_image') }}</label>
                                                <input type="file" name="image" accept="image/*" class="w-full text-sm text-gray-600 file:mr-3 file:py-1 file:px-3 file:rounded file:border-0 file:text-xs file:font-medium file:bg-purple-50 file:text-purple-700 hover:file:bg-purple-100">
                                            </div>
                                        </div>
                                        <div class="mt-3 flex gap-2">
                                            <button type="submit" class="px-3 py-1.5 bg-[#714B67] text-white text-sm rounded hover:bg-[#5c3d55]">{{ __('common.save_short') }}</button>
                                            <button type="button" @click="open = false" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded hover:bg-gray-100">{{ __('common.cancel') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endcan

                            @if($employee->contracts->isEmpty())
                                <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_contracts') }}</div>
                            @else
                            <div class="space-y-2">
                            @foreach($employee->contracts->sortByDesc('date_start') as $contract)
                            <div class="flex items-center gap-4 p-3 border border-gray-200 rounded-lg hover:bg-gray-50">
                                @if($contract->image)
                                <a href="{{ route('employees.contracts.image', [$employee, $contract]) }}" target="_blank" class="shrink-0">
                                    <img src="{{ route('employees.contracts.image', [$employee, $contract]) }}" alt="Contract image" class="w-12 h-12 object-cover rounded border border-gray-200">
                                </a>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-semibold text-gray-900">{{ $contract->name }}</p>
                                        @if($employee->contract_id === $contract->id)
                                            <span class="text-[10px] font-bold text-purple-700 bg-purple-100 px-1.5 py-0.5 rounded uppercase">{{ __('employees.contract_current') }}</span>
                                        @endif
                                        <span class="text-xs font-medium px-1.5 py-0.5 rounded {{ $contractColors[$contract->state] ?? '' }}">{{ ucfirst($contract->state) }}</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-0.5">
                                        {{ ucwords(str_replace('_', ' ', $contract->contract_type)) }}
                                        @if($contract->date_start) · {{ $contract->date_start->format('d M Y') }}@endif
                                        @if($contract->date_end) → {{ $contract->date_end->format('d M Y') }}@endif
                                        @if($contract->wage) · {{ number_format($contract->wage, 2) }}@endif
                                    </p>
                                </div>
                                @can('update', $contract)
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @if($employee->contract_id !== $contract->id)
                                    <form method="POST" action="{{ route('employees.contracts.set-active', [$employee, $contract]) }}">
                                        @csrf @method('PATCH')
                                        <button class="px-2 py-1 text-xs text-purple-700 border border-purple-200 rounded hover:bg-purple-50">{{ __('employees.set_active') }}</button>
                                    </form>
                                    @endif
                                    @can('delete', $contract)
                                    <div x-data="{ confirming: false }">
                                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-2 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                                        <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                            <span class="text-xs text-red-600">{{ __('common.sure') }}</span>
                                            <form method="POST" action="{{ route('employees.contracts.delete', [$employee, $contract]) }}">
                                                @csrf @method('DELETE')
                                                <button class="px-1.5 py-0.5 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                                            </form>
                                            <button type="button" @click="confirming = false" class="px-1.5 py-0.5 text-xs text-gray-500 border border-gray-200 rounded">{{ __('common.no') }}</button>
                                        </div>
                                    </div>
                                    @endcan
                                </div>
                                @endcan
                            </div>
                            @endforeach
                            </div>
                            @endif
                        </div>

                        {{-- Skills --}}
                        <div x-show="tab === 'skills'" id="skills" style="display:none" class="p-6">
                            @if($employee->skills->isEmpty())
                                <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_skills_recorded') }}</div>
                            @else
                            @php $grouped = $employee->skills->groupBy(fn($s) => $s->skillType?->name ?? 'Other'); @endphp
                            @foreach($grouped as $typeName => $typeSkills)
                            <div class="mb-6">
                                <h4 class="text-xs font-semibold text-gray-400 uppercase mb-3">{{ $typeName }}</h4>
                                <div class="space-y-2">
                                @foreach($typeSkills as $empSkill)
                                <div class="flex items-center gap-4">
                                    <span class="w-40 shrink-0 text-sm text-gray-800">{{ $empSkill->skill?->name }}</span>
                                    <span class="w-24 shrink-0 text-xs text-gray-500">{{ $empSkill->skillLevel?->name ?? '—' }}</span>
                                    <div class="flex-1 bg-gray-100 rounded-full h-1.5">
                                        <div class="bg-purple-500 h-1.5 rounded-full" style="width: {{ $empSkill->level_progress }}%"></div>
                                    </div>
                                    <span class="w-10 text-right text-xs text-gray-400">{{ $empSkill->level_progress }}%</span>
                                </div>
                                @endforeach
                                </div>
                            </div>
                            @endforeach
                            @endif
                        </div>

                        {{-- Documents --}}
                        @php
                            $docLabels = ['contract' => 'Contract', 'id_card' => 'ID Card', 'passport' => 'Passport', 'certificate' => 'Certificate', 'resume' => 'Resume', 'medical' => 'Medical', 'other' => 'Other'];
                            $docIcons  = ['contract' => '📄', 'id_card' => '🪪', 'passport' => '🛂', 'certificate' => '🎓', 'resume' => '📋', 'medical' => '🏥', 'other' => '📁'];
                            $imgExts   = ['jpg','jpeg','png','gif','webp','svg'];
                        @endphp
                        <div x-show="tab === 'documents'" id="documents" style="display:none" class="p-6"
                             x-data="{ doc: null }"
                             @keydown.escape.window="doc = null">

                            {{-- Document viewer modal --}}
                            <template x-if="doc">
                                <div class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="doc = null">
                                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"></div>
                                    <div class="relative z-10 bg-white rounded-2xl shadow-2xl flex flex-col w-full max-w-4xl max-h-[90vh] overflow-hidden">
                                        {{-- Modal header --}}
                                        <div class="flex items-center gap-3 px-5 py-3 border-b border-gray-200 shrink-0">
                                            <span class="text-xl" x-text="doc.icon"></span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-semibold text-gray-900 truncate" x-text="doc.name"></p>
                                                <p class="text-xs text-gray-400 capitalize" x-text="doc.type"></p>
                                            </div>
                                            <a :href="doc.downloadUrl" class="px-3 py-1.5 text-xs text-purple-700 border border-purple-200 rounded hover:bg-purple-50 shrink-0">{{ __('common.download') }}</a>
                                            <button type="button" @click="doc = null" class="p-1.5 text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg shrink-0">
                                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            </button>
                                        </div>
                                        {{-- Modal body --}}
                                        <div class="flex-1 overflow-hidden bg-gray-50 flex items-center justify-center min-h-64">
                                            <template x-if="doc.previewUrl && doc.isImage">
                                                <img :src="doc.previewUrl" :alt="doc.name" class="max-w-full max-h-full object-contain p-4">
                                            </template>
                                            <template x-if="doc.previewUrl && doc.isPdf">
                                                <iframe :src="doc.previewUrl" class="w-full h-full border-0" style="min-height: 60vh;"></iframe>
                                            </template>
                                            <template x-if="!doc.previewUrl || (!doc.isImage && !doc.isPdf)">
                                                <div class="text-center p-8">
                                                    <div class="text-5xl mb-4" x-text="doc.icon"></div>
                                                    <p class="text-sm text-gray-500 mb-4">{{ __('employees.no_preview') }}</p>
                                                    <a :href="doc.downloadUrl" class="px-4 py-2 bg-[#714B67] text-white text-sm rounded hover:bg-[#5c3d55]">{{ __('employees.download_file') }}</a>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            @can('update', $employee)
                            <div class="mb-4">
                                <a href="{{ route('employees.edit', $employee) }}#documents-section"
                                   class="inline-flex items-center gap-1 text-xs text-purple-700 border border-purple-200 rounded px-3 py-1.5 hover:bg-purple-50">
                                    {{ __('employees.add_document') }}
                                </a>
                            </div>
                            @endcan

                            @if($employee->documents->isEmpty())
                                <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_documents') }}</div>
                            @else
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                @foreach($employee->documents as $doc)
                                @php
                                    $ext         = $doc->attachedFile ? strtolower($doc->attachedFile->extension) : '';
                                    $isImage     = in_array($ext, $imgExts);
                                    $isPdf       = $ext === 'pdf';
                                    $previewUrl  = $doc->file_path ? route('files.serve', $doc->file_path) : null;
                                    $downloadUrl = $doc->file_path ? route('files.serve', $doc->file_path) : null;
                                    $docIcon     = $docIcons[$doc->document_type] ?? '📁';
                                    $docData     = json_encode([
                                        'name'        => $doc->name,
                                        'type'        => $docLabels[$doc->document_type] ?? $doc->document_type,
                                        'icon'        => $docIcon,
                                        'previewUrl'  => $previewUrl,
                                        'downloadUrl' => $downloadUrl,
                                        'isImage'     => $isImage,
                                        'isPdf'       => $isPdf,
                                    ]);
                                @endphp
                                <div class="group flex gap-3 p-4 border border-gray-200 rounded-xl transition-colors {{ $doc->file_path ? 'cursor-pointer hover:border-purple-300 hover:bg-purple-50/30' : '' }}"
                                     @if($doc->file_path) @click="doc = {{ $docData }}" @endif>
                                    <div class="text-2xl shrink-0 mt-0.5">{{ $docIcon }}</div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-semibold text-gray-900 truncate group-hover:text-purple-700">{{ $doc->name }}</p>
                                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                            <span class="inline-block px-1.5 py-0.5 text-[10px] font-semibold rounded-full bg-gray-100 text-gray-600 uppercase tracking-wide">{{ $docLabels[$doc->document_type] ?? $doc->document_type }}</span>
                                            @if($doc->issue_date)
                                            <span class="text-xs text-gray-400">{{ $doc->issue_date->format('d M Y') }}</span>
                                            @endif
                                        </div>
                                        @if($doc->expiry_date)
                                        <p class="text-xs mt-1 {{ $doc->is_expired ? 'text-red-600 font-semibold' : ($doc->is_expiring_soon ? 'text-amber-600' : 'text-gray-400') }}">
                                            {{ __('employees.doc_expires') }} {{ $doc->expiry_date->format('d M Y') }}{{ $doc->is_expired ? ' — ' . __('employees.doc_expired') : ($doc->is_expiring_soon ? ' — ' . __('employees.doc_soon') : '') }}
                                        </p>
                                        @endif
                                        <div class="flex items-center gap-2 mt-2">
                                            @if($doc->file_path)
                                            <span class="text-xs text-purple-500">{{ strtoupper($ext) ?: 'File' }} · {{ __('employees.click_to_view') }}</span>
                                            @else
                                            <span class="text-xs text-gray-300">{{ __('employees.no_file_attached') }}</span>
                                            @endif
                                            @can('update', $employee)
                                            <div x-data="{ confirming: false }" class="ms-auto" @click.stop>
                                                <button type="button" x-show="!confirming" @click="confirming = true"
                                                        class="text-xs text-red-400 hover:text-red-600">{{ __('common.delete') }}</button>
                                                <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                                    <span class="text-xs text-red-600">{{ __('common.sure') }}</span>
                                                    <form method="POST" action="{{ route('employees.documents.delete', [$employee, $doc]) }}">
                                                        @csrf @method('DELETE')
                                                        <button class="text-xs px-1.5 py-0.5 bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                                                    </form>
                                                    <button type="button" @click="confirming = false" class="text-xs px-1.5 py-0.5 text-gray-400 border border-gray-200 rounded">{{ __('common.no') }}</button>
                                                </div>
                                            </div>
                                            @endcan
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                            @endif
                        </div>

                        {{-- Planned Schedule --}}
                        <div x-show="tab === 'schedule'" style="display:none" class="p-6">
                            @if(!$employee->resourceCalendar)
                                <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_schedule') }}</div>
                            @else
                            @php
                                $hasWeeklyAttendances = $employee->resourceCalendar->attendances->isNotEmpty();
                                $calStart    = now()->startOfMonth();
                                $daysInMonth = $calStart->daysInMonth;
                                $firstCol    = $calStart->dayOfWeek; // 0=Sun
                                $attByDow    = $hasWeeklyAttendances ? $employee->resourceCalendar->attendances->groupBy('day_of_week') : collect();
                            @endphp
                            {{-- Default to the 'planned' sub-tab so the editing UI is the first thing the user sees. --}}
                            <div x-data="{ subTab: 'planned' }">
                                <div class="flex items-center justify-between mb-4">
                                    <span class="text-sm font-semibold text-gray-700">{{ now()->format('F Y') }} — {{ $employee->resourceCalendar->name }}</span>
                                    <div class="flex gap-1">
                                        <button type="button" @click="subTab = 'planned'" class="px-2.5 py-1 text-xs rounded border transition-colors" :class="subTab === 'planned' ? 'bg-purple-100 text-purple-700 border-purple-300' : 'text-gray-500 border-gray-200 hover:bg-gray-50'">{{ __('employees.planned_days_tab') }}</button>
                                        <button type="button" @click="subTab = 'calendar'" class="px-2.5 py-1 text-xs rounded border transition-colors" :class="subTab === 'calendar' ? 'bg-purple-100 text-purple-700 border-purple-300' : 'text-gray-500 border-gray-200 hover:bg-gray-50'">{{ __('employees.calendar_tab') }}</button>
                                        <button type="button" @click="subTab = 'pattern'" class="px-2.5 py-1 text-xs rounded border transition-colors" :class="subTab === 'pattern' ? 'bg-purple-100 text-purple-700 border-purple-300' : 'text-gray-500 border-gray-200 hover:bg-gray-50'">{{ __('employees.weekly_pattern') }}</button>
                                    </div>
                                </div>

                                {{-- Planned days (dynamic, editable, 30-day buffer) — always available --}}
                                <div x-show="subTab === 'planned'">
                                    @include('employees._planned_schedule', [
                                        'employee'        => $employee,
                                        'plannedDays'     => $plannedDays ?? collect(),
                                        'plannedPattern'  => $plannedPattern ?? collect(),
                                    ])
                                </div>

                                {{-- Calendar view (read-only; only meaningful when the calendar has weekly attendance lines) --}}
                                <div x-show="subTab === 'calendar'" style="display:none">
                                    @if(!$hasWeeklyAttendances)
                                        <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_weekly_pattern') }}</div>
                                    @else
                                    <div class="grid grid-cols-7 gap-px bg-gray-100 rounded-xl overflow-hidden text-xs">
                                        @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $h)
                                        <div class="bg-gray-50 py-2 text-center font-semibold text-gray-500 uppercase tracking-wide">{{ $h }}</div>
                                        @endforeach
                                        @for($b = 0; $b < $firstCol; $b++)
                                        <div class="bg-white min-h-16"></div>
                                        @endfor
                                        @for($d = 1; $d <= $daysInMonth; $d++)
                                        @php
                                            $date   = $calStart->copy()->day($d);
                                            $ourDow = ($date->dayOfWeek + 1) % 7;
                                            $lines  = $attByDow->get($ourDow, collect());
                                            $isToday = $date->isToday();
                                        @endphp
                                        <div class="bg-white min-h-16 p-1.5 {{ $isToday ? 'ring-2 ring-inset ring-purple-400' : '' }}">
                                            <span class="block text-right text-[11px] font-semibold mb-0.5 {{ $isToday ? 'text-purple-700' : 'text-gray-400' }}">{{ $d }}</span>
                                            @foreach($lines->sortBy('hour_from') as $line)
                                            <div class="text-[10px] text-purple-700 bg-purple-50 rounded px-1 py-0.5 truncate mb-0.5 leading-tight">
                                                {{ $decToTime($line->hour_from) }}–{{ $decToTime($line->hour_to) }}
                                            </div>
                                            @endforeach
                                        </div>
                                        @endfor
                                    </div>
                                    @endif
                                </div>

                                {{-- Weekly pattern view --}}
                                <div x-show="subTab === 'pattern'" style="display:none">
                                    @if(!$hasWeeklyAttendances)
                                        <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_weekly_pattern') }}</div>
                                    @else
                                    @php $dayNames = \App\Models\Employees\ResourceCalendar::$dayNames; @endphp
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="border-b border-gray-200">
                                                <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.day') }}</th>
                                                <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.period') }}</th>
                                                <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.from') }}</th>
                                                <th class="text-left py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.to') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            @foreach($employee->resourceCalendar->attendances->sortBy(['day_of_week', 'hour_from']) as $att)
                                            <tr class="hover:bg-gray-50">
                                                <td class="py-2 text-gray-700">{{ $dayNames[$att->day_of_week] ?? $att->day_of_week }}</td>
                                                <td class="py-2 text-gray-600 capitalize">{{ $att->day_period }}</td>
                                                <td class="py-2 text-gray-700">{{ $decToTime($att->hour_from) }}</td>
                                                <td class="py-2 text-gray-700">{{ $decToTime($att->hour_to) }}</td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    @endif
                                </div>
                            </div>
                            @endif
                        </div>

                        {{-- Requests (HR view only) --}}
                        @if(auth()->user()->hasPermission('attendance.requests.read'))
                        <div x-show="tab === 'requests'" style="display:none" class="p-6">
                            {{-- Balance summary at the top of the tab --}}
                            @if(isset($employeeBalance))
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
                                <div class="rounded-lg border border-purple-200 bg-purple-50/30 p-3">
                                    <p class="text-xs text-purple-600 uppercase tracking-wide font-semibold">{{ __('employees.balance_leave_days') }}</p>
                                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) $employeeBalance->leave_days_balance, 2) }}</p>
                                </div>
                                <div class="rounded-lg border border-purple-200 bg-purple-50/30 p-3">
                                    <p class="text-xs text-purple-600 uppercase tracking-wide font-semibold">{{ __('employees.balance_time_off_hours') }}</p>
                                    <p class="text-2xl font-bold text-gray-900 mt-1">{{ number_format((float) $employeeBalance->time_off_hours_balance, 2) }}</p>
                                </div>
                            </div>
                            @endif

                            @if(($employeeRequests ?? collect())->isEmpty())
                                <div class="py-12 text-center text-gray-400 text-sm">{{ __('employees.no_requests') }}</div>
                            @else
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="text-start px-4 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_type') }}</th>
                                        <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_subtype') }}</th>
                                        <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_from') }}</th>
                                        <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_to') }}</th>
                                        <th class="text-start px-3 py-2 text-xs font-semibold text-gray-500 uppercase">{{ __('employees.request_state') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                @foreach($employeeRequests as $r)
                                <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.requests.show', $r) }}'">
                                    <td class="px-4 py-2 text-gray-800">{{ __('employees.' . $r->type) }}</td>
                                    <td class="px-3 py-2 text-gray-700">{{ $r->subtype?->name }}</td>
                                    <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $r->start_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
                                    <td class="px-3 py-2 text-gray-700 whitespace-nowrap">{{ $r->end_at?->format($r->type === 'leave' ? 'M d, Y' : 'M d H:i') }}</td>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $r->state_color }}-100 text-{{ $r->state_color }}-700">{{ __('employees.request_state_' . $r->state) }}</span>
                                    </td>
                                </tr>
                                @endforeach
                                </tbody>
                            </table>
                            @endif
                        </div>
                        @endif

                    </div>{{-- end min-h-64 --}}
                </div>{{-- end x-data tabs --}}
            </div>
        </div>

        {{-- Chatter --}}
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\Employee"
                :model-id="$employee->id"
                :can-comment="auth()->user()->can('comment', $employee)"
                :comment-url="route('employees.comment', $employee)"
            />
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>{{ __('common.created') }} {{ $employee->created_at->format('M d, Y') }}{{ $employee->creator ? ' · ' . $employee->creator->name : '' }}</span>
            <span>{{ __('common.updated') }} {{ $employee->updated_at->diffForHumans() }}{{ $employee->updater ? ' · ' . $employee->updater->name : '' }}</span>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const hash = location.hash?.replace('#', '');
    const tabMap = { contracts: 'contracts', skills: 'skills', documents: 'documents', schedule: 'schedule' };
    if (hash && tabMap[hash]) {
        const btn = document.getElementById('tab-' + tabMap[hash]);
        if (btn) btn.click();
    }
});
</script>
@endsection
