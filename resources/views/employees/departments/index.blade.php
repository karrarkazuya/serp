@extends('layouts.app')
@section('title', __('employees.departments_title'))

@php
    $view = $view ?? request('view', 'list');
    $quickFilters = [
        ['label' => __('common.active'),   'params' => ['filter' => ''],         'url' => route('employees.departments.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'params' => ['filter' => 'archived'],  'url' => route('employees.departments.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'params' => ['filter' => 'all'],       'url' => route('employees.departments.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'tree' ? 'bg-gray-50' : 'bg-white' }}">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Department::class)
        <a href="{{ route('employees.departments.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.departments_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\Department::class"
            :action="route('employees.departments.index')"
            :preserve="['view' => $view]"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            {{-- Count / pagination --}}
            @if($view === 'tree')
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $total ?? 0 }}</span>
            @elseif(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $groups->sum('count') }}</span>
            @else
                @if($departments->total() > 0)
                    <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                        {{ $departments->firstItem() }}-{{ $departments->lastItem() }} / {{ $departments->total() }}
                    </span>
                @else
                    <span class="text-sm font-semibold text-gray-400">0</span>
                @endif

                <div class="flex items-center gap-1">
                    @if($departments->onFirstPage())
                        <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                    @else
                        <a href="{{ $departments->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                    @endif
                    @if($departments->hasMorePages())
                        <a href="{{ $departments->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                    @else
                        <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                    @endif
                </div>
            @endif

            {{-- View toggles --}}
            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ route('employees.departments.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('common.list_view') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/></svg>
                </a>
                <a href="{{ route('employees.departments.index', array_merge(request()->except('view','page'), ['view' => 'tree'])) }}"
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
    <x-tree :nodes="$treeNodes" :empty-text="__('employees.no_departments')" class="flex-1" />
    @elseif(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('employees.no_departments') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.name') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.manager') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.company') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.employees') }}</th>
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
            @foreach($group['items'] as $department)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.departments.show', $department) }}'">
                <td class="px-4 py-2.5 font-medium text-gray-900">
                    <div>
                        <p class="text-sm font-semibold text-gray-900">{{ $department->name }}</p>
                        @if($department->parent)
                            <p class="text-xs text-gray-400">{{ $department->parent->name }}</p>
                        @endif
                        @if(!$department->active)
                            <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                        @endif
                    </div>
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->manager?->name }}</td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->company?->name }}</td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->employees_count }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_departments') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>
    @else
    <x-list :paginator="$departments" :empty-text="__('employees.no_departments')">
        <x-slot:columns>
            <x-sortable-th column="name"     :label="__('common.name')"       class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.manager') }}</th>
            <x-sortable-th column="company"  :label="__('common.company')"    class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('common.employees') }}</th>
        </x-slot:columns>

        @foreach($departments as $department)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.departments.show', $department) }}'">
            <td class="px-4 py-2.5 font-medium text-gray-900">
                <div>
                    <p class="text-sm font-semibold text-gray-900">{{ $department->name }}</p>
                    @if($department->parent)
                        <p class="text-xs text-gray-400">{{ $department->parent->name }}</p>
                    @endif
                    @if(!$department->active)
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                    @endif
                </div>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->manager?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->company?->name }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $department->employees_count }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
