@extends('layouts.app')
@section('title', __('accounting.report_bank_recon'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_bank_recon') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showJournal' => true,
            'reportKey' => 'bank-reconciliation',
        ])

        @if(empty($filters['journal_id']))
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 10h18M3 6h18M3 14h18M3 18h18"/></svg>
            <p class="text-sm text-gray-500 mb-3">{{ __('accounting.select_bank_journal') }}</p>
            @if($bankJournals->isNotEmpty())
            <div class="flex flex-wrap gap-2 justify-center mt-3 max-w-2xl mx-auto">
                @foreach($bankJournals as $j)
                <a href="{{ route('accounting.reports.bank-reconciliation', ['journal_id' => $j->id]) }}"
                   class="px-3 py-1.5 text-xs font-medium rounded-full bg-purple-50 text-[#71639e] border border-purple-200 hover:bg-purple-100">
                    {{ $j->name }}
                </a>
                @endforeach
            </div>
            @endif
        </div>
        @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_date') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_entry') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_partner') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_label') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_debit') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_credit') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($lines as $line)
                    <tr class="hover:bg-purple-50/30">
                        <td class="px-4 py-2 tabular-nums text-gray-600 whitespace-nowrap">{{ $line->date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            @php
                                $lineRoute = match($line->move?->move_type) {
                                    'out_invoice' => route('accounting.invoices.show', $line->move_id),
                                    'in_invoice'  => route('accounting.bills.show', $line->move_id),
                                    'out_refund'  => route('accounting.credit-notes.show', $line->move_id),
                                    'in_refund'   => route('accounting.refunds.show', $line->move_id),
                                    default       => route('accounting.moves.show', $line->move_id),
                                };
                            @endphp
                            <a href="{{ $lineRoute }}" class="text-purple-600 hover:underline font-medium">{{ $line->move?->display_name }}</a>
                        </td>
                        <td class="px-4 py-2 text-gray-600">{{ $line->partner?->name ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-700">{{ $line->name ?: '—' }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-green-700"><x-money :amount="(float) $line->debit" :blank="true" /></td>
                        <td class="px-4 py-2 text-right tabular-nums text-red-600"><x-money :amount="(float) $line->credit" :blank="true" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-sm text-gray-400 text-center">{{ __('accounting.no_journal_items_filter') }}</td></tr>
                    @endforelse
                </tbody>
                @if($lines->count() > 0)
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td colspan="4" class="px-4 py-3 text-sm font-bold text-gray-800">{{ __('accounting.total') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-green-700"><x-money :amount="(float) $lines->sum('debit')" /></td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-red-600"><x-money :amount="(float) $lines->sum('credit')" /></td>
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
