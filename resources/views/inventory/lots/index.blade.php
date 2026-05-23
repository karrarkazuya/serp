@extends('layouts.app')
@section('title', __('inventory.lots'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Lot::class)
        <a href="{{ route('inventory.lots.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        @endcan

        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.lots') }}</span>
        <x-search :model="\App\Models\Inventory\Lot::class" :action="route('inventory.lots.index')" />

        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($lots) && $lots->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $lots->firstItem() }}-{{ $lots->lastItem() }} / {{ $lots->total() }}</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($lots) && $lots->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @elseif(isset($lots))
                    <a href="{{ $lots->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($lots) && $lots->hasMorePages())
                    <a href="{{ $lots->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @elseif(isset($lots))
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_lots') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_lot')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.product') }}</th>
            <x-sortable-th column="expiration_date" :label="__('inventory.expiration_date')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
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
            @foreach($group['items'] as $lot)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.lots.show', $lot) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $lot->name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $lot->product?->name }}</td>
                <td class="px-3 py-2 text-sm {{ $lot->isExpired() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                    {{ $lot->expiration_date?->format('M d, Y') ?? '-' }}
                </td>
                <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ number_format($lot->getOnHandQty(), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_lots') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$lots" empty-text="{{ __('inventory.no_lots') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_lot')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.product') }}</th>
            <x-sortable-th column="expiration_date" :label="__('inventory.expiration_date')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
        </x-slot:columns>
        @foreach($lots as $lot)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.lots.show', $lot) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $lot->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $lot->product?->name }}</td>
            <td class="px-3 py-2 text-sm {{ $lot->isExpired() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                {{ $lot->expiration_date?->format('M d, Y') ?? '-' }}
            </td>
            <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ number_format($lot->getOnHandQty(), 2) }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
