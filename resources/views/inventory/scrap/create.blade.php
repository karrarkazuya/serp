@extends('layouts.app')
@section('title', __('inventory.new_scrap_order'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.scrap.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.scrap.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.scrap_orders') }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.new') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.scrap.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">{{ __('inventory.save') }}</button>
                </div>
            </x-slot:actions>
        </x-toolbar>

        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-x-8" x-data="{
                    productId: '{{ old('product_id') }}',
                    uomId: '',
                    uomName: '',
                    uomInfoUrl: @js(route('inventory.products.uom-info')),
                    async onProductSelected(e) {
                        const pid = e.detail.value;
                        if (!pid) return;
                        const res = await fetch(this.uomInfoUrl + '?product_id=' + pid, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                        });
                        const d = await res.json();
                        this.uomId = d.uom_id;
                        this.uomName = d.uom_name;
                    }
                }" @product-selected.window="onProductSelected($event)">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.product') }}</label>
                            <x-relation-dropdown table="inventory_products" field="name" name="product_id" relation="many2one" :selected="old('product_id')" event="product-selected" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.unit_of_measure') }}</label>
                            <div class="flex-1">
                                <input type="hidden" name="uom_id" :value="uomId">
                                <span x-text="uomName || '-'" class="text-sm text-gray-600"></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.lot_serial') }}</label>
                            <x-relation-dropdown table="inventory_lots" field="name" name="lot_id" relation="many2one" :selected="old('lot_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.quantity') }}</label>
                            <input type="number" name="scrap_qty" value="{{ old('scrap_qty', 1) }}" step="0.001" min="0.001" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.source_location') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_id" relation="many2one" :selected="old('location_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.scrap_location') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="scrap_location_id" relation="many2one" :selected="old('scrap_location_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.company') }}</label>
                            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one" :selected="old('company_id', $defaultCompanyId ?? null)" class="flex-1" compact />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
