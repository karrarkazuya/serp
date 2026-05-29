@extends('layouts.app')
@section('title', __('inventory.operation_types'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.operation-types.store') }}" class="flex flex-col h-full">
        @csrf
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.operation-types.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.operation_types') }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.new') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.operation-types.index') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
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
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('inventory.name') }}"
                           class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent {{ $errors->has('name') ? 'border-red-400' : 'border-gray-200' }}">
                </div>

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.company') }}</label>
                            <x-relation-dropdown table="companies" field="name" name="company_id" relation="many2one" :selected="old('company_id', $defaultCompanyId ?? null)" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.operation_type') }}</label>
                            <select name="code" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                @foreach(['incoming' => __('inventory.op_type_incoming'), 'outgoing' => __('inventory.op_type_outgoing'), 'internal' => __('inventory.op_type_internal')] as $k => $v)
                                <option value="{{ $k }}" {{ old('code', 'incoming') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.sequence_prefix') }}</label>
                            <input type="text" name="sequence_prefix" value="{{ old('sequence_prefix') }}" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5" placeholder="WH/IN/">
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            {{-- This row was labelled `inventory.reference` ("Reference"),
                                 which made no sense over a 1-10 number input. The
                                 field is the digit-count for the sequence — corrected. --}}
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.sequence_padding') }}</label>
                            <input type="number" name="sequence_padding" value="{{ old('sequence_padding', 5) }}" min="1" max="10" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                        </div>
                    </div>
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.warehouse') }}</label>
                            <x-relation-dropdown table="inventory_warehouses" field="name" name="warehouse_id" relation="many2one" :selected="old('warehouse_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.from') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="default_location_src_id" relation="many2one" :selected="old('default_location_src_id')" class="flex-1" compact />
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.to') }}</label>
                            <x-relation-dropdown table="inventory_locations" field="complete_name" name="default_location_dest_id" relation="many2one" :selected="old('default_location_dest_id')" class="flex-1" compact />
                        </div>
                        {{-- `reservation_method` was a dropdown labelled "Action"
                             that posted a value with no DB column, no fillable
                             entry, and no validation rule. Pure dead UI. The
                             reservation behaviour in PickingService is fixed
                             (release-then-reserve via Check Availability); add
                             this back when the picking flow actually branches
                             on the picked method. --}}
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
