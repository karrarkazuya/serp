@extends('layouts.app')
@section('title', __('accounting.report_tax'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_tax') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showTaxUse' => true,
            'reportKey' => 'tax-report',
        ])

        @php $byUse = $rows->groupBy('tax_use'); @endphp

        <div class="max-w-4xl space-y-3">
            @forelse($byUse as $use => $group)
            @php
                $useLabel = match($use) {
                    'sale'     => 'Output VAT (Sales)',
                    'purchase' => 'Input VAT (Purchases)',
                    default    => 'Other',
                };
                $useColor = $use === 'sale' ? 'green' : ($use === 'purchase' ? 'blue' : 'gray');
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-2.5 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                    <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">{{ $useLabel }}</h2>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-{{ $useColor }}-50 text-{{ $useColor }}-700 border border-{{ $useColor }}-200">{{ $group->count() }} {{ Str::plural('tax', $group->count()) }}</span>
                </div>
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50/60">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.taxes') }}</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.base_amount') }}</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.tax_amount_col') }} (Dr)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.tax_amount_col') }} (Cr)</th>
                            <th class="px-4 py-2 text-right text-xs font-semibold text-gray-600 uppercase">{{ __('accounting.net') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($group as $row)
                        <tr class="hover:bg-purple-50/30">
                            <td class="px-4 py-2 text-gray-700">
                                {{ $row->tax_name }}
                                <span class="ms-1 text-xs text-gray-400">({{ rtrim(rtrim(number_format((float) $row->tax_rate, 2, '.', ''), '0'), '.') }}%)</span>
                            </td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-600"><x-money :amount="(float) $row->total_base" /></td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $row->total_debit" /></td>
                            <td class="px-4 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $row->total_credit" /></td>
                            <td class="px-4 py-2 text-right tabular-nums font-medium {{ (float) $row->net >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                                <x-money :amount="(float) $row->net" />
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td class="px-4 py-2.5 text-sm font-bold text-gray-800">{{ __('accounting.total') }}</td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-bold text-gray-800"><x-money :amount="(float) $group->sum('total_base')" /></td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-bold text-gray-800"><x-money :amount="(float) $group->sum('total_debit')" /></td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-bold text-gray-800"><x-money :amount="(float) $group->sum('total_credit')" /></td>
                            <td class="px-4 py-2.5 text-right tabular-nums font-bold text-gray-800"><x-money :amount="(float) $group->sum('net')" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @empty
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center text-sm text-gray-400">{{ __('accounting.no_data_period') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection
