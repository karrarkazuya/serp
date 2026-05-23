@extends('layouts.app')
@section('title', 'Physical Inventory')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\InventoryAdjustment::class)
        <a href="{{ route('inventory.adjustments.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Physical Inventory</span>
        <x-search :model="\App\Models\Inventory\InventoryAdjustment::class" :action="route('inventory.adjustments.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($adjustments->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $adjustments->firstItem() }}-{{ $adjustments->lastItem() }} / {{ $adjustments->total() }}</span>
            @endif
            <div class="flex items-center gap-1">
                @if($adjustments->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $adjustments->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($adjustments->hasMorePages())
                    <a href="{{ $adjustments->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$adjustments" empty-text="No physical inventory adjustments found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Reference" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
            <x-sortable-th column="date" label="Date" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Status</th>
        </x-slot:columns>
        @foreach($adjustments as $adj)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.adjustments.show', $adj) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $adj->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $adj->company?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $adj->date?->format('M d, Y') }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $adj->state_color }}-100 text-{{ $adj->state_color }}-700">
                    {{ $adj->state_label }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
