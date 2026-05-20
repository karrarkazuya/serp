@extends('layouts.app')
@section('title', $procedure->name)

@section('content')
<style>
@keyframes stepIn { from { opacity:0; transform:translateX(-5px) } to { opacity:1; transform:none } }
.workflow-step { animation: stepIn 0.3s ease both; }
@keyframes progressGrow { from { width:0 } }
.progress-bar { animation: progressGrow 0.7s cubic-bezier(0.4,0,0.2,1) both 0.15s; }
@keyframes pulseRing {
    0%,100% { box-shadow:0 0 0 0 rgba(113,75,103,0.25); }
    50%      { box-shadow:0 0 0 5px rgba(113,75,103,0); }
}
.step-active-ring { animation: pulseRing 2s ease-in-out infinite; }
</style>

<div class="flex flex-col h-full bg-gray-50">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-5 py-2 flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 shrink-0">
            @can('create', \App\Models\Workflow\Procedure::class)
            <a href="{{ route('workflow.procedures.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded transition-colors">New</a>
            @endcan
            <div>
                <a href="{{ route('workflow.procedures.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Procedures</a>
                <span class="text-sm font-semibold text-gray-800 block truncate max-w-xs">{{ $procedure->name }}</span>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-2 shrink-0">
            @can('update', $procedure)
            @if($procedure->state !== 'pending')
            @if($procedure->active)
            <form method="POST" action="{{ route('workflow.procedures.archive', $procedure) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50 transition-colors">Archive</button>
            </form>
            @else
            <form method="POST" action="{{ route('workflow.procedures.unarchive', $procedure) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50 transition-colors">Restore</button>
            </form>
            @endif
            @endif
            @endcan

            @can('update', $procedure)
            @if($procedure->sharedLink?->enabled)
            <form method="POST" action="{{ route('workflow.share.procedure.toggle', $procedure) }}">
                @csrf
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-300 rounded hover:bg-green-50 flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Sharing On
                </button>
            </form>
            @else
            <form method="POST" action="{{ route('workflow.share.procedure.toggle', $procedure) }}">
                @csrf
                <button class="px-3 py-1.5 text-sm text-purple-700 border border-purple-200 rounded hover:bg-purple-50 flex items-center gap-1.5 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Share
                </button>
            </form>
            @endif
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        @php
            $visibleTickets = $procedure->tickets
                ->filter(fn($t) => !in_array($t->state, ['draft', 'skipped']))
                ->sortBy('id')
                ->values();
            $totalSteps     = $visibleTickets->count();
            $completedSteps = $visibleTickets->where('state', 'completed')->count();
            $pendingSteps   = $visibleTickets->where('state', 'pending')->count();
            $rejectedSteps  = $visibleTickets->whereIn('state', ['rejected'])->count();
            $progress       = $totalSteps > 0 ? round(($completedSteps / $totalSteps) * 100) : 0;
        @endphp

        <div class="px-5 py-4 space-y-3">

            {{-- Alerts --}}
            @if(!$procedure->active)
            <div class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                This procedure is archived.
            </div>
            @endif
            @if(session('success'))
            <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">{{ session('success') }}</div>
            @endif
            @if(session('error'))
            <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ session('error') }}</div>
            @endif

            {{-- Two-column layout --}}
            <div class="flex gap-3 items-start">

                {{-- LEFT: header + chatter (75%) --}}
                <div class="flex-1 min-w-0 space-y-3">

                    {{-- Header card --}}
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4" x-data="{ details: false }">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold {{ $procedure->stateColor() }}">{{ $procedure->stateLabel() }}</span>
                                    @if($procedure->procedureTemplate)
                                    <span class="text-xs text-gray-400">{{ $procedure->procedureTemplate->name }}</span>
                                    @endif
                                </div>
                                <h1 class="text-xl font-bold text-gray-900 leading-snug">{{ $procedure->name }}</h1>
                                @if($procedure->description)
                                <p class="text-sm text-gray-500 mt-1 leading-relaxed">{{ $procedure->description }}</p>
                                @endif
                                @if($procedure->optionalTicket)
                                <a href="{{ route('workflow.tickets.show', $procedure->optionalTicket) }}"
                                   class="inline-flex items-center gap-1 mt-1.5 text-xs text-purple-600 hover:underline">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                                    {{ $procedure->optionalTicket->name }}
                                </a>
                                @endif
                            </div>

                            @if($totalSteps > 0)
                            <div class="shrink-0 text-right">
                                <div class="text-xl font-bold text-gray-900">{{ $completedSteps }}<span class="text-gray-300 font-normal text-base">/{{ $totalSteps }}</span></div>
                                <div class="text-xs text-gray-400">steps done</div>
                            </div>
                            @endif
                        </div>

                        @if($totalSteps > 0)
                        <div class="mt-3">
                            <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="progress-bar h-full rounded-full {{ $progress === 100 ? 'bg-green-500' : 'bg-[#714B67]' }}"
                                     style="width:{{ $progress }}%"></div>
                            </div>
                            <div class="flex items-center justify-between mt-1 text-xs text-gray-400">
                                <span>{{ $completedSteps }} done{{ $pendingSteps > 0 ? ' · ' . $pendingSteps . ' in progress' : '' }}{{ $rejectedSteps > 0 ? ' · ' . $rejectedSteps . ' returned' : '' }}</span>
                                <span>{{ $progress }}%</span>
                            </div>
                        </div>
                        @endif

                        {{-- Details toggle --}}
                        <div class="mt-3 pt-3 border-t border-gray-100">
                            <button @click="details=!details"
                                    class="flex items-center gap-1 text-xs text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-3.5 h-3.5 transition-transform duration-200" :class="details ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                                <span x-text="details ? 'Hide details' : 'Show details'"></span>
                            </button>
                            <div x-show="details" x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0" style="display:none"
                                 class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3">
                                @if($procedure->createdByUser)
                                <div>
                                    <p class="text-xs text-gray-400 mb-0.5">Created by</p>
                                    <p class="text-sm font-medium text-gray-800">{{ $procedure->createdByUser->name }}</p>
                                </div>
                                @endif
                                <div>
                                    <p class="text-xs text-gray-400 mb-0.5">Created</p>
                                    <p class="text-sm font-medium text-gray-800">{{ $procedure->created_at->format('M j, Y') }}</p>
                                </div>
                                <div>
                                    <p class="text-xs text-gray-400 mb-0.5">Last updated</p>
                                    <p class="text-sm font-medium text-gray-800">{{ $procedure->updated_at->diffForHumans() }}</p>
                                </div>
                                @if($procedure->resolve_duration)
                                <div>
                                    <p class="text-xs text-gray-400 mb-0.5">Duration</p>
                                    <p class="text-sm font-medium text-gray-800">{{ $procedure->resolve_duration }}h</p>
                                </div>
                                @endif
                                @if($procedure->procedureTemplate)
                                <div>
                                    <p class="text-xs text-gray-400 mb-0.5">Template</p>
                                    <p class="text-sm font-medium text-gray-800">{{ $procedure->procedureTemplate->name }}</p>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Share link --}}
                    @if($procedure->sharedLink?->enabled)
                    @can('update', $procedure)
                    <div class="bg-white rounded-xl border border-green-200 shadow-sm p-4">
                        <p class="text-xs font-semibold text-gray-600 mb-2">Share Link</p>
                        <div x-data="{ copied: false }" class="flex items-center gap-2 mb-2">
                            <input type="text" readonly value="{{ $procedure->sharedLink->shareUrl() }}"
                                   class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-1.5 bg-gray-50 text-gray-600 select-all">
                            <button @click="navigator.clipboard.writeText('{{ $procedure->sharedLink->shareUrl() }}'); copied=true; setTimeout(()=>copied=false,2000)"
                                    class="px-3 py-1.5 text-sm border rounded-lg transition-colors shrink-0"
                                    :class="copied ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                                <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                            </button>
                        </div>
                        <form method="POST" action="{{ route('workflow.share.procedure.message', $procedure) }}">
                            @csrf @method('PATCH')
                            <textarea name="message" rows="2" placeholder="Message for recipient (optional)"
                                      class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none mb-2">{{ $procedure->sharedLink->message }}</textarea>
                            <button type="submit" class="px-3 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded-lg transition-colors">Save Message</button>
                        </form>
                    </div>
                    @endcan
                    @endif

                    {{-- Activity --}}
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <x-chatter
                            model-type="App\Models\Workflow\Procedure"
                            :model-id="$procedure->id"
                            :can-comment="auth()->user()->can('comment', $procedure)"
                        />
                    </div>

                </div>
                {{-- END LEFT --}}

                {{-- RIGHT: workflow steps (25%) --}}
                <div class="w-1/4 shrink-0">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden sticky top-4">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <span class="text-sm font-semibold text-gray-800">Steps</span>
                            @if($totalSteps > 0)
                            <span class="text-xs text-gray-400">{{ $completedSteps }}/{{ $totalSteps }}</span>
                            @endif
                        </div>

                        @if($visibleTickets->isEmpty())
                        <div class="py-8 text-center">
                            <p class="text-sm text-gray-400">No active steps</p>
                        </div>
                        @else
                        <div class="px-4 py-3">
                            <div class="space-y-0">
                                @foreach($visibleTickets as $i => $ticket)
                                @php
                                    $isCompleted = $ticket->state === 'completed';
                                    $isPending   = $ticket->state === 'pending';
                                    $isRejected  = $ticket->state === 'rejected';
                                    $isClosed    = $ticket->state === 'closed';
                                    $isLast      = $loop->last;

                                    $circleClass = match(true) {
                                        $isCompleted => 'bg-green-500 text-white',
                                        $isPending   => 'bg-[#714B67] text-white step-active-ring',
                                        $isRejected  => 'bg-red-100 text-red-600 border-2 border-red-200',
                                        default      => 'bg-gray-100 text-gray-400 border-2 border-gray-200',
                                    };
                                    $lineClass = $isCompleted ? 'bg-green-200' : 'bg-gray-100';
                                @endphp

                                <div class="workflow-step flex items-stretch gap-2.5 group cursor-pointer rounded-lg hover:bg-gray-50 transition-colors -mx-1 px-1"
                                     style="animation-delay:{{ $i * 50 }}ms"
                                     onclick="window.location='{{ route('workflow.tickets.show', $ticket) }}'">
                                    {{-- Timeline --}}
                                    <div class="flex flex-col items-center shrink-0 w-7">
                                        <div class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 z-10 transition-transform duration-200 group-hover:scale-110 {{ $circleClass }}">
                                            @if($isCompleted)
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                                            @elseif($isRejected)
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            @elseif($isClosed)
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                            @else
                                            <span class="w-2 h-2 rounded-full bg-current inline-block"></span>
                                            @endif
                                        </div>
                                        @if(!$isLast)
                                        <div class="w-0.5 flex-1 min-h-3 mt-1 rounded-full {{ $lineClass }}"></div>
                                        @endif
                                    </div>

                                    {{-- Row --}}
                                    <div class="flex-1 min-w-0 {{ $isLast ? 'pb-0' : 'pb-3' }}">
                                        <a href="{{ route('workflow.tickets.show', $ticket) }}"
                                           class="block">
                                            <div class="flex items-center gap-1.5 mb-0.5">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $ticket->stateColor() }}">{{ $ticket->stateLabel() }}</span>
                                            </div>
                                            <p class="text-xs font-semibold text-gray-800 group-hover:text-[#714B67] transition-colors leading-snug truncate">{{ $ticket->name }}</p>
                                            @if($ticket->assignedDepartment || $ticket->assignedUser)
                                            <p class="text-[11px] text-gray-400 mt-0.5 truncate">{{ $ticket->assignedDepartment?->name ?? $ticket->assignedUser?->name }}</p>
                                            @endif
                                            @if($ticket->return_reason)
                                            <p class="text-[11px] text-red-400 mt-0.5 truncate">↩ {{ $ticket->return_reason }}</p>
                                            @endif
                                        </a>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>
                </div>
                {{-- END RIGHT --}}

            </div>
            {{-- END two-column --}}

        </div>
    </div>
</div>
@endsection
