@extends('layouts.app')
@section('title', 'Operation Types')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\OperationType::class)
        <a href="{{ route('inventory.config.operation-types.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Operation Types</span>
        <x-search :model="\App\Models\Inventory\OperationType::class" :action="route('inventory.config.operation-types.index')" />
    </div>

    <x-list :paginator="$operationTypes" empty-text="No operation types found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Name" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Type</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Warehouse</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
        </x-slot:columns>
        @foreach($operationTypes as $opType)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.operation-types.show', $opType) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $opType->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $opType->code_label }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $opType->warehouse?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $opType->company?->name }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
