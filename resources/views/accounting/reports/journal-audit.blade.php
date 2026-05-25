@extends('layouts.app')
@section('title', __('accounting.report_journal_audit'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_journal_audit') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showJournal' => true, 'showPartner' => true, 'showState' => true, 'showMoveType' => true,
            'reportKey' => 'journal-audit',
        ])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_date') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_reference') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_journal') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_partner') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_state') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_amount') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($moves as $move)
                    @php
                        $stateClass = match($move->state) {
                            'posted'    => 'bg-green-50 text-green-700 border-green-200',
                            'draft'     => 'bg-amber-50 text-amber-700 border-amber-200',
                            'cancelled' => 'bg-gray-100 text-gray-500 border-gray-200',
                            default     => 'bg-gray-50 text-gray-600 border-gray-200',
                        };
                        $auditRoute = match($move->move_type) {
                            'out_invoice' => route('accounting.invoices.show', $move),
                            'in_invoice'  => route('accounting.bills.show', $move),
                            'out_refund'  => route('accounting.credit-notes.show', $move),
                            'in_refund'   => route('accounting.refunds.show', $move),
                            default       => route('accounting.moves.show', $move),
                        };
                    @endphp
                    <tr class="hover:bg-purple-50/30">
                        <td class="px-4 py-2 tabular-nums text-gray-600 whitespace-nowrap">{{ $move->date?->format('Y-m-d') }}</td>
                        <td class="px-4 py-2">
                            <a href="{{ $auditRoute }}" class="text-purple-600 hover:underline font-medium">{{ $move->display_name }}</a>
                            @if($move->ref) <span class="text-xs text-gray-400 ms-1">{{ $move->ref }}</span> @endif
                        </td>
                        <td class="px-4 py-2 text-gray-600">{{ $move->journal?->code }} – {{ $move->journal?->name }}</td>
                        <td class="px-4 py-2 text-gray-600">{{ $move->partner?->name ?? '—' }}</td>
                        <td class="px-4 py-2">
                            <span class="inline-block px-2 py-0.5 text-[10px] font-semibold rounded border uppercase tracking-wide {{ $stateClass }}">{{ $move->state }}</span>
                        </td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $move->amount_total" :currency="$move->currency" /></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-sm text-gray-400 text-center">{{ __('accounting.no_journal_items_filter') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $moves->links() }}</div>
    </div>
</div>
@endsection
