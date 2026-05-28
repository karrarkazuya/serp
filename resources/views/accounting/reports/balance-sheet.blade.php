@extends('layouts.app')
@section('title', __('accounting.report_balance_sheet'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.report_balance_sheet') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @include('accounting.reports._filters', [
            'filters' => $filters, 'showAsOf' => true,
            'reportKey' => 'balance-sheet',
        ])

        @php
        $sections = [
            ['title' => __('accounting.total_assets'),      'rows' => $assets,      'total' => $total_assets,      'extra' => null],
            ['title' => __('accounting.total_liabilities'), 'rows' => $liabilities, 'total' => $total_liabilities, 'extra' => null],
            ['title' => __('accounting.total_equity'),      'rows' => $equity,      'total' => $total_equity,      'extra' => $current_year_earnings],
        ];
        @endphp

        <div class="max-w-3xl space-y-3">
            @foreach($sections as $section)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-5 py-2.5 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">{{ $section['title'] }}</h2>
                </div>
                <table class="min-w-full text-sm">
                    <tbody class="divide-y divide-gray-100">
                        @forelse($section['rows'] as $row)
                        <tr class="hover:bg-purple-50/30">
                            <td class="ps-8 pe-2 py-2 font-mono text-xs text-gray-500 w-20">{{ $row->account_code }}</td>
                            <td class="px-2 py-2 text-gray-700">{{ $row->account_name }}</td>
                            <td class="px-5 py-2 text-right tabular-nums text-gray-800 w-40"><x-money :amount="(float) abs($row->net)" /></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="px-5 py-6 text-sm text-gray-400 text-center">{{ __('accounting.no_accounts_found') }}</td></tr>
                        @endforelse
                        @if($loop->last && $section['extra'] != 0)
                        <tr class="italic bg-gray-50/50">
                            <td class="ps-8 pe-2 py-2 font-mono text-xs text-gray-400 w-20">—</td>
                            <td class="px-2 py-2 text-gray-500">{{ __('accounting.current_year_earnings') }}</td>
                            <td class="px-5 py-2 text-right tabular-nums {{ $section['extra'] >= 0 ? 'text-green-700' : 'text-red-700' }} w-40">
                                <x-money :amount="(float) $section['extra']" />
                            </td>
                        </tr>
                        @endif
                    </tbody>
                    <tfoot class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="2" class="px-5 py-2.5 text-sm font-bold text-gray-800">{{ $section['title'] }}</td>
                            <td class="px-5 py-2.5 text-right tabular-nums font-bold text-gray-900"><x-money :amount="(float) $section['total']" /></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            @endforeach

            {{-- Balance check --}}
            @php $diff = abs($total_assets - ($total_liabilities + $total_equity)); @endphp
            <div class="bg-white rounded-xl border-2 {{ $diff < 1 ? 'border-green-300' : 'border-red-300' }} shadow-sm p-4">
                <div class="flex justify-between items-center">
                    <span class="text-sm font-bold text-gray-700">{{ __('accounting.assets_equation') }}</span>
                    <span class="text-sm font-semibold {{ $diff < 1 ? 'text-green-600' : 'text-red-600' }}">
                        <x-money :amount="(float) $total_assets" /> = <x-money :amount="(float) ($total_liabilities + $total_equity)" />
                        @if($diff < 1) ✓ @else ✗ <x-money :amount="(float) $diff" /> @endif
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
