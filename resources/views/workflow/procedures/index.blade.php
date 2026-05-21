@extends('layouts.app')
@section('title', __('workflow.procedures_title'))

@php
    $view = request('view', 'list');
    $procQuickFilters = [
        ['label' => __('workflow.state_in_progress'), 'params' => ['state' => 'pending'],   'url' => route('workflow.procedures.index', array_merge(request()->except('page'), ['state' => 'pending']))],
        ['label' => __('workflow.state_completed'),   'params' => ['state' => 'completed'], 'url' => route('workflow.procedures.index', array_merge(request()->except('page'), ['state' => 'completed']))],
        ['label' => __('workflow.state_canceled'),    'params' => ['state' => 'closed'],    'url' => route('workflow.procedures.index', array_merge(request()->except('page'), ['state' => 'closed']))],
        ['label' => __('common.active'),              'params' => ['filter' => ''],         'url' => route('workflow.procedures.index', array_merge(request()->except('page', 'filter'), ['filter' => '']))],
        ['label' => __('common.archived'),            'params' => ['filter' => 'archived'], 'url' => route('workflow.procedures.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),                 'params' => ['filter' => 'all'],      'url' => route('workflow.procedures.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $procGroups = [
        ['label' => __('common.status'), 'url' => route('workflow.procedures.index', array_merge(request()->except('page'), ['group_by' => 'state']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'kanban' ? 'bg-gray-100' : 'bg-white' }}" x-data="{ checked: [] }">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Workflow\Procedure::class)
        <a href="{{ route('workflow.procedures.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('workflow.procedures_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Workflow\Procedure::class"
            :action="route('workflow.procedures.index')"
            :preserve="['view' => $view]"
            :quick-filters="$procQuickFilters"
            :group-by="$procGroups"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($procedures->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $procedures->firstItem() }}-{{ $procedures->lastItem() }} / {{ $procedures->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">{{ __('workflow.zero_records') }}</span>
            @endif

            <div class="flex items-center gap-1">
                @if($procedures->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $procedures->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($procedures->hasMorePages())
                    <a href="{{ $procedures->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>

            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ route('workflow.procedures.index', array_merge(request()->except('view','page'), ['view' => 'kanban'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'kanban' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('workflow.kanban_view_title') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 3h6v14H3V3zm8 0h6v6h-6V3zm0 8h6v6h-6v-6z"/>
                    </svg>
                </a>
                <a href="{{ route('workflow.procedures.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="{{ __('workflow.list_view_title') }}">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    @if($view === 'kanban')
    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
        @if($procedures->isEmpty())
            <div class="py-24 text-center text-gray-400">
                <p class="text-sm font-medium text-gray-500">{{ __('workflow.no_procedures_found_kanban') }}</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach($procedures as $procedure)
                <a href="{{ route('workflow.procedures.show', $procedure) }}" class="relative group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                    <x-state-ribbon :state="$procedure->state" />
                    <div class="p-3">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $procedure->stateColor() }}">{{ $procedure->stateLabel() }}</span>
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-purple-700 line-clamp-2">{{ $procedure->name }}</h3>
                        @if($procedure->procedureTemplate)
                            <p class="text-xs text-gray-500 truncate mt-1">{{ $procedure->procedureTemplate->name }}</p>
                        @endif
                        @if(!$procedure->active)
                            <span class="inline-block mt-1 text-[10px] font-semibold text-amber-600 uppercase">{{ __('common.archived') }}</span>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    </div>
    @else
    <x-list :paginator="$procedures" :empty-text="__('workflow.no_procedures_found')">
        <x-slot:columns>
            <x-sortable-th column="id"         label="ID"        class="px-4 py-2" />
            <x-sortable-th column="name"       label="Name"      class="px-3 py-2" :default="true" />
            <x-sortable-th column="state"      label="State"     class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('workflow.template_label') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('workflow.created_by_label') }}</th>
            <x-sortable-th column="deadline"   label="Deadline"  class="px-3 py-2" />
            <x-sortable-th column="duration"   label="Duration"  class="px-3 py-2" />
            <x-sortable-th column="sla_passed" label="SLA Passed" class="px-3 py-2" />
            <x-sortable-th column="sla_limit"  label="SLA Limit" class="px-3 py-2" />
            <x-sortable-th column="created_at" label="Created"   class="px-3 py-2" />
        </x-slot:columns>

        @foreach($procedures as $procedure)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.procedures.show', $procedure) }}'">
            <td class="px-4 py-2 text-gray-500">{{ $procedure->id }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">
                {{ $procedure->name }}
                @if(!$procedure->active)
                    <span class="ml-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('common.archived') }}</span>
                @endif
                @if($procedure->isOverdue())
                    <span class="ml-1.5 text-[10px] text-red-600 font-semibold uppercase">{{ __('workflow.overdue_label') }}</span>
                @endif
            </td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $procedure->stateColor() }}">{{ $procedure->stateLabel() }}</span>
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $procedure->procedureTemplate?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $procedure->createdByUser?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-500 text-xs">{{ $procedure->resolve_deadline?->format('M j, Y H:i') ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $procedure->resolve_duration ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $procedure->resolve_deadline_passed ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $procedure->resolve_max_duration ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-500 text-xs">{{ $procedure->created_at->format('M j, Y') }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
