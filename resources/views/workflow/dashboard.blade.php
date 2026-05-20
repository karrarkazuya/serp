@extends('layouts.app')
@section('title', 'Workflow Dashboard')

@section('content')
<style>
@keyframes cardFadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to   { opacity: 1; transform: none; }
}
.stat-card  { animation: cardFadeIn 0.35s ease both; }
.panel-card { animation: cardFadeIn 0.4s ease both; }
</style>

<div class="flex flex-col h-full bg-gray-50 overflow-y-auto">

    {{-- Header --}}
    <div class="bg-white border-b border-gray-200 px-6 py-4 shrink-0 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-lg font-bold text-gray-900">Workflow</h1>
            <p class="text-xs text-gray-400 mt-0.5">Your active tickets and procedures</p>
        </div>
        <div class="flex items-center gap-2">
            @can('create', \App\Models\Workflow\Ticket::class)
            <a href="{{ route('workflow.tickets.create') }}"
               class="px-4 py-2 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded-lg transition-colors shadow-sm">
                Submit Ticket
            </a>
            @endcan
            @can('create', \App\Models\Workflow\Procedure::class)
            <a href="{{ route('workflow.procedures.create') }}"
               class="px-4 py-2 text-sm font-medium text-[#714B67] border border-[#714B67]/30 rounded-lg hover:bg-[#714B67]/5 transition-colors">
                Start Procedure
            </a>
            @endcan
        </div>
    </div>

    <div class="flex-1 p-5 space-y-5">

        {{-- Stats Row --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">

            <div class="stat-card bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 hover:shadow-md transition-shadow" style="animation-delay:0ms">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">My Tickets</span>
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900">{{ $ticketsAssignedCount }}</div>
                <a href="{{ route('workflow.tickets.index', ['state' => 'pending']) }}" class="text-xs text-blue-600 hover:text-blue-700 mt-1 inline-block">View pending →</a>
            </div>

            <div class="stat-card bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 hover:shadow-md transition-shadow" style="animation-delay:60ms">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">In Procedures</span>
                    <div class="w-8 h-8 rounded-lg bg-purple-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold text-gray-900">{{ $procedureTicketsAssignedCount }}</div>
                <a href="{{ route('workflow.procedures.index', ['state' => 'pending']) }}" class="text-xs text-purple-600 hover:text-purple-700 mt-1 inline-block">View procedures →</a>
            </div>

            <div class="stat-card bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 hover:shadow-md transition-shadow" style="animation-delay:120ms">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Overdue</span>
                    <div class="w-8 h-8 rounded-lg {{ $overdueCount > 0 ? 'bg-red-50' : 'bg-gray-50' }} flex items-center justify-center">
                        <svg class="w-4 h-4 {{ $overdueCount > 0 ? 'text-red-500' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold {{ $overdueCount > 0 ? 'text-red-600' : 'text-gray-900' }}">{{ $overdueCount }}</div>
                <p class="text-xs text-gray-400 mt-1">{{ $overdueCount > 0 ? 'Need attention' : 'All on time' }}</p>
            </div>

            <div class="stat-card bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 hover:shadow-md transition-shadow" style="animation-delay:180ms">
                <div class="flex items-center justify-between mb-3">
                    <span class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Done Today</span>
                    <div class="w-8 h-8 rounded-lg {{ $completedTodayCount > 0 ? 'bg-green-50' : 'bg-gray-50' }} flex items-center justify-center">
                        <svg class="w-4 h-4 {{ $completedTodayCount > 0 ? 'text-green-500' : 'text-gray-300' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                        </svg>
                    </div>
                </div>
                <div class="text-3xl font-bold {{ $completedTodayCount > 0 ? 'text-green-600' : 'text-gray-900' }}">{{ $completedTodayCount }}</div>
                <p class="text-xs text-gray-400 mt-1">Completed today</p>
            </div>

        </div>

        {{-- Charts Row --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-stretch">

            {{-- Activity Bar Chart --}}
            <div class="panel-card lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-5" style="animation-delay:220ms">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Completion Activity</p>
                        <p class="text-xs text-gray-400 mt-0.5">Tickets completed · last 7 days</p>
                    </div>
                    @php $totalActivity = array_sum($chartActivity->toArray()); @endphp
                    <span class="text-2xl font-bold text-gray-900">{{ $totalActivity }}</span>
                </div>
                <div class="relative" style="height:160px">
                    <canvas id="activity-chart"></canvas>
                </div>
            </div>

            {{-- Status Donut Chart --}}
            <div class="panel-card bg-white rounded-xl border border-gray-200 shadow-sm p-5" style="animation-delay:280ms">
                <div class="mb-4">
                    <p class="text-sm font-semibold text-gray-800">Ticket Breakdown</p>
                    <p class="text-xs text-gray-400 mt-0.5">My standalone tickets · by state</p>
                </div>
                @php $totalTickets = array_sum($chartStatus); @endphp
                <div class="relative flex items-center justify-center" style="height:140px">
                    <canvas id="status-chart"></canvas>
                    <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                        <span class="text-2xl font-bold text-gray-900">{{ $totalTickets }}</span>
                        <span class="text-xs text-gray-400">total</span>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2">
                    @php
                        $statusItems = [
                            ['label' => 'Pending',   'color' => 'bg-blue-500',  'value' => $chartStatus[0]],
                            ['label' => 'Completed', 'color' => 'bg-green-500', 'value' => $chartStatus[1]],
                            ['label' => 'Closed',    'color' => 'bg-gray-400',  'value' => $chartStatus[2]],
                            ['label' => 'Rejected',  'color' => 'bg-red-400',   'value' => $chartStatus[3]],
                        ];
                    @endphp
                    @foreach($statusItems as $item)
                    <div class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full shrink-0 {{ $item['color'] }}"></span>
                        <span class="text-xs text-gray-500 truncate">{{ $item['label'] }}</span>
                        <span class="text-xs font-semibold text-gray-800 ml-auto">{{ $item['value'] }}</span>
                    </div>
                    @endforeach
                </div>
            </div>

        </div>

        {{-- Two Panels --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-start">

            {{-- Pending Tickets --}}
            <div class="panel-card bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" style="animation-delay:340ms">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-800">My Pending Tickets</span>
                        @if($pendingTickets->isNotEmpty())
                        <span class="text-xs bg-blue-100 text-blue-700 rounded-full px-2 py-0.5 font-medium">{{ $pendingTickets->count() }}</span>
                        @endif
                    </div>
                    <a href="{{ route('workflow.tickets.index', ['state' => 'pending']) }}" class="text-xs text-[#714B67] hover:text-[#5c3d55] font-medium">View all</a>
                </div>

                @forelse($pendingTickets as $ticket)
                <a href="{{ route('workflow.tickets.show', $ticket) }}"
                   class="flex items-start gap-3 px-5 py-3.5 border-b border-gray-50 hover:bg-gray-50/70 transition-colors group">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
                            <div class="flex items-center gap-0.5">
                                @foreach([1,2,3] as $s)
                                <svg class="w-3 h-3 {{ $ticket->priority >= $s ? 'text-amber-400' : 'text-gray-200' }}" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                                @endforeach
                            </div>
                            @if($ticket->isOverdue())
                            <span class="text-[11px] font-semibold text-red-500">Overdue</span>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-gray-800 group-hover:text-[#714B67] transition-colors leading-snug truncate">{{ $ticket->name }}</p>
                        @if($ticket->template || $ticket->assignedDepartment)
                        <p class="text-xs text-gray-400 mt-0.5 truncate">
                            {{ $ticket->template?->name }}{{ ($ticket->template && $ticket->assignedDepartment) ? ' · ' : '' }}{{ $ticket->assignedDepartment?->name }}
                        </p>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-[#714B67] shrink-0 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                @empty
                <div class="py-14 text-center">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-400">All caught up!</p>
                    <p class="text-xs text-gray-300 mt-0.5">No pending tickets assigned to you</p>
                </div>
                @endforelse
            </div>

            {{-- Pending Procedure Tickets --}}
            <div class="panel-card bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" style="animation-delay:400ms">
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-semibold text-gray-800">Procedure Tickets</span>
                        @if($pendingProcedureTickets->isNotEmpty())
                        <span class="text-xs bg-purple-100 text-purple-700 rounded-full px-2 py-0.5 font-medium">{{ $pendingProcedureTickets->count() }}</span>
                        @endif
                    </div>
                    <a href="{{ route('workflow.procedures.index', ['state' => 'pending']) }}" class="text-xs text-[#714B67] hover:text-[#5c3d55] font-medium">View all</a>
                </div>

                @forelse($pendingProcedureTickets as $ticket)
                <a href="{{ route('workflow.procedures.show', $ticket->procedure_id) }}"
                   class="flex items-start gap-3 px-5 py-3.5 border-b border-gray-50 hover:bg-gray-50/70 transition-colors group">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 mb-0.5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
                            @if($ticket->isOverdue())
                            <span class="text-[11px] font-semibold text-red-500">Overdue</span>
                            @endif
                        </div>
                        <p class="text-sm font-semibold text-gray-800 group-hover:text-[#714B67] transition-colors leading-snug truncate">{{ $ticket->name }}</p>
                        @if($ticket->procedure || $ticket->assignedDepartment)
                        <p class="text-xs text-gray-400 mt-0.5 truncate">
                            {{ $ticket->procedure?->name }}{{ ($ticket->procedure && $ticket->assignedDepartment) ? ' · ' : '' }}{{ $ticket->assignedDepartment?->name }}
                        </p>
                        @endif
                    </div>
                    <svg class="w-4 h-4 text-gray-300 group-hover:text-[#714B67] shrink-0 mt-0.5 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                @empty
                <div class="py-14 text-center">
                    <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                        <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    </div>
                    <p class="text-sm font-medium text-gray-400">No procedure tickets</p>
                    <p class="text-xs text-gray-300 mt-0.5">You have no pending steps in any procedure</p>
                </div>
                @endforelse
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
(function () {
    const brandColor = '#714B67';

    // Activity bar chart
    new Chart(document.getElementById('activity-chart'), {
        type: 'bar',
        data: {
            labels: @json($chartLabels),
            datasets: [{
                data: @json($chartActivity),
                backgroundColor: (ctx) => {
                    const max = Math.max(...ctx.dataset.data, 1);
                    return ctx.dataset.data.map((v, i) =>
                        i === ctx.dataset.data.indexOf(Math.max(...ctx.dataset.data)) && max > 0
                            ? brandColor : '#E5DDEF'
                    );
                },
                borderRadius: 6,
                borderSkipped: false,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: {
                callbacks: { label: (ctx) => ` ${ctx.parsed.y} completed` }
            }},
            scales: {
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { font: { size: 11, family: 'Inter, sans-serif' }, color: '#9CA3AF' }
                },
                y: {
                    grid: { color: '#F3F4F6', drawTicks: false },
                    border: { display: false },
                    ticks: { font: { size: 11 }, color: '#9CA3AF', precision: 0, padding: 8 },
                    beginAtZero: true
                }
            }
        }
    });

    // Status donut chart
    const statusTotal = {{ array_sum($chartStatus) }};
    new Chart(document.getElementById('status-chart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending', 'Completed', 'Closed', 'Rejected'],
            datasets: [{
                data: @json($chartStatus),
                backgroundColor: ['#3B82F6', '#22C55E', '#9CA3AF', '#F87171'],
                borderWidth: 0,
                hoverOffset: 5,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '72%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (ctx) => {
                            const pct = statusTotal > 0 ? Math.round(ctx.parsed / statusTotal * 100) : 0;
                            return ` ${ctx.parsed} (${pct}%)`;
                        }
                    }
                }
            }
        }
    });
})();
</script>
@endsection
