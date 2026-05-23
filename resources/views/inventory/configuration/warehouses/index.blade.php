@extends('layouts.app')
@section('title', 'Warehouses')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Warehouse::class)
        <a href="{{ route('inventory.config.warehouses.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Warehouses</span>
        <x-search :model="\App\Models\Inventory\Warehouse::class" :action="route('inventory.config.warehouses.index')" />
    </div>

    <x-list :paginator="$warehouses" empty-text="No warehouses found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Name" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Short Name</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
        </x-slot:columns>
        @foreach($warehouses as $warehouse)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.warehouses.show', $warehouse) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $warehouse->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $warehouse->code }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $warehouse->company?->name }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
