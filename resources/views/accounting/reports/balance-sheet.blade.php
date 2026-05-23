@extends('layouts.app')
@section('title', 'Balance Sheet')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Balance Sheet</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', ['dateFrom' => $dateFrom, 'dateTo' => $dateTo])

        @php
        function reportSection($rows, $title, $colorClass, $total) {
            return compact('rows', 'title', 'colorClass', 'total');
        }
        $sections = [
            ['rows' => $assets,      'title' => 'Assets',      'colorClass' => 'blue',   'total' => $totalAssets],
            ['rows' => $liabilities, 'title' => 'Liabilities', 'colorClass' => 'red',    'total' => $totalLiabilities],
            ['rows' => $equity,      'title' => 'Equity',      'colorClass' => 'purple', 'total' => $totalEquity],
        ];
        @endphp

        <div class="max-w-2xl space-y-4">
            @foreach($sections as $section)
            @php
                $color = $section['colorClass'];
                $bgHdr = "bg-{$color}-50 border-{$color}-100";
                $textHdr = "text-{$color}-800";
                $bgFtr = "bg-{$color}-50 border-{$color}-100";
            @endphp
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-800">{{ $section['title'] }}</h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @foreach($section['rows'] as $row)
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-2.5 font-mono text-gray-600">{{ $row->account_code }}</td>
                            <td class="px-2 py-2.5 text-gray-700">{{ $row->account_name }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums text-gray-800">{{ number_format(abs($row->net), 2) }}</td>
                        </tr>
                        @endforeach
                        @if($section['title'] === 'Equity' && $currentYearEarnings != 0)
                        <tr class="hover:bg-gray-50 italic">
                            <td class="px-5 py-2.5 font-mono text-gray-400">—</td>
                            <td class="px-2 py-2.5 text-gray-500">Current Year Earnings</td>
                            <td class="px-5 py-2.5 text-right tabular-nums {{ $currentYearEarnings >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                {{ number_format($currentYearEarnings, 2) }}
                            </td>
                        </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                        <tr>
                            <td colspan="2" class="px-5 py-2.5 text-sm font-bold text-gray-800">Total {{ $section['title'] }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-bold text-gray-800">{{ number_format($section['total'], 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endforeach

            <div class="bg-white rounded-xl border-2 border-gray-300 shadow-sm p-5">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-700">Assets = Liabilities + Equity</span>
                    <span class="text-sm font-semibold {{ abs($totalAssets - ($totalLiabilities + $totalEquity)) < 1 ? 'text-green-600' : 'text-red-600' }}">
                        {{ number_format($totalAssets, 2) }} = {{ number_format($totalLiabilities + $totalEquity, 2) }}
                        @if(abs($totalAssets - ($totalLiabilities + $totalEquity)) < 1) ✓ @endif
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
