@extends('layouts.app')
@section('title', 'Edit Putaway Rule')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.putaway-rules.update', $putawayRule) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.putaway-rules.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Putaway Rules</a>
                <span class="text-sm font-semibold text-gray-800">Edit</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.putaway-rules.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">Discard</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">Save</button>
                </div>
            </x-slot:actions>
        </x-toolbar>
        <div class="flex-1 overflow-y-auto">
            <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
                @if($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
                @endif

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">When Arriving In</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_id" relation="many2one" :selected="old('location_id', $putawayRule->location_id)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Store In</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="fixed_location_id" relation="many2one" :selected="old('fixed_location_id', $putawayRule->fixed_location_id)" class="flex-1" compact />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Product</label>
                            <x-relation-dropdown table="inventory_products" field="name" name="product_id" relation="many2one" :selected="old('product_id', $putawayRule->product_id)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Product Category</label>
                            <x-relation-dropdown table="inventory_product_categories" field="complete_name" name="product_category_id" relation="many2one" :selected="old('product_category_id', $putawayRule->product_category_id)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">Sequence</label>
                            <input type="number" name="sequence" value="{{ old('sequence', $putawayRule->sequence) }}" min="0" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
