@extends('layouts.app')
@section('title', 'Tickets')

@php
    $view = request('view', 'list');
    $ticketQuickFilters = [
        ['label' => 'Open',      'params' => ['state' => 'pending'],   'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['state' => 'pending']))],
        ['label' => 'Completed', 'params' => ['state' => 'completed'], 'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['state' => 'completed']))],
        ['label' => 'Closed',    'params' => ['state' => 'closed'],    'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['state' => 'closed']))],
        ['label' => 'Active',    'params' => ['filter' => ''],         'url' => route('workflow.tickets.index', array_merge(request()->except('page', 'filter'), ['filter' => '']))],
        ['label' => 'Archived',  'params' => ['filter' => 'archived'], 'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',       'params' => ['filter' => 'all'],      'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $ticketGroups = [
        ['label' => 'State',    'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['group_by' => 'state']))],
        ['label' => 'Priority', 'url' => route('workflow.tickets.index', array_merge(request()->except('page'), ['group_by' => 'priority']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'kanban' ? 'bg-gray-100' : 'bg-white' }}" x-data="{ checked: [] }">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Workflow\Ticket::class)
        <a href="{{ route('workflow.tickets.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            New
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">Tickets</span>
        </div>

        <x-search
            :model="\App\Models\Workflow\Ticket::class"
            :action="route('workflow.tickets.index')"
            :preserve="['view' => $view]"
            :quick-filters="$ticketQuickFilters"
            :group-by="$ticketGroups"
        />

        <div class="ml-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($tickets->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $tickets->firstItem() }}-{{ $tickets->lastItem() }} / {{ $tickets->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0 records</span>
            @endif

            <div class="flex items-center gap-1">
                @if($tickets->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $tickets->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($tickets->hasMorePages())
                    <a href="{{ $tickets->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>

            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ route('workflow.tickets.index', array_merge(request()->except('view','page'), ['view' => 'kanban'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'kanban' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="Kanban view">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M3 3h6v14H3V3zm8 0h6v6h-6V3zm0 8h6v6h-6v-6z"/>
                    </svg>
                </a>
                <a href="{{ route('workflow.tickets.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="List view">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    @if($view === 'kanban')
    <div class="flex-1 overflow-y-auto p-3 sm:p-4">
        @if($tickets->isEmpty())
            <div class="py-24 text-center text-gray-400">
                <p class="text-sm font-medium text-gray-500">No tickets found</p>
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3">
                @foreach($tickets as $ticket)
                <a href="{{ route('workflow.tickets.show', $ticket) }}" class="relative group bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                    <x-state-ribbon :state="$ticket->state" />
                    <div class="p-3">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
                            @if($ticket->isOverdue())
                                <span class="text-[10px] font-semibold text-red-600 uppercase">Overdue</span>
                            @endif
                        </div>
                        <h3 class="text-sm font-semibold text-gray-900 group-hover:text-purple-700 line-clamp-2">{{ $ticket->name }}</h3>
                        @if($ticket->template)
                            <p class="text-xs text-gray-500 truncate mt-1">{{ $ticket->template->name }}</p>
                        @endif
                        @if($ticket->assignedDepartment)
                            <p class="text-xs text-gray-400 truncate mt-0.5">{{ $ticket->assignedDepartment->name }}</p>
                        @endif
                        @if(!$ticket->active)
                            <span class="inline-block mt-1 text-[10px] font-semibold text-amber-600 uppercase">Archived</span>
                        @endif
                    </div>
                </a>
                @endforeach
            </div>
        @endif
    </div>
    @else
    <x-list :paginator="$tickets" empty-text="No tickets found.">
        <x-slot:columns>
            <x-sortable-th column="id"         label="ID"         class="px-4 py-2" />
            <x-sortable-th column="name"       label="Name"       class="px-3 py-2" :default="true" />
            <x-sortable-th column="state"      label="State"      class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Assigned To</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Department</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Template</th>
            <x-sortable-th column="deadline"   label="Deadline"   class="px-3 py-2" />
            <x-sortable-th column="duration"   label="Duration"   class="px-3 py-2" />
            <x-sortable-th column="sla_passed" label="SLA Passed" class="px-3 py-2" />
            <x-sortable-th column="sla_limit"  label="SLA Limit"  class="px-3 py-2" />
            <x-sortable-th column="created_at" label="Created"    class="px-3 py-2" />
        </x-slot:columns>

        @foreach($tickets as $ticket)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.tickets.show', $ticket) }}'">
            <td class="px-4 py-2 text-gray-500">{{ $ticket->id }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">
                {{ $ticket->name }}
                @if(!$ticket->active)
                    <span class="ml-1.5 text-[10px] text-amber-600 font-semibold uppercase">Archived</span>
                @endif
                @if($ticket->isOverdue())
                    <span class="ml-1.5 text-[10px] text-red-600 font-semibold uppercase">Overdue</span>
                @endif
            </td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $ticket->assignedUser?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $ticket->assignedDepartment?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $ticket->template?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-500 text-xs">{{ $ticket->resolve_deadline?->format('M j, Y H:i') ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $ticket->resolve_duration ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $ticket->resolve_deadline_passed ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $ticket->resolve_max_duration ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-500 text-xs">{{ $ticket->created_at->format('M j, Y') }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
