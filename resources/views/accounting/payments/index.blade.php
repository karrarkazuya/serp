@extends('layouts.app')
@section('title', __('accounting.payments'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="route('accounting.payments.create')">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.payments') }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search
                :model="\App\Models\Accounting\AccountPayment::class"
                :action="route('accounting.payments.index')"
            />
        </x-slot:search>
    </x-toolbar>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('accounting.no_payments')">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('accounting.col_date')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_type') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_partner') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_journal') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.field_document') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.field_memo') }}</th>
            <x-sortable-th column="amount" :label="__('accounting.col_amount')" class="px-3 py-2 text-right" />
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $payment)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.payments.show', $payment) }}'">
                <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($payment->date)->format('Y-m-d') }}</td>
                <td class="px-3 py-2">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $payment->payment_type === 'inbound' ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
                        {{ ucfirst($payment->payment_type) }}
                    </span>
                </td>
                <td class="px-3 py-2 text-gray-600">{{ $payment->partner?->name ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $payment->journal?->name ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $payment->pairedDocument?->display_name ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $payment->memo ?: '—' }}</td>
                <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900"><x-money :amount="(float) $payment->amount" :currency="$payment->currency" /></td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_payments') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$payments" :empty-text="__('accounting.no_payments')">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('accounting.col_date')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_type') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_partner') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_journal') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.field_document') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.field_memo') }}</th>
            <x-sortable-th column="amount" :label="__('accounting.col_amount')" class="px-3 py-2 text-right" />
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
            <td class="px-3 py-2 text-gray-600">{{ $payment->pairedDocument?->display_name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $payment->memo ?: '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums font-medium text-gray-900"><x-money :amount="(float) $payment->amount" :currency="$payment->currency" /></td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
