@extends('layouts.app')
@section('title', 'Bank Reconciliation')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Bank Reconciliation</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        <form method="GET" class="mb-5 flex flex-wrap items-end gap-3 bg-white rounded-xl border border-gray-200 shadow-sm p-4">
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Bank / Cash Journal</label>
                <x-relation-dropdown
                    table="account_journals"
                    field="name"
                    name="journal_id"
                    :compact="true"
                    :selected="$journalId"
                />
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">From</label>
                <input type="date" name="date_from" value="{{ $dateFrom }}"
                       class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">To</label>
                <input type="date" name="date_to" value="{{ $dateTo }}"
                       class="border border-gray-300 rounded px-3 py-1.5 text-sm focus:outline-none focus:ring-1 focus:ring-purple-400">
            </div>
            <button type="submit" class="px-4 py-1.5 bg-purple-600 text-white text-sm font-medium rounded hover:bg-purple-700">Apply</button>
        </form>

        @if(!$journalId)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center text-sm text-gray-400">
            Select a bank or cash journal to view transactions.
        </div>
        @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Entry</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Label</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Credit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lines as $line)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 tabular-nums text-gray-600">{{ $line->date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-2.5">
                            @php
                                $lineRoute = match($line->move?->move_type) {
                                    'out_invoice' => route('accounting.invoices.show', $line->move_id),
                                    'in_invoice'  => route('accounting.bills.show', $line->move_id),
                                    'out_refund'  => route('accounting.credit-notes.show', $line->move_id),
                                    'in_refund'   => route('accounting.refunds.show', $line->move_id),
                                    default       => route('accounting.moves.show', $line->move_id),
                                };
                            @endphp
                            <a href="{{ $lineRoute }}" class="text-purple-600 hover:underline">{{ $line->move?->name }}</a>
                        </td>
                        <td class="px-4 py-2.5 text-gray-700">{{ $line->name ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-green-700">{{ $line->debit > 0 ? number_format($line->debit, 2) : '' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-red-600">{{ $line->credit > 0 ? number_format($line->credit, 2) : '' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-sm text-gray-400 text-center">No transactions found for the selected journal and period.</td></tr>
                    @endforelse
                </tbody>
                @if($lines->count() > 0)
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-800">Total</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-green-700">{{ number_format($lines->sum('debit'), 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-red-600">{{ number_format($lines->sum('credit'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
        <div class="mt-4">{{ $lines->links() }}</div>
        @endif
    </div>
</div>
@endsection
