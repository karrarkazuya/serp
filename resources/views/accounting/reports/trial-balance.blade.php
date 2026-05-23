@extends('layouts.app')
@section('title', 'Trial Balance')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Trial Balance</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Account</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Debit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Credit</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Balance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('accounting.accounts.show', $row->account_id) }}" class="font-mono text-purple-600 hover:underline">
                                {{ $row->account?->code }}
                            </a>
                            <span class="ml-2 text-gray-700">{{ $row->account?->name }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($row->total_debit, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($row->total_credit, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium {{ $row->balance >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                            {{ number_format(abs($row->balance), 2) }} {{ $row->balance < 0 ? 'Cr' : '' }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-sm text-gray-400 text-center">No data for the selected period.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-gray-800">Total</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800">{{ number_format($totalDebit, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800">{{ number_format($totalCredit, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold {{ abs($totalDebit - $totalCredit) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                            {{ number_format(abs($totalDebit - $totalCredit), 2) }}
                            @if(abs($totalDebit - $totalCredit) < 0.01) ✓ @endif
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
@endsection
