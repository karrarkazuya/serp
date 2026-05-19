@extends('layouts.app')
@section('title', 'Workflow Dashboard')

@section('content')
<div class="flex flex-col h-full bg-gray-100 overflow-y-auto">

    {{-- Banner --}}
    <div class="relative shrink-0 overflow-hidden" style="background: linear-gradient(135deg, #7c3810 0%, #b85820 50%, #c86828 100%);">
        <div class="relative px-8 py-7 flex items-start justify-between gap-6">
            <div>
                <h1 class="text-2xl font-bold text-white">Workflow Dashboard</h1>
                <p class="text-white/70 text-sm mt-1">Overview of pending tickets and procedures for your account</p>
                <div class="mt-5 flex items-center gap-3">
                    <div class="bg-black/25 rounded-lg px-7 py-3 text-center">
                        <div class="text-3xl font-bold text-white">{{ $ticketsAssignedCount }}</div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60 mt-0.5">TICKETS ASSIGNED</div>
                    </div>
                    <div class="bg-black/25 rounded-lg px-7 py-3 text-center">
                        <div class="text-3xl font-bold text-white">{{ $procedureTicketsAssignedCount }}</div>
                        <div class="text-[11px] font-semibold uppercase tracking-wider text-white/60 mt-0.5">PROCEDURE TICKETS</div>
                    </div>
                </div>
            </div>
            <div class="flex flex-col gap-2 shrink-0 pt-1">
                @can('create', \App\Models\Workflow\Ticket::class)
                <a href="{{ route('workflow.tickets.create') }}"
                   class="px-5 py-2 bg-white text-gray-800 text-sm font-semibold rounded shadow hover:bg-gray-50 text-center">
                    Submit Ticket
                </a>
                @endcan
                @can('create', \App\Models\Workflow\Procedure::class)
                <a href="{{ route('workflow.procedures.create') }}"
                   class="px-5 py-2 bg-[#5c3010]/70 text-white text-sm font-semibold rounded shadow hover:bg-[#5c3010] text-center border border-white/20">
                    Start Procedure
                </a>
                @endcan
            </div>
        </div>
    </div>

    {{-- Panels --}}
    <div class="flex-1 p-5 grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

        {{-- Pending Tickets --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                    </div>
                    <span class="text-sm font-semibold text-gray-800">Pending Tickets</span>
                </div>
                <a href="{{ route('workflow.tickets.index', ['state' => 'pending']) }}" class="text-xs text-purple-600 hover:text-purple-700 font-medium">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70">
                            <th class="px-4 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Ticket</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Assigned</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingTickets as $ticket)
                        <tr class="border-b border-gray-50 hover:bg-gray-50/60 cursor-pointer" onclick="window.location='{{ route('workflow.tickets.show', $ticket) }}'">
                            <td class="px-4 py-2.5">
                                <div class="font-semibold text-gray-800 text-sm leading-snug">{{ $ticket->name }}</div>
                                <div class="text-gray-400 mt-0.5">#{{ $ticket->id }}{{ $ticket->template ? ' · ' . $ticket->template->name : '' }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $ticket->stateColor() }}">
                                    {{ $ticket->stateLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-gray-500">
                                <div>{{ $ticket->assignedUser ? $ticket->assignedUser->user?->name : '-' }}</div>
                                @if($ticket->assignedDepartment)
                                <div class="text-gray-400 mt-0.5">{{ $ticket->assignedDepartment->name }}{{ $ticket->company ? ' (' . $ticket->company->name . ')' : '' }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">
                                {{ $ticket->resolve_deadline?->format('Y-m-d H:i') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">No pending tickets</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Pending Procedure Tickets --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-lg bg-purple-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                    <span class="text-sm font-semibold text-gray-800">Pending Procedure Tickets</span>
                </div>
                <a href="{{ route('workflow.procedures.index', ['state' => 'pending']) }}" class="text-xs text-purple-600 hover:text-purple-700 font-medium">View all</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70">
                            <th class="px-4 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Ticket</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Status</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Assigned</th>
                            <th class="px-3 py-2.5 text-left font-semibold text-gray-400 uppercase tracking-wide">Deadline</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingProcedureTickets as $ticket)
                        <tr class="border-b border-gray-50 hover:bg-gray-50/60 cursor-pointer" onclick="window.location='{{ route('workflow.procedures.show', $ticket->procedure_id) }}'">
                            <td class="px-4 py-2.5">
                                <div class="font-semibold text-gray-800 text-sm leading-snug">{{ $ticket->name }}</div>
                                <div class="text-gray-400 mt-0.5">#{{ $ticket->id }}{{ $ticket->procedure ? ' · ' . $ticket->procedure->name : '' }}</div>
                            </td>
                            <td class="px-3 py-2.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $ticket->stateColor() }}">
                                    {{ $ticket->stateLabel() }}
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-gray-500">
                                <div>{{ $ticket->assignedUser?->name ?? '-' }}</div>
                                @if($ticket->assignedDepartment)
                                <div class="text-gray-400 mt-0.5">{{ $ticket->assignedDepartment->name }}{{ $ticket->procedure?->company ? ' (' . $ticket->procedure->company->name . ')' : '' }}</div>
                                @endif
                            </td>
                            <td class="px-3 py-2.5 text-gray-500 whitespace-nowrap">
                                {{ $ticket->resolve_deadline?->format('Y-m-d H:i') }}
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-sm text-gray-400">No pending procedure tickets</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>
@endsection
