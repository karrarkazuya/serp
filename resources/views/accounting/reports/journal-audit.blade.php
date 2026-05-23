@extends('layouts.app')
@section('title', 'Journal Audit')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Journal Audit</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'dateFrom'    => $dateFrom,
            'dateTo'      => $dateTo,
            'showJournal' => true,
            'journalId'   => $journalId,
        ])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Reference</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Journal</th>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Partner</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($moves as $move)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 tabular-nums text-gray-600">{{ $move->date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-2.5">
                            @php
                                $auditRoute = match($move->move_type) {
                                    'out_invoice' => route('accounting.invoices.show', $move),
                                    'in_invoice'  => route('accounting.bills.show', $move),
                                    'out_refund'  => route('accounting.credit-notes.show', $move),
                                    'in_refund'   => route('accounting.refunds.show', $move),
                                    default       => route('accounting.moves.show', $move),
                                };
                            @endphp
                            <a href="{{ $auditRoute }}" class="text-purple-600 hover:underline font-medium">{{ $move->name }}</a>
                            @if($move->ref) <span class="text-xs text-gray-400 ml-1">{{ $move->ref }}</span> @endif
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $move->journal?->code }} – {{ $move->journal?->name }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $move->partner?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($move->amount_total, 2) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-sm text-gray-400 text-center">No journal entries for the selected filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $moves->links() }}</div>
    </div>
</div>
@endsection
