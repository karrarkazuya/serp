@extends('layouts.app')
@section('title', 'Product Categories')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\ProductCategory::class)
        <a href="{{ route('inventory.config.product-categories.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Product Categories</span>
        <x-search :model="\App\Models\Inventory\ProductCategory::class" :action="route('inventory.config.product-categories.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($categories->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $categories->firstItem() }}-{{ $categories->lastItem() }} / {{ $categories->total() }}</span>
            @endif
        </div>
    </div>

    <x-list :paginator="$categories" empty-text="No product categories found.">
        <x-slot:columns>
            <x-sortable-th column="complete_name" label="Name" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Parent</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Removal Strategy</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Costing Method</th>
        </x-slot:columns>
        @foreach($categories as $category)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.product-categories.show', $category) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $category->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $category->parent?->name ?? '-' }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ ucwords(str_replace('_', ' ', $category->removal_strategy)) }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ ucwords(str_replace('_', ' ', $category->costing_method)) }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
