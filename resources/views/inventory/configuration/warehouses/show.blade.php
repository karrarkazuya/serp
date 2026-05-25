@extends('layouts.app')
@section('title', $warehouse->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\Warehouse::class)
        @php $newHref = route('inventory.config.warehouses.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.config.warehouses.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.config.warehouses.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.config.warehouses.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.warehouses') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $warehouse->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $warehouse)
                <a href="{{ route('inventory.config.warehouses.edit', $warehouse) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                @if($warehouse->active)
                <form method="POST" action="{{ route('inventory.config.warehouses.archive', $warehouse) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-amber-700 border border-amber-200 rounded hover:bg-amber-50">{{ __('inventory.archive') }}</button>
                </form>
                @else
                <form method="POST" action="{{ route('inventory.config.warehouses.unarchive', $warehouse) }}">
                    @csrf @method('PATCH')
                    <button class="px-3 py-1.5 text-sm text-green-700 border border-green-200 rounded hover:bg-green-50">{{ __('inventory.restore') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $warehouse)
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.config.warehouses.delete', $warehouse) }}">
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
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
            @if(!$warehouse->active)
            <div class="mb-4"><div class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('inventory.warehouse_archived') }}</div></div>
            @endif
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $warehouse->name }}</h1>
            @foreach([
                [__('inventory.short_name'), $warehouse->code],
                [__('inventory.company'), $warehouse->company?->name],
                [__('inventory.location'), $warehouse->stockLocation?->complete_name],
            ] as [$label, $value])
            <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
            </div>
            @endforeach

            @if($warehouse->operationTypes->isNotEmpty())
            <div class="mt-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('inventory.operation_types') }}</h3>
                <div class="grid grid-cols-3 gap-3">
                    @foreach($warehouse->operationTypes as $opType)
                    <a href="{{ route('inventory.config.operation-types.show', $opType) }}"
                       class="p-3 rounded-lg border border-gray-200 hover:border-purple-300 hover:bg-purple-50/50 transition-all">
                        <p class="text-sm font-medium text-gray-900">{{ $opType->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5">{{ $opType->code_label }}</p>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif
        </div>

        <div class="bg-white mx-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Inventory\Warehouse"
                :model-id="$warehouse->id"
                :can-comment="auth()->user()->can('update', $warehouse)"
            />
        </div>
    </div>
</div>
@endsection
