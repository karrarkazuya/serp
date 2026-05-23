@extends('layouts.app')
@section('title', 'Profit and Loss')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Profit and Loss</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])

        <div class="max-w-2xl space-y-4">
            {{-- Income --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-green-50 border-b border-green-100">
                    <h2 class="text-sm font-bold text-green-800">Income</h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($income as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-mono text-gray-600">{{ $row->account_code }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ $row->account_name }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-800">{{ number_format(abs($row->net), 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-green-50 border-t border-green-100">
                        <tr>
                            <td colspan="2" class="px-5 py-2.5 text-sm font-bold text-green-800">Total Income</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-bold text-green-800">{{ number_format($totalIncome, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Expenses --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-red-50 border-b border-red-100">
                    <h2 class="text-sm font-bold text-red-800">Expenses</h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($expense as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-mono text-gray-600">{{ $row->account_code }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ $row->account_name }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-800">{{ number_format(abs($row->net), 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-red-50 border-t border-red-100">
                        <tr>
                            <td colspan="2" class="px-5 py-2.5 text-sm font-bold text-red-800">Total Expenses</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-bold text-red-800">{{ number_format($totalExpense, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Net Profit --}}
            <div class="bg-white rounded-xl border-2 {{ $netProfit >= 0 ? 'border-green-300' : 'border-red-300' }} shadow-sm p-5">
                <div class="flex justify-between items-center">
                    <span class="text-base font-bold {{ $netProfit >= 0 ? 'text-green-800' : 'text-red-800' }}">
                        {{ $netProfit >= 0 ? 'Net Profit' : 'Net Loss' }}
                    </span>
                    <span class="text-xl font-bold tabular-nums {{ $netProfit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ number_format(abs($netProfit), 2) }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
