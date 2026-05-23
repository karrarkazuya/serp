@extends('layouts.app')
@section('title', __('inventory.edit') . ': ' . $uom->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.uoms.update', $uom) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.uoms.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.units_of_measure') }}</a>
                <a href="{{ route('inventory.config.uoms.show', $uom) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $uom->name }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.edit') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.uoms.show', $uom) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
                    <button type="submit" class="px-3 py-1.5 text-sm font-semibold text-white bg-[#714B67] hover:bg-[#5c3d55] rounded">{{ __('inventory.save') }}</button>
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

                <div class="mb-5">
                    <input type="text" name="name" value="{{ old('name', $uom->name) }}" required placeholder="{{ __('inventory.name') }}"
                           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent border-gray-200">
                </div>

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('inventory.category') }}</label>
                            <x-relation-dropdown table="inventory_uom_categories" field="name" name="uom_category_id" relation="many2one"
                                :selected="old('uom_category_id', $uom->uom_category_id)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('inventory.symbol') }}</label>
                            <input type="text" name="symbol" value="{{ old('symbol', $uom->symbol) }}" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5" placeholder="-">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('inventory.type') }}</label>
                            <select name="uom_type" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                @foreach(['reference' => __('inventory.reference_unit'), 'bigger' => __('inventory.bigger_than_reference'), 'smaller' => __('inventory.smaller_than_reference')] as $k => $v)
                                <option value="{{ $k }}" {{ old('uom_type', $uom->uom_type) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('inventory.ratio') }}</label>
                            <input type="number" name="ratio" value="{{ old('ratio', $uom->ratio) }}" step="0.000001" min="0.000001" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('inventory.rounding') }}</label>
                            <input type="number" name="rounding" value="{{ old('rounding', $uom->rounding) }}" step="0.000001" min="0.000001" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
