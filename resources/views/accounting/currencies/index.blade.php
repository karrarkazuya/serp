@extends('layouts.app')
@section('title', 'Exchange Rates')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->hasPermission('accounting.write') ? route('accounting.currencies.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Exchange Rates</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search :model="\App\Models\Accounting\CurrencyRate::class" />
        </x-slot:search>
    </x-toolbar>

    @if(session('success'))<div class="shrink-0 px-4 py-2 bg-green-50 border-b border-green-200 text-sm text-green-700">{{ session('success') }}</div>@endif

    @if(isset($groups))
    <x-list :grouped="true" empty-text="No exchange rates found.">
        <x-slot:columns>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Currency</th>
            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Rate (per 1 foreign unit)</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Effective Date</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Company</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $rate)
            <tr x-show="open" class="hover:bg-gray-50 cursor-pointer" onclick="window.location='{{ route('accounting.currencies.show', $rate) }}'">
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
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">No exchange rates found.</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
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
    @endif
</div>
@endsection
