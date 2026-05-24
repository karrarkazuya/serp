@extends('layouts.app')
@section('title', ($move->name && $move->name !== '/') ? $move->name : __('accounting.move_type_entry'))

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
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <a href="{{ route('accounting.moves.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.journal_entries') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ ($move->name && $move->name !== '/') ? $move->name : __('accounting.status_draft') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @if($move->isDraft())
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.post', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">{{ __('accounting.btn_post') }}</button>
                    </form>
                    @endcan
                    @can('update', $move)
                    <a href="{{ route('accounting.moves.edit', $move) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_edit') }}</a>
                    @endcan
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.cancel', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-gray-700 border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_cancel') }}</button>
                    </form>
                    @endcan
                @elseif($move->isPosted())
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.reset-draft', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('accounting.btn_reset_draft') }}</button>
                    </form>
                    <form method="POST" action="{{ route('accounting.moves.reverse', $move) }}"
                          x-data="{ open: false }" class="relative">
                        @csrf
                        <button type="button" @click="open = !open"
                                class="px-3 py-1.5 text-sm text-purple-700 border border-purple-200 rounded hover:bg-purple-50">
                            {{ __('accounting.btn_reverse') }}
                        </button>
                        <div x-show="open" style="display:none"
                             class="absolute right-0 mt-1 z-20 bg-white border border-gray-200 rounded-lg shadow-lg p-3 flex items-end gap-2">
                            <div>
                                <label class="block text-[11px] font-semibold text-gray-500 uppercase mb-1">{{ __('accounting.field_date') }}</label>
                                <input type="date" name="reversal_date" value="{{ now()->toDateString() }}"
                                       class="text-sm border border-gray-200 rounded px-2 py-1">
                            </div>
                            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">{{ __('accounting.btn_confirm') }}</button>
                            <button type="button" @click="open = false" class="px-2 py-1 text-xs text-gray-500">{{ __('accounting.btn_cancel') }}</button>
                        </div>
                    </form>
                    @endcan
                @elseif($move->isCancelled())
                    @can('post', $move)
                    <form method="POST" action="{{ route('accounting.moves.reset-draft', $move) }}">
                        @csrf @method('PATCH')
                        <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('accounting.btn_reset_draft') }}</button>
                    </form>
                    @endcan
                @endif
                @can('delete', $move)
                <form method="POST" action="{{ route('accounting.moves.delete', $move) }}"
                      x-data="{ confirming: false }">
                    @csrf @method('DELETE')
                    <button type="button" x-show="!confirming" @click="confirming = true"
                            class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">
                        {{ __('accounting.btn_delete') }}
                    </button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-red-600">{{ __('accounting.confirm_delete_move') }}</span>
                        <button type="submit" class="px-2 py-1 text-xs font-medium text-white bg-red-600 rounded">{{ __('accounting.btn_yes') }}</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500">{{ __('accounting.btn_cancel') }}</button>
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
        @if(session('success'))
        <div class="mx-4 mt-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm" x-data="{ tab: 'lines' }">
            <div class="p-6">
                {{-- Header: title + state stepper --}}
                <div class="mb-8 flex items-start justify-between gap-6">
                    <div>
                        <p class="text-lg font-semibold text-gray-800">{{ __('accounting.move_type_entry') }}</p>
                        <h1 class="mt-2 text-4xl font-bold {{ ($move->name && $move->name !== '/') ? 'text-gray-900' : 'text-gray-400' }}">
                            {{ ($move->name && $move->name !== '/') ? $move->name : __('accounting.status_draft') }}
                        </h1>
                    </div>
                    <div class="flex shrink-0 items-center text-sm font-semibold">
                        <span class="relative px-8 py-2 border {{ $move->isDraft() ? 'border-[#71639e] bg-purple-50 text-gray-900' : 'border-gray-200 bg-gray-100 text-gray-400' }}">{{ __('accounting.status_draft') }}</span>
                        <span class="px-8 py-2 border {{ $move->isPosted() ? 'border-green-500 bg-green-50 text-green-700' : 'border-gray-200 bg-gray-100 text-gray-400' }}">{{ __('accounting.status_posted') }}</span>
                        @if($move->isCancelled())
                        <span class="px-8 py-2 border border-gray-400 bg-gray-200 text-gray-600">{{ __('accounting.status_cancelled') }}</span>
                        @endif
                    </div>
                </div>

                {{-- Fields --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-0 mb-8">
                    <div>
                        <div class="flex items-center gap-5 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm font-semibold text-gray-500">{{ __('accounting.col_reference') }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $move->ref ?: '—' }}</span>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-5 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm font-semibold text-gray-500">{{ __('accounting.field_accounting_date') }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ optional($move->date)->format('Y-m-d') }}</span>
                        </div>
                        <div class="flex items-center gap-5 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm font-semibold text-gray-500">{{ __('accounting.col_journal') }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $move->journal?->name ?: '—' }}</span>
                        </div>
                    </div>
                </div>

                @if($move->reversedMove)
                <div class="mb-6 px-3 py-2 bg-purple-50 border border-purple-200 rounded text-sm text-purple-700">
                    Reverses <a href="{{ route('accounting.moves.show', $move->reversedMove) }}" class="font-semibold underline">{{ $move->reversedMove->display_name }}</a>
                </div>
                @endif
                @if($move->reversal->isNotEmpty())
                <div class="mb-6 px-3 py-2 bg-amber-50 border border-amber-200 rounded text-sm text-amber-700">
                    Reversed by
                    @foreach($move->reversal as $rev)
                    <a href="{{ route('accounting.moves.show', $rev) }}" class="font-semibold underline">{{ $rev->display_name }}</a>{{ !$loop->last ? ', ' : '' }}
                    @endforeach
                </div>
                @endif
            </div>

            {{-- Tabs --}}
            <div class="rounded-b-xl overflow-hidden">
                <div class="border-t border-b border-gray-200 flex bg-white px-6">
                    <button type="button" @click="tab = 'lines'"
                            class="px-5 py-3 text-sm font-semibold border-b-2 -mb-px bg-white transition-colors"
                            :class="tab === 'lines' ? 'border-[#71639e] text-[#71639e]' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        {{ __('accounting.journal_items') }}
                    </button>
                    <button type="button" @click="tab = 'other'"
                            class="px-5 py-3 text-sm font-semibold border-b-2 -mb-px bg-white transition-colors"
                            :class="tab === 'other' ? 'border-[#71639e] text-[#71639e]' : 'border-transparent text-gray-500 hover:text-gray-700'">
                        Other Info
                    </button>
                </div>

                {{-- Journal Items --}}
                <div x-show="tab === 'lines'" class="border-t border-gray-200">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100 border-b border-gray-200">
                            <tr class="text-xs font-semibold text-gray-500 uppercase tracking-wide">
                                <th class="px-4 py-3 text-left w-72">{{ __('accounting.col_account') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('accounting.col_partner') }}</th>
                                <th class="px-4 py-3 text-left">{{ __('accounting.col_label') }}</th>
                                <th class="px-4 py-3 text-right w-36">{{ __('accounting.col_debit') }}</th>
                                <th class="px-4 py-3 text-right w-36">{{ __('accounting.col_credit') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($move->lines as $line)
                            <tr>
                                <td class="px-4 py-2 text-gray-800">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $line->partner?->name ?: '—' }}</td>
                                <td class="px-4 py-2 text-gray-700">{{ $line->name }}</td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-800">
                                    <x-money :amount="(float) $line->debit" :currency="$move->currency" :blank="true" />
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums text-gray-800">
                                    <x-money :amount="(float) $line->credit" :currency="$move->currency" :blank="true" />
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="border-t border-gray-200">
                            <tr class="bg-gray-100 text-sm font-semibold">
                                <td colspan="3" class="px-4 py-2.5 text-right text-gray-700">{{ __('accounting.total') }}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums"><x-money :amount="(float) $balance['debit']" :currency="$move->currency" /></td>
                                <td class="px-4 py-2.5 text-right tabular-nums"><x-money :amount="(float) $balance['credit']" :currency="$move->currency" /></td>
                            </tr>
                            @if(abs($balance['difference']) > 0.005)
                            <tr class="bg-amber-50 text-amber-700 text-sm font-medium border-t border-amber-100">
                                <td colspan="3" class="px-4 py-2 text-right">{{ __('accounting.difference') }}</td>
                                <td colspan="2" class="px-4 py-2 text-right tabular-nums"><x-money :amount="(float) $balance['difference']" :currency="$move->currency" /></td>
                            </tr>
                            @endif
                        </tfoot>
                    </table>
                </div>

                {{-- Other Info --}}
                <div x-show="tab === 'other'" style="display:none" class="border-t border-gray-200 px-8 py-6">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-x-12 gap-y-0">
                        <div>
                            <div class="flex items-center gap-5 py-2 border-b border-gray-100">
                                <span class="w-32 shrink-0 text-sm font-semibold text-gray-500">{{ __('accounting.field_company') }}</span>
                                <span class="flex-1 text-sm text-gray-800">{{ $move->company?->name ?: '—' }}</span>
                            </div>
                            <div class="flex items-center gap-5 py-2 border-b border-gray-100">
                                <span class="w-32 shrink-0 text-sm font-semibold text-gray-500">{{ __('accounting.field_currency') }}</span>
                                <span class="flex-1 text-sm text-gray-800">{{ $move->currency ?: '—' }}</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-5 py-2 border-b border-gray-100">
                                <span class="w-32 shrink-0 text-sm font-semibold text-gray-500">{{ __('accounting.field_partner') }}</span>
                                <span class="flex-1 text-sm text-gray-800">{{ $move->partner?->name ?: '—' }}</span>
                            </div>
                        </div>
                    </div>
                    @if($move->narration)
                    <div class="mt-6">
                        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">{{ __('accounting.field_notes') }}</p>
                        <p class="text-sm text-gray-700 whitespace-pre-line">{{ $move->narration }}</p>
                    </div>
                    @endif
                    <div class="mt-6 pt-4 border-t border-gray-100 text-xs text-gray-400 flex flex-wrap gap-x-6 gap-y-1">
                        <span>Created: {{ $move->created_at?->format('M d, Y') }}{{ $move->creator ? ' · ' . $move->creator->name : '' }}</span>
                        @if($move->posted_at)
                        <span>Posted: {{ $move->posted_at->format('M d, Y H:i') }}{{ $move->poster ? ' · ' . $move->poster->name : '' }}</span>
                        @endif
                        <span>Updated: {{ $move->updated_at?->diffForHumans() }}{{ $move->updater ? ' · ' . $move->updater->name : '' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Accounting\AccountMove"
                :model-id="$move->id"
                :can-comment="auth()->user()->can('comment', $move)"
            />
        </div>
    </div>
</div>
@endsection
