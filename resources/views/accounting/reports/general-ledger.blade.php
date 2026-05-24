@extends('layouts.app')
@section('title', __('accounting.report_general_ledger'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_general_ledger') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'showJournal' => true,
            'journalId'   => $journalId,
            'showAccount' => true,
            'accountId'   => $accountId,
        ])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_date') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.journal_entries') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_account') }}</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_label') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_debit') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_credit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lines as $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 tabular-nums text-gray-600">{{ $line->date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-2.5 text-purple-600">
                            @php
                                $moveRoute = match($line->move?->move_type) {
                                    'out_invoice' => route('accounting.invoices.show', $line->move_id),
                                    'in_invoice'  => route('accounting.bills.show', $line->move_id),
                                    'out_refund'  => route('accounting.credit-notes.show', $line->move_id),
                                    'in_refund'   => route('accounting.refunds.show', $line->move_id),
                                    default       => route('accounting.moves.show', $line->move_id),
                                };
                            @endphp
                            <a href="{{ $moveRoute }}" class="hover:underline">
                                {{ $line->move?->display_name ?? $line->move_id }}
                            </a>
                        </td>
                        <td class="px-4 py-2.5 font-mono text-gray-700">{{ $line->account?->code }} {{ $line->account?->name }}</td>
                        <td class="px-4 py-2.5 text-gray-700">{{ $line->name ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800"><x-money :amount="(float) $line->debit" :blank="true" /></td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800"><x-money :amount="(float) $line->credit" :blank="true" /></td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-sm text-gray-400 text-center">{{ __('accounting.no_journal_items_filter') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $lines->links() }}</div>
    </div>
</div>
@endsection
