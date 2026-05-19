@extends('layouts.app')
@section('title', $procedure->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50" x-data="{ compose: false }">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 shrink-0">
            @can('create', \App\Models\Workflow\Procedure::class)
            <a href="{{ route('workflow.procedures.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded">New</a>
            @endcan
            <div class="flex flex-col leading-tight">
                <a href="{{ route('workflow.procedures.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Procedures</a>
                <span class="text-sm font-semibold text-gray-800">{{ $procedure->name }}</span>
            </div>
        </div>

        <div class="ml-auto flex items-center gap-3 shrink-0 flex-wrap">
            @can('update', $procedure)
            @if($procedure->state !== 'pending')
            @if($procedure->active)
            <form method="POST" action="{{ route('workflow.procedures.archive', $procedure) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Archive</button>
            </form>
            @else
            <form method="POST" action="{{ route('workflow.procedures.unarchive', $procedure) }}">
                @csrf @method('PATCH')
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">Restore</button>
            </form>
            @endif
            @endif
            @endcan

            @can('update', $procedure)
            @if($procedure->sharedLink?->enabled)
            <form method="POST" action="{{ route('workflow.share.procedure.toggle', $procedure) }}">
                @csrf
                <button class="px-3 py-1.5 text-sm text-green-700 border border-green-300 rounded hover:bg-green-50 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Sharing On
                </button>
            </form>
            @else
            <form method="POST" action="{{ route('workflow.share.procedure.toggle', $procedure) }}">
                @csrf
                <button class="px-3 py-1.5 text-sm text-purple-700 border border-purple-200 rounded hover:bg-purple-50 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Share
                </button>
            </form>
            @endif
            @endcan

        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            @if(!$procedure->active)
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">This procedure is archived.</div>
            </div>
            @endif

            @if(session('success'))
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-green-700 bg-green-50 border border-green-200 rounded-lg px-3 py-2">{{ session('success') }}</div>
            </div>
            @endif

            @if(session('error'))
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2">{{ session('error') }}</div>
            </div>
            @endif

            <div class="p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $procedure->stateColor() }}">{{ $procedure->stateLabel() }}</span>
                        @if($procedure->isOverdue())
                            <span class="text-xs text-red-600 font-semibold">Overdue</span>
                        @endif
                    </div>
                    <div class="inline-flex items-center overflow-hidden rounded border border-gray-200 text-xs">
                        @foreach(['pending' => 'In Progress', 'completed' => 'Completed', 'closed' => 'Canceled'] as $state => $label)
                        <span class="px-3 py-1 {{ $procedure->state === $state ? 'bg-[#714B67] text-white' : 'bg-white text-gray-500' }} {{ !$loop->last ? 'border-r border-gray-200' : '' }}">{{ $label }}</span>
                        @endforeach
                    </div>
                </div>
                <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ $procedure->name }}</h1>

                @if($procedure->description)
                <p class="text-sm text-gray-600 mb-6">{{ $procedure->description }}</p>
                @endif

                <div class="flex gap-8">
                    <div class="flex-1">
                        @foreach([
                            ['Template',   $procedure->procedureTemplate?->name],
                            ['Created By', $procedure->createdByUser?->name],
                            ['Deadline',   $procedure->resolve_deadline?->format('M j, Y H:i')],
                            ['Duration',   $procedure->resolve_duration],
                            ['SLA Passed', $procedure->resolve_deadline_passed],
                            ['SLA Limit',  $procedure->resolve_max_duration],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>

            @if($procedure->sharedLink?->enabled)
            @can('update', $procedure)
            <div class="border-t border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"/></svg>
                    Share Link
                </h3>
                <div x-data="{ copied: false }" class="flex items-center gap-2 mb-4">
                    <input type="text" readonly value="{{ $procedure->sharedLink->shareUrl() }}"
                           class="flex-1 text-sm border border-gray-200 rounded px-3 py-1.5 bg-gray-50 text-gray-700 select-all">
                    <button @click="navigator.clipboard.writeText('{{ $procedure->sharedLink->shareUrl() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="px-3 py-1.5 text-sm border rounded transition-colors"
                            :class="copied ? 'border-green-300 text-green-700 bg-green-50' : 'border-gray-200 text-gray-600 hover:bg-gray-50'">
                        <span x-text="copied ? 'Copied!' : 'Copy'"></span>
                    </button>
                </div>
                <form method="POST" action="{{ route('workflow.share.procedure.message', $procedure) }}">
                    @csrf @method('PATCH')
                    <label class="block text-xs text-gray-500 mb-1">Message for recipient (optional)</label>
                    <textarea name="message" rows="3"
                              class="w-full text-sm border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-none"
                              placeholder="Enter a message the recipient will see on the shared page…">{{ $procedure->sharedLink->message }}</textarea>
                    <div class="mt-2 flex justify-end">
                        <button type="submit" class="px-3 py-1.5 text-sm bg-[#714B67] hover:bg-[#5c3d55] text-white rounded">Save Message</button>
                    </div>
                </form>
            </div>
            @endcan
            @endif

            <div class="border-t border-gray-200 p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4">Tickets</h3>
                <div class="space-y-3">
                    @forelse($procedure->tickets->sortBy('task_sequence') as $ticket)
                    <div class="border border-gray-200 rounded-lg p-4" x-data="{ open: false }">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 flex-1 min-w-0">
                                <div class="mt-0.5">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $ticket->stateColor() }}">
                                        {{ $ticket->stateLabel() }}
                                    </span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <a href="{{ route('workflow.tickets.show', $ticket) }}" class="text-sm font-semibold text-gray-800 hover:text-purple-700">{{ $ticket->task_sequence }}. {{ $ticket->name }}</a>
                                    @if($ticket->description)
                                    <p class="text-xs text-gray-500 mt-0.5">{{ $ticket->description }}</p>
                                    @endif
                                </div>
                            </div>

                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-400">No tickets.</p>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                :model="$procedure"
                :messages="$messages"
                :comment-url="route('workflow.procedures.comment', $procedure)"
                :can-comment="auth()->user()->can('comment', $procedure)"
            />
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>Created {{ $procedure->created_at->format('M d, Y') }}{{ $procedure->creator ? ' by ' . $procedure->creator->name : '' }}</span>
            <span>Updated {{ $procedure->updated_at->diffForHumans() }}{{ $procedure->updater ? ' by ' . $procedure->updater->name : '' }}</span>
        </div>
    </div>
</div>
@endsection
