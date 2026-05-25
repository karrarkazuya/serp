@extends('layouts.app')
@section('title', __('accounting.report_cash_flow'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_cash_flow') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showJournal' => true,
            'reportKey' => 'cash-flow',
        ])

        <div class="max-w-3xl">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_account') }}</th>
                            <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.cash_inflow') }}</th>
                            <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.cash_outflow') }}</th>
                            <th class="px-5 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.net') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($rows as $row)
                        <tr class="hover:bg-purple-50/30">
                            <td class="px-5 py-2">
                                <span class="font-mono text-xs text-gray-500">{{ $row->account_code }}</span>
                                <span class="ms-1 text-gray-700">{{ $row->account_name }}</span>
                            </td>
                            <td class="px-5 py-2 text-right tabular-nums text-green-700"><x-money :amount="(float) $row->total_debit" :blank="true" /></td>
                            <td class="px-5 py-2 text-right tabular-nums text-red-600"><x-money :amount="(float) $row->total_credit" :blank="true" /></td>
                            <td class="px-5 py-2 text-right tabular-nums font-medium {{ (float) $row->net >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                                <x-money :amount="(float) $row->net" />
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" class="px-5 py-12 text-sm text-gray-400 text-center">{{ __('accounting.no_data_period') }}</td></tr>
                        @endforelse
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                        <tr>
                            <td class="px-5 py-3 text-sm font-bold text-gray-800">{{ __('accounting.net_cash_flow') }}</td>
                            <td class="px-5 py-3 text-right tabular-nums font-bold text-green-700"><x-money :amount="(float) $total_inflow" /></td>
                            <td class="px-5 py-3 text-right tabular-nums font-bold text-red-600"><x-money :amount="(float) $total_outflow" /></td>
                            <td class="px-5 py-3 text-right tabular-nums font-bold text-lg {{ $net_cash_flow >= 0 ? 'text-green-700' : 'text-red-600' }}">
                                <x-money :amount="(float) $net_cash_flow" />
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
