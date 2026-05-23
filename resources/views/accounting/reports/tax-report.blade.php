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
        @include('accounting.reports._filters', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden max-w-3xl">
            <table class="min-w-full text-sm divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.taxes') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.base_amount') }}</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.tax_amount_col') }} (Dr)</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.tax_amount_col') }} (Cr)</th>
                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.net') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($rows as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2.5 text-gray-700">{{ $row->taxLine?->name ?? '—' }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-600">{{ number_format($row->total_base, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($row->total_debit, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums text-gray-800">{{ number_format($row->total_credit, 2) }}</td>
                        <td class="px-4 py-2.5 text-right tabular-nums font-medium {{ $row->net >= 0 ? 'text-gray-800' : 'text-red-600' }}">
                            {{ number_format($row->net, 2) }}
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="px-4 py-8 text-sm text-gray-400 text-center">{{ __('accounting.no_data_period') }}</td></tr>
                    @endforelse
                </tbody>
                @if($rows->isNotEmpty())
                <tfoot class="bg-gray-50 border-t-2 border-gray-300">
                    <tr>
                        <td class="px-4 py-3 text-sm font-bold text-gray-800">{{ __('accounting.total') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800">{{ number_format($rows->sum('total_base'), 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800">{{ number_format($rows->sum('total_debit'), 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800">{{ number_format($rows->sum('total_credit'), 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums font-bold text-gray-800">{{ number_format($rows->sum('net'), 2) }}</td>
                    </tr>
                </tfoot>
                @endif
            </table>
        </div>
    </div>
</div>
@endsection
