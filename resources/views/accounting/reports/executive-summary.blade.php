@extends('layouts.app')
@section('title', 'Executive Summary')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Executive Summary</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])

        <div class="grid grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
            @php
            $cards = [
                ['label' => 'Total Income',   'value' => $totalIncome,   'color' => 'green'],
                ['label' => 'Total Expenses', 'value' => $totalExpense,  'color' => 'red'],
                ['label' => $netProfit >= 0 ? 'Net Profit' : 'Net Loss', 'value' => abs($netProfit), 'color' => $netProfit >= 0 ? 'green' : 'red'],
                ['label' => 'Total Assets',   'value' => $totalAssets,   'color' => 'blue'],
                ['label' => 'Total Liabilities', 'value' => $totalLiabs, 'color' => 'orange'],
                ['label' => 'Draft Entries',  'value' => $draftCount,    'color' => 'gray', 'is_count' => true],
            ];
            @endphp

            @foreach($cards as $card)
            @php
            $textColor = match($card['color']) {
                'green'  => 'text-green-700',
                'red'    => 'text-red-600',
                'blue'   => 'text-blue-700',
                'orange' => 'text-orange-700',
                default  => 'text-gray-700',
            };
            $bgColor = match($card['color']) {
                'green'  => 'bg-green-50 border-green-200',
                'red'    => 'bg-red-50 border-red-200',
                'blue'   => 'bg-blue-50 border-blue-200',
                'orange' => 'bg-orange-50 border-orange-200',
                default  => 'bg-gray-50 border-gray-200',
            };
            @endphp
            <div class="rounded-xl border {{ $bgColor }} p-5">
                <p class="text-xs font-medium text-gray-500 mb-1">{{ $card['label'] }}</p>
                <p class="text-2xl font-bold tabular-nums {{ $textColor }}">
                    {{ isset($card['is_count']) ? $card['value'] : number_format($card['value'], 2) }}
                </p>
            </div>
            @endforeach
        </div>

        @if($overdueCount > 0)
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-center gap-3">
            <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div>
                <p class="text-sm font-semibold text-red-800">{{ $overdueCount }} overdue document{{ $overdueCount !== 1 ? 's' : '' }}</p>
                <p class="text-xs text-red-600">Invoices or bills past their due date with outstanding balances.</p>
            </div>
            <div class="ms-auto flex gap-2">
                <a href="{{ route('accounting.reports.aged-receivable') }}" class="text-xs text-red-700 underline">Aged Receivable</a>
                <a href="{{ route('accounting.reports.aged-payable') }}" class="text-xs text-red-700 underline">Aged Payable</a>
            </div>
        </div>
        @endif
    </div>
</div>
@endsection
