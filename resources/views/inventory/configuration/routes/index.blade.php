@extends('layouts.app')
@section('title', 'Routes')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Route::class)
        <a href="{{ route('inventory.config.routes.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Routes</span>
        <x-search :model="\App\Models\Inventory\Route::class" :action="route('inventory.config.routes.index')" />
    </div>

    <x-list :paginator="$routes" empty-text="No routes found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Name" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Rules</th>
        </x-slot:columns>
        @foreach($routes as $route)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.routes.show', $route) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $route->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $route->company?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600 text-right">{{ $route->rules_count ?? 0 }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
