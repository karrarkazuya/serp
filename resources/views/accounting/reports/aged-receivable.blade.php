@extends('layouts.app')
@section('title', 'Aged Receivable')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Aged Receivable</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', ['showAsOf' => true, 'asOf' => $asOf])

        @php
        $buckets = ['Current', '1–30', '31–60', '61–90', '90+'];
        $grouped = $rows->groupBy('bucket');
        @endphp

        @foreach($buckets as $bucket)
        @if(($grouped[$bucket] ?? collect())->isNotEmpty())
        <div class="mb-4 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-4 py-2 bg-gray-50 border-b border-gray-100">
                <span class="text-xs font-semibold text-gray-600 uppercase">{{ $bucket }} days overdue</span>
            </div>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-4 py-2 text-left text-xs text-gray-400">Invoice</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-400">Partner</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-400">Invoice Date</th>
                        <th class="px-4 py-2 text-left text-xs text-gray-400">Due Date</th>
                        <th class="px-4 py-2 text-right text-xs text-gray-400">Days Overdue</th>
                        <th class="px-4 py-2 text-right text-xs text-gray-400">Residual</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($grouped[$bucket] as $row)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2">
                            <a href="{{ route('accounting.invoices.show', $row->id) }}" class="text-purple-600 hover:underline text-sm">{{ $row->name }}</a>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-700">{{ $row->partner_name }}</td>
                        <td class="px-4 py-2 text-sm tabular-nums text-gray-600">{{ $row->invoice_date }}</td>
                        <td class="px-4 py-2 text-sm tabular-nums {{ $row->days_overdue > 0 ? 'text-red-600 font-medium' : 'text-gray-600' }}">{{ $row->invoice_date_due }}</td>
                        <td class="px-4 py-2 text-right text-sm tabular-nums {{ $row->days_overdue > 0 ? 'text-red-600' : 'text-gray-600' }}">{{ $row->days_overdue }}</td>
                        <td class="px-4 py-2 text-right text-sm tabular-nums font-medium text-gray-800">{{ number_format($row->residual, 2) }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif
        @endforeach

        @if($rows->isEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-8 text-center text-sm text-gray-400">No overdue receivables as of {{ $asOf }}.</div>
        @else
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 flex justify-between items-center">
            <span class="text-sm font-bold text-gray-800">Total Overdue</span>
            <span class="text-lg font-bold tabular-nums text-red-600">{{ number_format($rows->sum('residual'), 2) }}</span>
        </div>
        @endif
    </div>
</div>
@endsection
