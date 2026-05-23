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
        @include('accounting.reports._filters', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_account') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_debit') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_credit') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_balance') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5">
                            <a href="{{ route('accounting.accounts.show', $row->account_id) }}" class="font-mono text-purple-600 hover:underline">
                                {{ $row->account?->code }}
                            </a>
                            <span class="ms-2 text-gray-700">{{ $row->account?->name }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($row->total_debit, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($row->total_credit, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium {{ $row->balance >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                            {{ number_format(abs($row->balance), 2) }} {{ $row->balance < 0 ? __('accounting.cr_suffix') : '' }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="4" class="px-4 py-8 text-sm text-gray-400 text-center">{{ __('accounting.no_data_period') }}</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-gray-800">{{ __('accounting.total') }}</td>
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
