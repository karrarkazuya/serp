@extends('layouts.app')
@section('title', $journal->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Accounting\AccountJournal::class)
        @php $newHref = route('accounting.journals.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('accounting.journals.show', $prevId) : null"
        :next-href="$nextId ? route('accounting.journals.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.journals.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Journals</a>
            <span class="text-sm font-semibold text-gray-800">{{ $journal->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('create', \App\Models\Accounting\AccountMove::class)
                <a href="{{ route('accounting.moves.create', ['journal_id' => $journal->id]) }}" class="px-3 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">New Entry</a>
                @endcan
                @can('update', $journal)
                <a href="{{ route('accounting.journals.edit', $journal) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @if($journal->active)
                <form method="POST" action="{{ route('accounting.journals.archive', $journal) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">Archive</button>
                </form>
                @else
                <form method="POST" action="{{ route('accounting.journals.unarchive', $journal) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">Restore</button>
                </form>
                @endif
                @endcan
                @can('delete', $journal)
                <form method="POST" action="{{ route('accounting.journals.delete', $journal) }}" x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">Delete this journal?</span>
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
            @if(!$journal->active)
            <div class="px-6 pt-4 pb-0">
                <div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">This journal is archived.</div>
            </div>
            @endif

            <div class="p-6">
                <div class="text-sm text-gray-600 mb-1">{{ $journal->type_label }}</div>
                <h1 class="text-3xl font-bold text-gray-900">{{ $journal->code }} <span class="text-gray-700">{{ $journal->name }}</span></h1>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 mt-6">
                    <div>
                        @foreach([
                            ['Code',     $journal->code],
                            ['Type',     $journal->type_label],
                            ['Currency', $journal->currency ?: '—'],
                            ['Company',  $journal->company?->name],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">Default Account</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $journal->defaultAccount ? $journal->defaultAccount->code.' '.$journal->defaultAccount->name : '—' }}</span>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">Suspense Account</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $journal->suspenseAccount ? $journal->suspenseAccount->code.' '.$journal->suspenseAccount->name : '—' }}</span>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">Next Sequence</span>
                            <span class="flex-1 text-sm text-gray-800 tabular-nums">{{ $journal->sequence_prefix }}{{ now()->format('Y') }}/{{ str_pad($journal->sequence_next_number, $journal->sequence_padding, '0', STR_PAD_LEFT) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200">
                <div class="flex items-center justify-between px-6 py-3">
                    <h2 class="text-sm font-semibold text-gray-800">Recent Entries</h2>
                    <a href="{{ route('accounting.moves.index', ['journal_id' => $journal->id]) }}" class="text-xs text-purple-600 hover:text-purple-700">View all →</a>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-xs font-semibold text-gray-500 uppercase">
                            <th class="px-4 py-2 text-left">Number</th>
                            <th class="px-3 py-2 text-left">Date</th>
                            <th class="px-3 py-2 text-left">Partner</th>
                            <th class="px-3 py-2 text-right">Amount</th>
                            <th class="px-3 py-2 text-left">State</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentMoves as $move)
                        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.moves.show', $move) }}'">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $move->name ?: '(Draft)' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ optional($move->date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $move->partner?->name ?: '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ number_format((float) $move->amount_total, 2) }}</td>
                            <td class="px-3 py-2">
                                @php
                                    $color = match($move->state) {
                                        'posted'    => 'bg-green-100 text-green-700',
                                        'draft'     => 'bg-amber-100 text-amber-700',
                                        'cancelled' => 'bg-gray-200 text-gray-600',
                                        default     => 'bg-gray-100 text-gray-600',
                                    };
                                @endphp
                                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $color }}">{{ $move->state_label }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="px-4 py-12 text-center text-sm text-gray-400">No entries in this journal yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Accounting\AccountJournal"
                :model-id="$journal->id"
                :can-comment="auth()->user()->can('comment', $journal)"
            />
        </div>
    </div>
</div>
@endsection
