@extends('layouts.app')
@section('title', __('inventory.stock_report'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.stock_report') }}</span>

        <form method="GET" action="{{ route('inventory.reports.stock') }}" class="flex items-center gap-2 ms-4">
            <x-relation-dropdown table="inventory_products" field="name" name="product_id" relation="many2one"
                :selected="request('product_id')" class="w-48" />
            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_id" relation="many2one"
                :selected="request('location_id')" class="w-56" />
            <label class="flex items-center gap-1.5 text-sm text-gray-600 cursor-pointer">
                <input type="checkbox" name="show_zero" value="1" {{ request('show_zero') ? 'checked' : '' }} class="rounded text-purple-600">
                {{ __('inventory.show_zero') }}
            </label>
            <button type="submit" class="px-3 py-1.5 text-sm bg-purple-600 text-white rounded hover:bg-purple-700">{{ __('inventory.apply') }}</button>
            <a href="{{ route('inventory.reports.stock') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.clear') }}</a>
        </form>

        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($quants->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $quants->firstItem() }}-{{ $quants->lastItem() }} / {{ $quants->total() }}</span>
            @endif
            <div class="flex items-center gap-1">
                @if($quants->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $quants->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($quants->hasMorePages())
                    <a href="{{ $quants->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$quants" empty-text="{{ __('inventory.no_stock') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_product') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_location') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_lot') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_reserved') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_available') }}</th>
        </x-slot:columns>
        @foreach($quants as $quant)
        <tr class="hover:bg-purple-50/30">
            <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $quant->product?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $quant->location?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $quant->lot?->name ?? '-' }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-end font-medium">{{ number_format($quant->qty, 2) }}</td>
            <td class="px-3 py-2 text-sm text-amber-600 text-end">{{ number_format($quant->reserved_qty, 2) }}</td>
            <td class="px-3 py-2 text-sm text-end font-semibold {{ $quant->getAvailableQty() < 0 ? 'text-red-600' : 'text-green-700' }}">
                {{ number_format($quant->getAvailableQty(), 2) }}
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
