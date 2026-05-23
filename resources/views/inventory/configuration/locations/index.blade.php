@extends('layouts.app')
@section('title', 'Locations')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Location::class)
        <a href="{{ route('inventory.config.locations.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Locations</span>
        <x-search :model="\App\Models\Inventory\Location::class" :action="route('inventory.config.locations.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($locations->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $locations->firstItem() }}-{{ $locations->lastItem() }} / {{ $locations->total() }}</span>
            @endif
        </div>
    </div>

    <x-list :paginator="$locations" empty-text="No locations found.">
        <x-slot:columns>
            <x-sortable-th column="complete_name" label="Location" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Type</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
        </x-slot:columns>
        @foreach($locations as $location)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.locations.show', $location) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">
                {{ $location->complete_name }}
                @if(!$location->active) <span class="ms-1 text-[10px] text-amber-600 font-semibold uppercase">Archived</span> @endif
            </td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $location->usage_label }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $location->company?->name ?? 'All Companies' }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
