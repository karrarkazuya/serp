@extends('layouts.app')
@section('title', 'Payment')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <a href="{{ route('accounting.payments.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Payments</a>
            <span class="text-sm font-semibold text-gray-800">{{ $payment->memo ?: 'Payment' }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <a href="{{ route('accounting.moves.show', $payment->move) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Journal Entry</a>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="p-6">
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-600">{{ ucfirst($payment->payment_type) }} Payment</span>
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium bg-green-100 text-green-700">Posted</span>
                </div>
                <h1 class="mt-2 text-4xl font-bold text-gray-900">{{ number_format((float) $payment->amount, 2) }} {{ $payment->currency }}</h1>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 mt-6">
                    <div>
                        @foreach([
                            ['Partner', $payment->partner?->name],
                            ['Document', $payment->pairedDocument?->name],
                            ['Memo', $payment->memo],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                    <div>
                        @foreach([
                            ['Date', optional($payment->date)->format('Y-m-d')],
                            ['Journal', $payment->journal?->name],
                            ['Company', $payment->company?->name],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-32 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '—' }}</span>
                        </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-8 border border-gray-200 rounded-lg overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr class="text-xs font-semibold text-gray-500 uppercase">
                                <th class="px-3 py-2 text-left">Account</th>
                                <th class="px-3 py-2 text-left">Label</th>
                                <th class="px-3 py-2 text-right">Debit</th>
                                <th class="px-3 py-2 text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach($payment->move->lines as $line)
                            <tr>
                                <td class="px-3 py-1.5 text-gray-800">{{ $line->account?->display_name }}</td>
                                <td class="px-3 py-1.5 text-gray-700">{{ $line->name }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ (float) $line->debit ? number_format((float) $line->debit, 2) : '' }}</td>
                                <td class="px-3 py-1.5 text-right tabular-nums">{{ (float) $line->credit ? number_format((float) $line->credit, 2) : '' }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
