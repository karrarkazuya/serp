@extends('layouts.app')
@section('title', __('employees.title'))

@php
    $view = $view ?? request('view', 'kanban');
    $quickFilters = [
        ['label' => __('common.active'),        'params' => ['filter' => ''],         'url' => route('employees.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'),      'params' => ['filter' => 'archived'],  'url' => route('employees.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),           'params' => ['filter' => 'all'],       'url' => route('employees.index', array_merge(request()->except('page'), ['filter' => 'all']))],
        ['label' => __('employees.probation'),  'params' => ['status' => 'probation'], 'url' => route('employees.index', array_merge(request()->except('page'), ['status' => 'probation']))],
    ];
    $groupOptions = [
        ['label' => __('common.department'),      'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'department_id']))],
        ['label' => __('employees.job_position'),'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'job_id']))],
        ['label' => __('common.company'),         'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'company_id']))],
        ['label' => __('common.status'),          'url' => route('employees.index', array_merge(request()->except('page'), ['group_by' => 'employment_status']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'kanban' ? 'bg-gray-100' : 'bg-white' }}" x-data="{ checked: [] }"
     @if($view === 'tree') style="background:#f9fafb" @endif>
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\Employee::class"
            :action="route('employees.index')"
            :preserve="['view' => $view]"
            :quick-filters="$quickFilters"
            :group-by="$groupOptions"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            {{-- Count / pagination --}}
            @if($view === 'tree')
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $total ?? 0 }}</span>
            @elseif(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $groups->sum('count') }}</span>
            @else
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
            @endif

            {{-- View toggles --}}
            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ route('employees.index', array_merge(request()->except('view','page'), ['view' => 'kanban'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'kanban' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('common.kanban_view') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M3 3h6v14H3V3zm8 0h6v6h-6V3zm0 8h6v6h-6v-6z"/></svg>
                </a>
                <a href="{{ route('employees.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('common.list_view') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/></svg>
                </a>
                <a href="{{ route('employees.index', array_merge(request()->except('view','page'), ['view' => 'tree'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'tree' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('common.tree_view') }}">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M3 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H5a2 2 0 01-2-2V6zM13 4h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6a2 2 0 012-2zM9 18a2 2 0 012-2h2a2 2 0 012 2v1a2 2 0 01-2 2h-2a2 2 0 01-2-2v-1zM6 10v4M12 10v4M9 14h6"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    @if($view === 'tree')
    <x-tree :nodes="$treeNodes" :empty-text="__('employees.no_employees')" class="flex-1" />

    @elseif($view === 'kanban')
    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
        @if($employees->isEmpty())
            <div class="py-24 text-center text-gray-400">
                <p class="text-sm font-medium text-gray-500">{{ __('employees.no_employees') }}</p>
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
                                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
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
    {{-- list view --}}
    @can('export', \App\Models\Employees\Employee::class)
    <x-export
        :fields="config('exportable')['employees']['fields'] ?? []"
        :export-url="route('export')"
        model-key="employees"
    />
    @endcan

    @if(isset($groups))
    {{-- Grouped list --}}
    <x-list :grouped="true" :empty-text="__('employees.no_employees')">
        <x-slot:columns>
            <x-sortable-th column="name"       :label="__('common.name')"             class="px-4 py-2" :default="true" />
            <x-sortable-th column="department"  :label="__('common.department')"       class="px-3 py-2" />
            <x-sortable-th column="job"         :label="__('employees.job_position')"  class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.work_email') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.work_phone') }}</th>
            <x-sortable-th column="status"      :label="__('common.status')"           class="px-3 py-2" />
            <x-sortable-th column="company"     :label="__('common.company')"          class="px-3 py-2" />
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
            @foreach($group['items'] as $employee)
            @php $statusColor = \App\Models\Employees\Employee::employmentStatusColor($employee->employment_status); @endphp
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.show', $employee) }}'">
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
        </tbody>
        @empty
        <tbody>
            <tr>
                <td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_employees') }}</td>
            </tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    {{-- Paginated list --}}
    <x-list :paginator="$employees" :empty-text="__('employees.no_employees')" :selectable="true" :total-count="$employees->total()" :model="\App\Models\Employees\Employee::class">
        <x-slot:columns>
            <x-sortable-th column="name"       :label="__('common.name')"             class="px-4 py-2" :default="true" />
            <x-sortable-th column="department"  :label="__('common.department')"       class="px-3 py-2" />
            <x-sortable-th column="job"         :label="__('employees.job_position')"  class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.work_email') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.work_phone') }}</th>
            <x-sortable-th column="status"      :label="__('common.status')"           class="px-3 py-2" />
            <x-sortable-th column="company"     :label="__('common.company')"          class="px-3 py-2" />
        </x-slot:columns>

        @foreach($employees as $employee)
        @php $statusColor = \App\Models\Employees\Employee::employmentStatusColor($employee->employment_status); @endphp
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.show', $employee) }}'">
            <td class="w-10 px-3 py-2 text-center" @click.stop>
                <input type="checkbox"
                       class="list-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
                       x-model="selected"
                       value="{{ $employee->id }}">
            </td>
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
    @endif
</div>
@endsection
