@extends('layouts.app')
@section('title', 'Scrap Orders')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\ScrapOrder::class)
        <a href="{{ route('inventory.scrap.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Scrap Orders</span>
        <x-search :model="\App\Models\Inventory\ScrapOrder::class" :action="route('inventory.scrap.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($scrapOrders->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $scrapOrders->firstItem() }}-{{ $scrapOrders->lastItem() }} / {{ $scrapOrders->total() }}</span>
            @endif
        </div>
    </div>

    <x-list :paginator="$scrapOrders" empty-text="No scrap orders found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Reference" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Product</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Qty</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Source Location</th>
            <x-sortable-th column="date_done" label="Date" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Status</th>
        </x-slot:columns>
        @foreach($scrapOrders as $scrap)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.scrap.show', $scrap) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $scrap->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $scrap->product?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-right">{{ number_format($scrap->scrap_qty, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $scrap->location?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $scrap->date_done?->format('M d, Y') }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $scrap->state_color }}-100 text-{{ $scrap->state_color }}-700">
                    {{ ucfirst($scrap->state) }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
