@extends('layouts.app')
@section('title', $move->name ?: 'Journal Entry')

@php
    $stateColor = match($move->state) {
        'posted'    => 'bg-green-100 text-green-700',
        'draft'     => 'bg-amber-100 text-amber-700',
        'cancelled' => 'bg-gray-200 text-gray-600',
        default     => 'bg-gray-100 text-gray-600',
    };
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountMove::class)
        @php $newHref = route('accounting.moves.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.moves.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.moves.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.moves.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Journal Entries</a>
            <span class="text-sm font-semibold text-gray-800">{{ $move->name ?: 'Draft' }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if($move->isDraft())
                    @can('update', $move)
                    <a href="{{ route('accounting.moves.edit', $move) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                    @endcan
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.post', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm font-medium text-white bg-green-700 hover:bg-green-800 rounded">Post</button>
                    </form>
                    <form method="POST" action="{{ route('accounting.moves.cancel', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50">Cancel Entry</button>
                    </form>
                    @endcan
                @elseif($move->isPosted())
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.reset-draft', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Reset to Draft</button>
                    </form>
                    <form method="POST" action="{{ route('accounting.moves.reverse', $move) }}" x-data="{ open: false }">
                        @csrf
                        <button type="button" @click="open = !open" class="px-3 py-1.5 text-sm text-purple-700 border border-purple-200 rounded hover:bg-purple-50">Reverse</button>
                        <div x-show="open" style="display:none" class="absolute right-4 mt-1 z-20 bg-white border border-gray-200 rounded-lg shadow-lg p-3 flex items-end gap-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase">Reversal Date</label>
                                <input type="date" name="reversal_date" value="{{ now()->toDateString() }}" class="text-sm border border-gray-200 rounded px-2 py-1">
                            </div>
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-purple-700 hover:bg-purple-800 rounded">Confirm</button>
                            <button type="button" @click="open = false" class="px-2 py-1 text-xs text-gray-500">Cancel</button>
                        </div>
                    </form>
                    @endcan
                @elseif($move->isCancelled())
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.reset-draft', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Reset to Draft</button>
                    </form>
                    @endcan
                @endif

                @can('delete', $move)
                <form method="POST" action="{{ route('accounting.moves.delete', $move) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this entry?</span>
                        <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">Yes</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">Cancel</button>
                    </div>
                </form>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        @if(session('error'))
        <div class="mx-4 mt-4 px-3 py-2 bg-red-50 border border-red-200 text-sm text-red-700 rounded">{{ session('error') }}</div>
        @endif

        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="p-6">
                <div class="flex items-center gap-3 mb-1">
                    <span class="text-sm text-gray-600">{{ \App\Models\Accounting\AccountMove::MOVE_TYPES[$move->move_type] ?? 'Journal Entry' }}</span>
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $stateColor }}">{{ $move->state_label }}</span>
                </div>
                <h1 class="text-3xl font-bold {{ $move->name ? 'text-gray-900' : 'text-gray-400' }}">{{ $move->name ?: 'Draft' }}</h1>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 mt-6">
                    <div>
                        @foreach([
                            ['Journal',   $move->journal?->name],
                            ['Date',      optional($move->date)->format('Y-m-d')],
                            ['Reference', $move->ref],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        @foreach([
                            ['Company',  $move->company?->name],
                            ['Partner',  $move->partner?->name],
                            ['Currency', $move->currency],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                @if($move->reversedMove)
                <div class="mt-4 px-3 py-2 bg-purple-50 border border-purple-200 rounded text-sm text-purple-700">
                    Reverses
                    <a href="{{ route('accounting.moves.show', $move->reversedMove) }}" class="font-semibold underline">{{ $move->reversedMove->name }}</a>
                </div>
                @endif
                @if($move->reversal->isNotEmpty())
                <div class="mt-4 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-sm text-amber-700">
                    Reversed by
                    @foreach($move->reversal as $rev)
                    <a href="{{ route('accounting.moves.show', $rev) }}" class="font-semibold underline">{{ $rev->name }}</a>{{ !$loop->last ? ', ' : '' }}
                    @endforeach
                </div>
                @endif

                <div class="mt-6 border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-xs font-semibold text-gray-500 uppercase">
                                <th class="px-3 py-2 text-left">Account</th>
                                <th class="px-3 py-2 text-left">Label</th>
                                <th class="px-3 py-2 text-left">Partner</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($move->lines as $line)
                            <tr>
                                <td class="px-3 py-1.5 text-gray-800">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                                <td class="px-3 py-1.5 text-gray-700">{{ $line->name }}</td>
                                <td class="px-3 py-1.5 text-gray-600">{{ $line->partner?->name ?: '—' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ (float) $line->debit  ? number_format((float) $line->debit, 2)  : '' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ (float) $line->credit ? number_format((float) $line->credit, 2) : '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-sm">
                            <tr>
                                <td colspan="3" class="px-3 py-2 text-right text-gray-700">Totals</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($balance['debit'], 2) }}</td>
                                <td class="px-3 py-2 text-right tabular-nums">{{ number_format($balance['credit'], 2) }}</td>
                            </tr>
                            @if(abs($balance['difference']) > 0.005)
                            <tr class="bg-amber-50 text-amber-700">
                                <td colspan="3" class="px-3 py-2 text-right">Difference</td>
                                <td colspan="2" class="px-3 py-2 text-right tabular-nums">{{ number_format($balance['difference'], 2) }}</td>
                            </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>

                @if($move->narration)
                <div class="mt-6">
                    <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Narration</p>
                    <p class="text-sm text-gray-700 whitespace-pre-line">{{ $move->narration }}</p>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Accounting\AccountMove"
                :model-id="$move->id"
                :can-comment="auth()->user()->can('comment', $move)"
            />
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>Created: {{ $move->created_at?->format('M d, Y') }}{{ $move->creator ? ' · ' . $move->creator->name : '' }}</span>
            @if($move->posted_at)
            <span>Posted: {{ $move->posted_at->format('M d, Y H:i') }}{{ $move->poster ? ' · ' . $move->poster->name : '' }}</span>
            @endif
            <span>Updated: {{ $move->updated_at?->diffForHumans() }}{{ $move->updater ? ' · ' . $move->updater->name : '' }}</span>
        </div>
    </div>
</div>
@endsection
