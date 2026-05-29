@extends('layouts.app')
@section('title', __('inventory.new_reorder_rule'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.replenishment.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.replenishment.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.replenishment') }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.new_reorder_rule') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.replenishment.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
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

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.product') }}</label>
                            <x-relation-dropdown table="inventory_products" field="name" name="product_id" relation="many2one" :selected="old('product_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.location') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="location_id" relation="many2one" :selected="old('location_id')" class="flex-1" compact />
                        </div>
                        {{-- route_id is stored on the rule but NOT consumed
                             by ReorderRuleController::replenish() — that path
                             hardcodes a receipt OperationType lookup. Left in
                             the form so configuration data isn't lost when
                             route-driven replenishment is wired up later. --}}
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">
                                {{ __('inventory.route') }}
                                <span class="ml-1 text-[10px] font-medium text-amber-600 uppercase tracking-wide">{{ __('inventory.stored_only') }}</span>
                            </label>
                            <x-relation-dropdown table="inventory_routes" field="name" name="route_id" relation="many2one" :selected="old('route_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.company') }}</label>
                            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one" :selected="old('company_id', $defaultCompanyId ?? null)" class="flex-1" compact />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.qty_min') }}</label>
                            <input type="number" name="qty_min" value="{{ old('qty_min', 0) }}" step="0.01" min="0" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.qty_max') }}</label>
                            <input type="number" name="qty_max" value="{{ old('qty_max', 0) }}" step="0.01" min="0" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.qty_multiple') }}</label>
                            <input type="number" name="qty_multiple" value="{{ old('qty_multiple', 1) }}" step="0.01" min="0" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.lead_time') }}</label>
                            <input type="number" name="lead_days" value="{{ old('lead_days', 0) }}" step="1" min="0" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
