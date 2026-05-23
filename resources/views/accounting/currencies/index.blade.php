@extends('layouts.app')
@section('title', 'Exchange Rates')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.write') ? route('accounting.currencies.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Exchange Rates</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        @if(session('success'))
        <div class="mb-4 px-3 py-2 bg-green-50 border border-green-200 text-sm text-green-700 rounded">{{ session('success') }}</div>
        @endif

        <x-search :model="\App\Models\Accounting\CurrencyRate::class" />

        <x-list :paginator="$rates" empty-text="No exchange rates found.">
            <x-slot:columns>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Currency</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Rate (per 1 foreign unit)</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Effective Date</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Company</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
            </x-slot:columns>

            @foreach($rates as $rate)
            <tr class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.currencies.show', $rate) }}'">
                <td class="px-4 py-3 text-sm font-semibold text-[#71639e]">{{ $rate->currency }}</td>
                <td class="px-4 py-3 text-sm tabular-nums text-right text-gray-800">{{ number_format((float)$rate->rate, 4) }}</td>
                <td class="px-4 py-3 text-sm text-gray-700">{{ $rate->date->format('Y-m-d') }}</td>
                <td class="px-4 py-3 text-sm text-gray-600">{{ $rate->company?->name ?? '—' }}</td>
                <td class="px-4 py-3">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $rate->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $rate->active ? 'Active' : 'Inactive' }}
                    </span>
                </td>
            </tr>
            @endforeach
        </x-list>
    </div>
</div>
@endsection
