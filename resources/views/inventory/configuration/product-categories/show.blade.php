@extends('layouts.app')
@section('title', $productCategory->complete_name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\ProductCategory::class)
        @php $newHref = route('inventory.config.product-categories.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.config.product-categories.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.config.product-categories.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.config.product-categories.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.product_categories') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $productCategory->complete_name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $productCategory)
                <a href="{{ route('inventory.config.product-categories.edit', $productCategory) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                @endcan
                @can('delete', $productCategory)
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.config.product-categories.delete', $productCategory) }}">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('inventory.delete') }}</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">{{ __('inventory.are_you_sure') }}</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('inventory.yes') }}</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">{{ __('inventory.cancel') }}</button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $productCategory->complete_name }}</h1>
            @foreach([
                [__('inventory.parent'), $productCategory->parent?->complete_name],
                [__('inventory.removal_strategy'), ucwords(str_replace('_', ' ', $productCategory->removal_strategy))],
                [__('inventory.costing_method'), ucwords(str_replace('_', ' ', $productCategory->costing_method))],
            ] as [$label, $value])
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
            </div>
            @endforeach

            @if($productCategory->children->isNotEmpty())
            <div class="mt-4">
                <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('inventory.product_categories') }}</h3>
                @foreach($productCategory->children as $child)
                <a href="{{ route('inventory.config.product-categories.show', $child) }}" class="block py-1.5 text-sm text-purple-600 hover:underline">{{ $child->name }}</a>
                @endforeach
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
