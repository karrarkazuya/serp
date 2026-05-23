@extends('layouts.app')
@section('title', __('inventory.edit') . ': ' . $operationType->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.operation-types.update', $operationType) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.operation-types.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.operation_types') }}</a>
                <a href="{{ route('inventory.config.operation-types.show', $operationType) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $operationType->name }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.edit') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.operation-types.show', $operationType) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
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
                    <input type="text" name="name" value="{{ old('name', $operationType->name) }}" required
                           class="w-full text-2xl font-bold text-gray-900 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent border-gray-200">
                </div>

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.sequence') }}</label>
                            <input type="text" name="sequence_prefix" value="{{ old('sequence_prefix', $operationType->sequence_prefix) }}" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.reference') }}</label>
                            <input type="number" name="sequence_padding" value="{{ old('sequence_padding', $operationType->sequence_padding) }}" min="1" max="10" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.from') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="default_location_src_id" relation="many2one" :selected="old('default_location_src_id', $operationType->default_location_src_id)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.to') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="default_location_dest_id" relation="many2one" :selected="old('default_location_dest_id', $operationType->default_location_dest_id)" class="flex-1" compact />
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.action') }}</label>
                            <select name="reservation_method" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                @foreach(['at_confirm' => 'At Confirmation', 'before_scheduled' => 'Before Scheduled Date', 'manual' => 'Manually'] as $k => $v)
                                <option value="{{ $k }}" {{ old('reservation_method', $operationType->reservation_method) === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.create_lots') }}</label>
                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="use_create_lots" value="1" {{ old('use_create_lots', $operationType->use_create_lots) ? 'checked' : '' }} class="rounded text-purple-600">
                                {{ __('inventory.allow_creating_lots') }}
                            </label>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.use_existing_lots') }}</label>
                            <label class="flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="use_existing_lots" value="1" {{ old('use_existing_lots', $operationType->use_existing_lots) ? 'checked' : '' }} class="rounded text-purple-600">
                                {{ __('inventory.allow_using_existing_lots') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
