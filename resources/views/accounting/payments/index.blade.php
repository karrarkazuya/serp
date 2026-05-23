@extends('layouts.app')
@section('title', 'Payments')

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <div class="flex flex-col leading-tight shrink-0">
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Payments</span>
        </div>

        <x-search
            :model="\App\Models\Accounting\AccountPayment::class"
            :action="route('accounting.payments.index')"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            <span class="text-sm font-semibold text-gray-600">
                {{ $payments->total() > 0 ? $payments->firstItem().'-'.$payments->lastItem() : 0 }} / {{ $payments->total() }}
            </span>
        </div>
    </div>

    <x-list :paginator="$payments" empty-text="No payments yet.">
        <x-slot:columns>
            <x-sortable-th column="date" label="Date" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Type</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Partner</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Journal</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Document</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Memo</th>
            <x-sortable-th column="amount" label="Amount" class="px-3 py-2 text-right" />
        </x-slot:columns>

        @foreach($payments as $payment)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.payments.show', $payment) }}'">
            <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($payment->date)->format('Y-m-d') }}</td>
            <td class="px-3 py-2">
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $payment->payment_type === 'inbound' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                    {{ ucfirst($payment->payment_type) }}
                </span>
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $payment->partner?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $payment->journal?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $payment->pairedDocument?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $payment->memo ?: '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
