@extends('layouts.app')
@section('title', __('accounting.report_aged_receivable'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_aged_receivable') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showAsOf' => true, 'showPartner' => true,
            'reportKey' => 'aged-receivable',
        ])

        @php
        $buckets = ['Current', '1–30', '31–60', '61–90', '90+'];
        $grouped = $rows->groupBy('bucket');
        $bucketColors = ['Current' => 'gray', '1–30' => 'yellow', '31–60' => 'orange', '61–90' => 'red', '90+' => 'red'];
        @endphp

        {{-- Summary cards --}}
        @if($rows->isNotEmpty())
        <div class="grid grid-cols-2 md:grid-cols-5 gap-2 mb-4">
            @foreach($buckets as $bucket)
            @php
                $bucketTotal = ($grouped[$bucket] ?? collect())->sum('residual');
                $bucketCount = ($grouped[$bucket] ?? collect())->count();
                $color = $bucketColors[$bucket] ?? 'gray';
            @endphp
            <div class="bg-white rounded-lg border border-{{ $color }}-200 shadow-sm p-3">
                <p class="text-[10px] font-semibold text-gray-500 uppercase">{{ $bucket }} days</p>
                <p class="text-lg font-bold tabular-nums {{ $bucketTotal > 0 ? 'text-' . $color . '-700' : 'text-gray-400' }}"><x-money :amount="(float) $bucketTotal" /></p>
                <p class="text-[10px] text-gray-400">{{ $bucketCount }} {{ Str::plural('item', $bucketCount) }}</p>
            </div>
            @endforeach
        </div>
        @endif

        {{-- Buckets --}}
        @foreach($buckets as $bucket)
        @if(($grouped[$bucket] ?? collect())->isNotEmpty())
        <div class="mb-3 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-200 flex items-center justify-between">
                <span class="text-sm font-bold text-gray-800">{{ $bucket }} {{ __('accounting.days_overdue') }}</span>
                <span class="text-xs text-gray-500"><x-money :amount="(float) $grouped[$bucket]->sum('residual')" /></span>
            </div>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/50">
                        <th class="px-4 py-1.5 text-left text-xs font-medium text-gray-500">Invoice</th>
                        <th class="px-4 py-1.5 text-left text-xs font-medium text-gray-500">{{ __('accounting.col_partner') }}</th>
                        <th class="px-4 py-1.5 text-left text-xs font-medium text-gray-500">Invoice Date</th>
                        <th class="px-4 py-1.5 text-left text-xs font-medium text-gray-500">{{ __('accounting.col_due_date') }}</th>
                        <th class="px-4 py-1.5 text-right text-xs font-medium text-gray-500">{{ __('accounting.days_overdue') }}</th>
                        <th class="px-4 py-1.5 text-right text-xs font-medium text-gray-500">Residual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($grouped[$bucket] as $row)
                    <tr class="hover:bg-purple-50/30">
                        <td class="px-4 py-1.5">
                            <a href="{{ route('accounting.invoices.show', $row->move_id) }}" class="text-purple-600 hover:underline text-sm font-medium">{{ $row->name ?: '(Draft)' }}</a>
                            @if(($row->total_installments ?? 1) > 1)
                            <span class="ms-1 inline-block px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-50 text-blue-700 border border-blue-200">{{ $row->installment_number }}/{{ $row->total_installments }}</span>
                            @endif
                        </td>
                        <td class="px-4 py-1.5 text-sm text-gray-700">{{ $row->partner_name }}</td>
                        <td class="px-4 py-1.5 text-sm tabular-nums text-gray-600">{{ $row->invoice_date instanceof \Carbon\CarbonInterface ? $row->invoice_date->format('Y-m-d') : $row->invoice_date }}</td>
                        <td class="px-4 py-1.5 text-sm tabular-nums {{ $row->days_overdue > 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">{{ $row->invoice_date_due instanceof \Carbon\CarbonInterface ? $row->invoice_date_due->format('Y-m-d') : $row->invoice_date_due }}</td>
                        <td class="px-4 py-1.5 text-right text-sm tabular-nums {{ $row->days_overdue > 0 ? 'text-red-600' : 'text-gray-600' }}">{{ $row->days_overdue }}</td>
                        <td class="px-4 py-1.5 text-right text-sm tabular-nums font-medium text-gray-800"><x-money :amount="(float) $row->residual" /></td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @endforeach

        @if($rows->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center text-sm text-gray-400">No overdue receivables as of {{ $filters['as_of'] ?? '' }}.</div>
        @else
        <div class="bg-white rounded-xl border-2 border-red-200 shadow-sm p-4 flex justify-between items-center">
            <span class="text-sm font-bold text-gray-800">Total Outstanding</span>
            <span class="text-lg font-bold tabular-nums text-red-600"><x-money :amount="(float) $rows->sum('residual')" /></span>
        </div>
        @endif
    </div>
</div>
@endsection
