@extends('layouts.app')
@section('title', $product->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\Product::class)
        @php $newHref = route('inventory.products.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.products.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.products.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.products.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.products') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $product->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $product)
                <a href="{{ route('inventory.products.edit', $product) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                @if($product->active)
                <form method="POST" action="{{ route('inventory.products.archive', $product) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('inventory.archive') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('inventory.products.unarchive', $product) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('inventory.restore') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $product)
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.products.delete', $product) }}">
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
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            @if(!$product->active)
            <div class="px-6 pt-4"><div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('inventory.product_archived') }}</div></div>
            @endif

            <div class="p-6">
                <div class="flex gap-8">
                    <div class="flex-1">
                        <h1 class="text-2xl font-bold text-gray-900 mb-1">{{ $product->name }}</h1>
                        @if($product->internal_reference)
                        <p class="text-sm text-gray-500 mb-4">[{{ $product->internal_reference }}]</p>
                        @endif

                        @foreach([
                            [__('inventory.product_type'), $product->product_type_label],
                            [__('inventory.category'), $product->category?->name],
                            [__('inventory.unit_of_measure'), $product->uom?->name],
                            [__('inventory.purchase_uom'), $product->uomPo?->name],
                            [__('inventory.barcode'), $product->barcode],
                            [__('inventory.tracking'), $product->tracking_label],
                            [__('inventory.sales_price'), number_format($product->sale_price ?? 0, 2)],
                            [__('inventory.cost'), number_format($product->cost ?? 0, 2)],
                        ] as [$label, $value])
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                            <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                        </div>
                        @endforeach
                    </div>

                    <div class="shrink-0 text-end">
                        @if($product->image_uuid)
                            <img src="{{ route('files.serve', $product->image_uuid) }}" alt="{{ $product->name }}" class="w-36 h-36 object-cover rounded-xl border border-gray-200 shadow-sm">
                        @else
                            <div class="w-36 h-36 rounded-xl flex items-center justify-center text-4xl font-bold bg-purple-100 text-purple-600">
                                {{ strtoupper(substr($product->name, 0, 2)) }}
                            </div>
                        @endif

                        <div class="mt-4 text-center">
                            <a href="{{ route('inventory.reports.stock', ['product_id' => $product->id]) }}" class="text-xs text-purple-600 hover:underline">
                                {{ number_format($quants->sum('quantity'), 2) }} {{ __('inventory.on_hand') }}
                            </a>
                        </div>
                    </div>
                </div>

                {{-- Routes --}}
                @if($product->routes->isNotEmpty())
                <div class="mt-4 flex items-center gap-4 py-2 border-b border-gray-100">
                    <span class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.section_routes') }}</span>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($product->routes as $route)
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-700">{{ $route->name }}</span>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Suppliers --}}
                @if($product->suppliers->isNotEmpty())
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-2">{{ __('inventory.section_vendors') }}</h3>
                    <table class="w-full text-sm">
                        <thead><tr class="border-b border-gray-200">
                            <th class="py-1.5 text-start text-xs text-gray-500">{{ __('inventory.vendor') }}</th>
                            <th class="py-1.5 text-end text-xs text-gray-500">{{ __('inventory.price') }}</th>
                            <th class="py-1.5 text-end text-xs text-gray-500">{{ __('inventory.min_qty') }}</th>
                            <th class="py-1.5 text-end text-xs text-gray-500">{{ __('inventory.lead_time') }}</th>
                        </tr></thead>
                        <tbody>
                            @foreach($product->suppliers as $supplier)
                            <tr class="border-b border-gray-100">
                                <td class="py-1.5 text-gray-800">{{ $supplier->partner?->name ?? $supplier->partner_name }}</td>
                                <td class="py-1.5 text-end text-gray-800">{{ number_format($supplier->price, 2) }}</td>
                                <td class="py-1.5 text-end text-gray-600">{{ $supplier->min_qty }}</td>
                                <td class="py-1.5 text-end text-gray-600">{{ $supplier->delay }} {{ __('inventory.days') }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter model-type="App\Models\Inventory\Product" :model-id="$product->id"
                :can-comment="auth()->user()->can('comment', $product)" />
        </div>

        <div class="px-4 pb-4 text-xs text-gray-400 flex gap-6">
            <span>Created: {{ $product->created_at->format('M d, Y') }}{{ $product->creator ? ' · ' . $product->creator->name : '' }}</span>
            <span>Updated: {{ $product->updated_at->diffForHumans() }}{{ $product->updater ? ' · ' . $product->updater->name : '' }}</span>
        </div>
    </div>
</div>
@endsection
