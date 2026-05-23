@extends('layouts.app')
@section('title', 'Lots / Serial Numbers')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Lot::class)
        <a href="{{ route('inventory.lots.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan

        <span class="text-xl font-semibold text-gray-700 shrink-0">Lots / Serial Numbers</span>
        <x-search :model="\App\Models\Inventory\Lot::class" :action="route('inventory.lots.index')" />

        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($lots->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $lots->firstItem() }}-{{ $lots->lastItem() }} / {{ $lots->total() }}</span>
            @endif
            <div class="flex items-center gap-1">
                @if($lots->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $lots->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($lots->hasMorePages())
                    <a href="{{ $lots->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$lots" empty-text="No lots or serial numbers found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Lot / Serial Number" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Product</th>
            <x-sortable-th column="expiration_date" label="Expiration Date" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">On Hand</th>
        </x-slot:columns>
        @foreach($lots as $lot)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.lots.show', $lot) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $lot->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $lot->product?->name }}</td>
            <td class="px-3 py-2 text-sm {{ $lot->isExpired() ? 'text-red-600 font-medium' : 'text-gray-600' }}">
                {{ $lot->expiration_date?->format('M d, Y') ?? '-' }}
            </td>
            <td class="px-3 py-2 text-sm text-gray-800 text-right">{{ number_format($lot->getOnHandQty(), 2) }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
