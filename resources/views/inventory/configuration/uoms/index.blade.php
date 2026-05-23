@extends('layouts.app')
@section('title', 'Units of Measure')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <a href="{{ route('inventory.config.uoms.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        <span class="text-xl font-semibold text-gray-700 shrink-0">Units of Measure</span>
        <x-search :model="\App\Models\Inventory\Uom::class" :action="route('inventory.config.uoms.index')" />
    </div>

    <x-list :paginator="$uoms" empty-text="No units of measure found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Name" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Category</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Type</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Ratio</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Rounding</th>
        </x-slot:columns>
        @foreach($uoms as $uom)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.uoms.show', $uom) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $uom->name }}@if($uom->symbol) <span class="text-gray-400 text-xs">({{ $uom->symbol }})</span>@endif</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $uom->category?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ ucfirst($uom->uom_type) }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-right">{{ $uom->ratio }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-right">{{ $uom->rounding }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
