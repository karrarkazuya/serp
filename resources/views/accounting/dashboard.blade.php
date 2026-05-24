@extends('layouts.app')
@section('title', __('accounting.accounting'))

@section('content')
<div class="flex min-w-0 flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.accounting') }}</span>
            <span class="text-[11px] text-gray-400 uppercase tracking-wide">{{ __('accounting.unified_system') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('create', \App\Models\Accounting\AccountMove::class)
                <a href="{{ route('accounting.invoices.create') }}" class="px-3 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('accounting.btn_new_invoice') }}</a>
                <a href="{{ route('accounting.bills.create') }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('accounting.btn_new_bill') }}</a>
                <a href="{{ route('accounting.moves.create') }}" class="px-3 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('accounting.btn_new_entry') }}</a>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-7 gap-4 mb-6">
            <a href="{{ route('accounting.invoices.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-purple-700">{{ __('accounting.invoices') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $invoiceCount }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.customer_documents') }}</p>
            </a>
            <a href="{{ route('accounting.bills.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-indigo-700">{{ __('accounting.bills') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $billCount }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.vendor_documents') }}</p>
            </a>
            <a href="{{ route('accounting.accounts.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-gray-500">{{ __('accounting.chart_of_accounts') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $accountsCount }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.active_accounts') }}</p>
            </a>
            <a href="{{ route('accounting.journals.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-gray-500">{{ __('accounting.journals') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $journals->count() }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.journals_summary') }}</p>
            </a>
            <a href="{{ route('accounting.moves.index', ['state' => 'draft']) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-amber-600">{{ __('accounting.draft_entries') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $draftCount }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.pending_posting') }}</p>
            </a>
            <a href="{{ route('accounting.moves.index', ['state' => 'posted']) }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-green-700">{{ __('accounting.posted_entries') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $postedCount }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.locked_in_ledger') }}</p>
            </a>
            <a href="{{ route('accounting.items.index') }}" class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 hover:shadow-md transition">
                <p class="text-xs font-semibold uppercase text-gray-500">{{ __('accounting.journal_items') }}</p>
                <p class="mt-2 text-3xl font-bold text-gray-900">{{ $journalItemsCount }}</p>
                <p class="mt-1 text-xs text-gray-400">{{ __('accounting.debit_credit_lines') }}</p>
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">{{ __('accounting.recent_entries') }}</h2>
                    <a href="{{ route('accounting.moves.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.view_all') }}</a>
                </div>
                <table class="w-full text-sm">
                    <thead class="bg-gray-50">
                        <tr class="text-xs font-semibold text-gray-500 uppercase">
                            <th class="px-4 py-2 text-left">{{ __('accounting.col_number') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('accounting.col_journal') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('accounting.col_partner') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('accounting.col_date') }}</th>
                            <th class="px-3 py-2 text-right">{{ __('accounting.col_amount') }}</th>
                            <th class="px-3 py-2 text-left">{{ __('accounting.col_state') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($recentMoves as $move)
                        @php
                            $dashMoveUrl = match($move->move_type) {
                                'out_invoice' => route('accounting.invoices.show', $move),
                                'in_invoice'  => route('accounting.bills.show', $move),
                                'out_refund'  => route('accounting.credit-notes.show', $move),
                                'in_refund'   => route('accounting.refunds.show', $move),
                                default       => route('accounting.moves.show', $move),
                            };
                        @endphp
                        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ $dashMoveUrl }}'">
                            <td class="px-4 py-2 font-medium text-gray-900">{{ $move->display_name }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $move->journal->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $move->partner->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ optional($move->date)->format('Y-m-d') }}</td>
                            <td class="px-3 py-2 text-right text-gray-800 tabular-nums"><x-money :amount="(float) $move->amount_total" :currency="$move->currency" /></td>
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
                        <tr><td colspan="6" class="px-4 py-16 text-center text-sm text-gray-400">{{ __('accounting.no_entries_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-gray-800">{{ __('accounting.journals') }}</h2>
                    <a href="{{ route('accounting.journals.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.manage') }}</a>
                </div>
                <div class="divide-y divide-gray-100">
                    @forelse($journals as $journal)
                    <a href="{{ route('accounting.journals.show', $journal) }}" class="flex items-center justify-between px-4 py-3 hover:bg-gray-50">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-gray-900 truncate">{{ $journal->name }}</p>
                            <p class="text-xs text-gray-500">{{ $journal->code }} · {{ $journal->type_label }}</p>
                        </div>
                        <span class="text-xs text-gray-400 tabular-nums shrink-0">#{{ $journal->sequence_next_number }}</span>
                    </a>
                    @empty
                    <p class="px-4 py-12 text-center text-sm text-gray-400">{{ __('accounting.no_journals_configured') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
