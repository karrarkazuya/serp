@extends('layouts.app')
@section('title', 'Employees')

@php
    $view = request('view', 'kanban');
    $quickFilters = [
        ['label' => 'Active',     'params' => ['filter' => ''],         'url' => route('employees.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => 'Archived',   'params' => ['filter' => 'archived'],  'url' => route('employees.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',        'params' => ['filter' => 'all'],       'url' => route('employees.index', array_merge(request()->except('page'), ['filter' => 'all']))],
        ['label' => 'Probation',  'params' => ['status' => 'probation'], 'url' => route('employees.index', array_merge(request()->except('page'), ['status' => 'probation']))],
    ];
    $groupOptions = [
        ['label' => 'Department',  'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'department_id']))],
        ['label' => 'Job Position','url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'job_id']))],
        ['label' => 'Company',     'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'company_id']))],
        ['label' => 'Status',      'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'employment_status']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'kanban' ? 'bg-gray-100' : 'bg-white' }}" x-data="{ checked: [] }">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            New
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">Employees</span>
        </div>

        <x-search
            :model="\App\Models\Employees\Employee::class"
            :action="route('employees.index')"
            :preserve="['view' => $view]"
            :quick-filters="$quickFilters"
            :group-by="$groupOptions"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($employees->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $employees->firstItem() }}-{{ $employees->lastItem() }} / {{ $employees->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif

            <div class="flex items-center gap-1">
                @if($employees->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $employees->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($employees->hasMorePages())
                    <a href="{{ $employees->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>

            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ route('employees.index', array_merge(request()->except('view','page'), ['view' => 'kanban'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'kanban' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="Kanban View">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 3h6v14H3V3zm8 0h6v6h-6V3zm0 8h6v6h-6v-6z"/></svg>
                </a>
                <a href="{{ route('employees.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="List View">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/></svg>
                </a>
            </div>
        </div>
    </div>

    @if($view === 'kanban')
    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
        @if($employees->isEmpty())
            <div class="py-24 text-center text-gray-400">
                <p class="text-sm font-medium text-gray-500">No employees found.</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach($employees as $employee)
                @php
                    $statusColor = \App\Models\Employees\Employee::employmentStatusColor($employee->employment_status);
                    $statusColors = ['green' => 'text-green-700 bg-green-50', 'blue' => 'text-blue-700 bg-blue-50', 'orange' => 'text-orange-700 bg-orange-50', 'red' => 'text-red-700 bg-red-50', 'gray' => 'text-gray-700 bg-gray-50'];
                @endphp
                <a href="{{ route('employees.show', $employee) }}" class="group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                    <div class="flex gap-3 p-3">
                        <div class="shrink-0">
                            @if($employee->avatar_url)
                                <img src="{{ $employee->avatar_url }}" alt="{{ $employee->name }}" class="w-16 h-16 rounded-lg object-cover border border-gray-100">
                            @else
                                <div class="w-16 h-16 rounded-lg flex items-center justify-center text-xl font-bold bg-purple-100 text-purple-700">
                                    {{ strtoupper(substr($employee->name, 0, 2)) }}
                                </div>
                            @endif
                        </div>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-gray-900 truncate group-hover:text-purple-700">{{ $employee->name }}</h3>
                            @if($employee->job_title ?? $employee->job?->name)
                                <p class="text-xs text-gray-500 truncate mt-0.5">{{ $employee->job_title ?? $employee->job?->name }}</p>
                            @endif
                            @if($employee->department?->name)
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ $employee->department->name }}</p>
                            @endif
                            @if($employee->work_email)
                                <p class="text-xs text-blue-500 truncate mt-0.5">{{ $employee->work_email }}</p>
                            @endif
                            <div class="flex flex-wrap gap-1 mt-1.5">
                                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $statusColors[$statusColor] ?? $statusColors['gray'] }}">
                                    {{ \App\Models\Employees\Employee::employmentStatusLabel($employee->employment_status) }}
                                </span>
                                @if(!$employee->active)
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">Archived</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @if($employee->categories->isNotEmpty())
                    <div class="px-3 pb-2.5 flex flex-wrap gap-1">
                        @foreach($employee->categories->take(3) as $cat)
                            <span class="inline-block px-1.5 py-0.5 rounded-full text-[10px] font-medium text-white" style="background-color: {{ $cat->color }}">{{ $cat->name }}</span>
                        @endforeach
                    </div>
                    @endif
                </a>
                @endforeach
            </div>
        @endif
    </div>
    @else
    <x-list :paginator="$employees" empty-text="No employees found.">
        <x-slot:columns>
            <x-sortable-th column="name"       label="Name"        class="px-4 py-2" :default="true" />
            <x-sortable-th column="department"  label="Department"  class="px-3 py-2" />
            <x-sortable-th column="job"         label="Job Position" class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Work Email</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Phone</th>
            <x-sortable-th column="status"      label="Status"      class="px-3 py-2" />
            <x-sortable-th column="company"     label="Company"     class="px-3 py-2" />
        </x-slot:columns>

        @foreach($employees as $employee)
        @php $statusColor = \App\Models\Employees\Employee::employmentStatusColor($employee->employment_status); @endphp
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.show', $employee) }}'">
            <td class="px-4 py-2.5 font-medium text-gray-900">
                <div class="flex items-center gap-2.5">
                    @if($employee->avatar_url)
                        <img src="{{ $employee->avatar_url }}" alt="{{ $employee->name }}" class="w-8 h-8 rounded-full object-cover">
                    @else
                        <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700">{{ strtoupper(substr($employee->name, 0, 2)) }}</div>
                    @endif
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $employee->name }}</p>
                        @if($employee->employee_code)<p class="text-xs text-gray-400">{{ $employee->employee_code }}</p>@endif
                    </div>
                </div>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $employee->department?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $employee->job_title ?? $employee->job?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $employee->work_email }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $employee->work_phone }}</td>
            <td class="px-3 py-2.5">
                @php $statusColors = ['green' => 'text-green-700 bg-green-50', 'blue' => 'text-blue-700 bg-blue-50', 'orange' => 'text-orange-700 bg-orange-50', 'red' => 'text-red-700 bg-red-50', 'gray' => 'text-gray-700 bg-gray-50']; @endphp
                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $statusColors[$statusColor] ?? $statusColors['gray'] }}">
                    {{ \App\Models\Employees\Employee::employmentStatusLabel($employee->employment_status) }}
                </span>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $employee->company?->name }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
