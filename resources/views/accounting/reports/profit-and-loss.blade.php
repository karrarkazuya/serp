@extends('layouts.app')
@section('title', __('accounting.report_profit_loss'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_profit_loss') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showJournal' => true,
            'reportKey' => 'profit-and-loss',
        ])

        <div class="max-w-3xl space-y-3">
            {{-- Income --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-2.5 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">{{ __('accounting.income') }}</h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($income as $row)
                        <tr class="hover:bg-purple-50/30">
                            <td class="ps-8 pe-2 py-2 font-mono text-xs text-gray-500 w-20">{{ $row->account_code }}</td>
                            <td class="px-2 py-2 text-gray-700">{{ $row->account_name }}</td>
                            <td class="px-5 py-2 text-right tabular-nums text-gray-800 w-40"><x-money :amount="(float) abs($row->net)" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-5 py-8 text-sm text-gray-400 text-center">{{ __('accounting.no_data_period') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="2" class="px-5 py-2.5 text-sm font-bold text-gray-800">{{ __('accounting.total') }} {{ __('accounting.income') }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-bold text-gray-900"><x-money :amount="(float) $total_income" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Expenses --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-2.5 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">{{ __('accounting.expense') }}</h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($expense as $row)
                        <tr class="hover:bg-purple-50/30">
                            <td class="ps-8 pe-2 py-2 font-mono text-xs text-gray-500 w-20">{{ $row->account_code }}</td>
                            <td class="px-2 py-2 text-gray-700">{{ $row->account_name }}</td>
                            <td class="px-5 py-2 text-right tabular-nums text-gray-800 w-40"><x-money :amount="(float) abs($row->net)" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-5 py-8 text-sm text-gray-400 text-center">{{ __('accounting.no_data_period') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="2" class="px-5 py-2.5 text-sm font-bold text-gray-800">{{ __('accounting.total') }} {{ __('accounting.expense') }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-bold text-gray-900"><x-money :amount="(float) $total_expense" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            {{-- Net Profit / Loss --}}
            <div class="bg-white rounded-xl border-2 {{ $net_profit >= 0 ? 'border-green-300' : 'border-red-300' }} shadow-sm p-5">
                <div class="flex justify-between items-center">
                    <span class="text-base font-bold {{ $net_profit >= 0 ? 'text-green-800' : 'text-red-800' }}">
                        {{ $net_profit >= 0 ? __('accounting.net_profit') : __('accounting.net_loss') }}
                    </span>
                    <span class="text-2xl font-bold tabular-nums {{ $net_profit >= 0 ? 'text-green-700' : 'text-red-700' }}">
                        <x-money :amount="(float) abs($net_profit)" />
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
