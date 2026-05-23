@extends('layouts.app')
@section('title', __('inventory.products'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Product::class)
        <a href="{{ route('inventory.products.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        @endcan

        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.products') }}</span>

        <x-search :model="\App\Models\Inventory\Product::class" :action="route('inventory.products.index')" />

        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($products) && $products->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $products->firstItem() }}-{{ $products->lastItem() }} / {{ $products->total() }}</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($products) && $products->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @elseif(isset($products))
                    <a href="{{ $products->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($products) && $products->hasMorePages())
                    <a href="{{ $products->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @elseif(isset($products))
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif

            <div class="hidden sm:flex items-center rounded overflow-hidden border border-gray-200">
                <a href="{{ route('inventory.products.index', array_merge(request()->except('view','page'), ['view' => 'kanban'])) }}"
                   class="w-9 h-9 inline-flex items-center justify-center {{ ($view??'kanban') === 'kanban' ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:bg-gray-100' }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M3 3h6v14H3V3zm8 0h6v6h-6V3zm0 8h6v6h-6v-6z"/></svg>
                </a>
                <a href="{{ route('inventory.products.index', array_merge(request()->except('view','page'), ['view' => 'list'])) }}"
                   class="w-9 h-9 inline-flex items-center justify-center {{ ($view??'kanban') === 'list' ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:bg-gray-100' }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/></svg>
                </a>
            </div>
        </div>
    </div>

    @php $view = $view ?? request('view', 'kanban'); @endphp

    @if($view === 'kanban')
    <div class="flex-1 overflow-y-auto p-3 sm:p-4 bg-gray-100">
        @if($products->isEmpty())
            <div class="py-24 text-center text-gray-400 text-sm">{{ __('inventory.no_products') }}</div>
        @else
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-7 gap-3">
            @foreach($products as $product)
            <a href="{{ route('inventory.products.show', $product) }}" class="group bg-white rounded-lg border border-gray-200 shadow-sm hover:shadow-md transition-all overflow-hidden">
                <div class="h-32 bg-gray-50 flex items-center justify-center overflow-hidden border-b border-gray-100">
                    @if($product->image_uuid)
                        <img src="{{ route('files.serve', $product->image_uuid) }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="w-14 h-14 bg-purple-100 rounded-lg flex items-center justify-center text-lg font-bold text-purple-600">
                            {{ strtoupper(substr($product->name, 0, 2)) }}
                        </div>
                    @endif
                </div>
                <div class="p-2.5">
                    <h3 class="text-xs font-semibold text-gray-900 truncate group-hover:text-purple-700">{{ $product->name }}</h3>
                    @if($product->internal_reference)
                    <p class="text-[11px] text-gray-400 mt-0.5">[{{ $product->internal_reference }}]</p>
                    @endif
                    <p class="text-[11px] text-gray-500 mt-0.5">{{ $product->product_type_label }}</p>
                    @if(!$product->active)
                    <span class="inline-block mt-0.5 text-[10px] font-semibold text-amber-600 uppercase">{{ __('inventory.status_archived') }}</span>
                    @endif
                </div>
            </a>
            @endforeach
        </div>
        @endif
    </div>
    @elseif(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_products') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_product')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.reference') }}</th>
            <x-sortable-th column="product_type" :label="__('inventory.col_type')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.category') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.unit_of_measure') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
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
            @foreach($group['items'] as $product)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.products.show', $product) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">
                    {{ $product->name }}
                    @if(!$product->active) <span class="ms-1 text-[10px] text-amber-600 font-semibold uppercase">{{ __('inventory.status_archived') }}</span> @endif
                </td>
                <td class="px-3 py-2 text-gray-500 text-sm">{{ $product->internal_reference }}</td>
                <td class="px-3 py-2 text-gray-600 text-sm">{{ $product->product_type_label }}</td>
                <td class="px-3 py-2 text-gray-600 text-sm">{{ $product->category?->name }}</td>
                <td class="px-3 py-2 text-gray-600 text-sm">{{ $product->uom?->name }}</td>
                <td class="px-3 py-2 text-gray-600 text-sm text-end">{{ number_format($product->getOnHandQty(), 2) }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_products') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$products" empty-text="{{ __('inventory.no_products') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_product')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.reference') }}</th>
            <x-sortable-th column="product_type" :label="__('inventory.col_type')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.category') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.unit_of_measure') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
        </x-slot:columns>
        @foreach($products as $product)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.products.show', $product) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">
                {{ $product->name }}
                @if(!$product->active) <span class="ms-1 text-[10px] text-amber-600 font-semibold uppercase">{{ __('inventory.status_archived') }}</span> @endif
            </td>
            <td class="px-3 py-2 text-gray-500 text-sm">{{ $product->internal_reference }}</td>
            <td class="px-3 py-2 text-gray-600 text-sm">{{ $product->product_type_label }}</td>
            <td class="px-3 py-2 text-gray-600 text-sm">{{ $product->category?->name }}</td>
            <td class="px-3 py-2 text-gray-600 text-sm">{{ $product->uom?->name }}</td>
            <td class="px-3 py-2 text-gray-600 text-sm text-end">{{ number_format($product->getOnHandQty(), 2) }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
