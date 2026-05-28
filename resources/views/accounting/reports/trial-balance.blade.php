@extends('layouts.app')
@section('title', __('accounting.report_trial_balance'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_trial_balance') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showJournal' => true, 'showAccountType' => true,
            'reportKey' => 'trial-balance',
        ])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50 sticky top-0 z-10">
                    <tr>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_code') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_account') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_type') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_debit') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_credit') }}</th>
                        <th class="px-4 py-2.5 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.col_balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-purple-50/30">
                        <td class="px-4 py-2 font-mono text-xs">
                            <a href="{{ route('accounting.accounts.show', $row->account_id) }}" class="text-purple-600 hover:underline">{{ $row->account_code }}</a>
                        </td>
                        <td class="px-4 py-2 text-gray-700">{{ $row->account_name }}</td>
                        <td class="px-4 py-2 text-xs text-gray-500">{{ ucwords(str_replace('_', ' ', $row->account_type)) }}</td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $row->total_debit" /></td>
                        <td class="px-4 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $row->total_credit" /></td>
                        <td class="px-4 py-2 text-right tabular-nums font-medium {{ (float) $row->net_balance >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                            <x-money :amount="(float) abs($row->net_balance)" /> {{ (float) $row->net_balance < 0 ? __('accounting.cr_suffix') : '' }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="px-4 py-12 text-sm text-gray-400 text-center">{{ __('accounting.no_data_period') }}</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-sm font-bold text-gray-800">{{ __('accounting.total') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800"><x-money :amount="(float) $totalDebit" /></td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800"><x-money :amount="(float) $totalCredit" /></td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold {{ abs($totalDebit - $totalCredit) < 0.01 ? 'text-green-600' : 'text-red-600' }}">
                            <x-money :amount="(float) abs($totalDebit - $totalCredit) " />
                            @if(abs($totalDebit - $totalCredit) < 0.01) ✓ @endif
                        </td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
