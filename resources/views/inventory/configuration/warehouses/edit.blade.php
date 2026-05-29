@extends('layouts.app')
@section('title', __('inventory.edit') . ': ' . $warehouse->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <form method="POST" action="{{ route('inventory.config.warehouses.update', $warehouse) }}" class="flex flex-col h-full">
        @csrf @method('PUT')
        <x-toolbar>
            <x-slot:breadcrumb>
                <a href="{{ route('inventory.config.warehouses.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.warehouses') }}</a>
                <a href="{{ route('inventory.config.warehouses.show', $warehouse) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $warehouse->name }}</a>
                <span class="text-sm font-semibold text-gray-800">{{ __('inventory.edit') }}</span>
            </x-slot:breadcrumb>
            <x-slot:actions>
                <div class="flex items-center gap-2">
                    <a href="{{ route('inventory.config.warehouses.show', $warehouse) }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.discard') }}</a>
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
                    <input type="text" name="name" value="{{ old('name', $warehouse->name) }}" required
                           class="w-full text-2xl font-bold text-gray-900 border-0 border-b-2 focus:outline-none focus:border-purple-500 pb-1 bg-transparent border-gray-200">
                </div>

                <div class="grid grid-cols-2 gap-x-8">
                    <div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.short_name') }}</label>
                            {{-- short_name is fixed post-create: it's part of the
                                 unique (company_id, short_name) key and drives
                                 the OperationType sequence prefix. Showing it as
                                 read-only is honest; the previous form posted
                                 `code` (silently dropped) and offered a fake edit. --}}
                            <span class="flex-1 text-sm text-gray-800">{{ $warehouse->short_name }}</span>
                        </div>
                    </div>
                    <div>
                        {{-- reception_steps + delivery_steps were missing — the
                             update request validates both as required, so any
                             save from this form returned 422. --}}
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.reception_steps') }}</label>
                            <select name="reception_steps" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                @foreach(['one_step' => __('inventory.reception_one_step'), 'two_steps' => __('inventory.reception_two_steps'), 'three_steps' => __('inventory.reception_three_steps')] as $k => $v)
                                <option value="{{ $k }}" @selected(old('reception_steps', $warehouse->reception_steps) === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('inventory.delivery_steps') }}</label>
                            <select name="delivery_steps" class="flex-1 text-sm bg-transparent border-0 focus:outline-none px-0 py-0.5">
                                @foreach(['one_step' => __('inventory.delivery_one_step'), 'two_steps' => __('inventory.delivery_two_steps'), 'three_steps' => __('inventory.delivery_three_steps')] as $k => $v)
                                <option value="{{ $k }}" @selected(old('delivery_steps', $warehouse->delivery_steps) === $k)>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
