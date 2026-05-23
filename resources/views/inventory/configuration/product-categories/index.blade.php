@extends('layouts.app')
@section('title', __('inventory.product_categories'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\ProductCategory::class)
        <a href="{{ route('inventory.config.product-categories.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.product_categories') }}</span>
        <x-search :model="\App\Models\Inventory\ProductCategory::class" :action="route('inventory.config.product-categories.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($categories) && $categories->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $categories->firstItem() }}-{{ $categories->lastItem() }} / {{ $categories->total() }}</span>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_product_categories') }}">
        <x-slot:columns>
            <x-sortable-th column="complete_name" :label="__('inventory.col_name')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_parent') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.removal_strategy') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_costing_method') }}</th>
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $category)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.product-categories.show', $category) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $category->complete_name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $category->parent?->name ?? '-' }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ ucwords(str_replace('_', ' ', $category->removal_strategy)) }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ ucwords(str_replace('_', ' ', $category->costing_method)) }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_product_categories') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$categories" empty-text="{{ __('inventory.no_product_categories') }}">
        <x-slot:columns>
            <x-sortable-th column="complete_name" :label="__('inventory.col_name')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_parent') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.removal_strategy') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_costing_method') }}</th>
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
    @endif
</div>
@endsection
