@extends('layouts.app')
@section('title', $scrapOrder->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.scrap.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.scrap.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.scrap.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('inventory.scrap_orders') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $scrapOrder->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $scrapOrder)
                @if($scrapOrder->isDraft())
                <form method="POST" action="{{ route('inventory.scrap.validate', $scrapOrder) }}">
                    @csrf
                    <button class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded">{{ __('inventory.validate') }}</button>
                </form>
                @endif
                @endcan
                @can('delete', $scrapOrder)
                @if($scrapOrder->isDraft())
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.scrap.delete', $scrapOrder) }}">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">{{ __('inventory.delete') }}</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">{{ __('inventory.are_you_sure') }}</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('inventory.yes') }}</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">{{ __('inventory.cancel') }}</button>
                        </div>
                    </form>
                </div>
                @endif
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-3 mb-6">
                <h1 class="text-2xl font-bold text-gray-900">{{ $scrapOrder->name }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $scrapOrder->state_color }}-100 text-{{ $scrapOrder->state_color }}-700">
                    {{ $scrapOrder->state_label }}
                </span>
            </div>
            <div class="grid grid-cols-2 gap-x-8">
                <div>
                    @foreach([
                        [__('inventory.product'), $scrapOrder->product?->name],
                        [__('inventory.lot_serial'), $scrapOrder->lot?->name],
                        [__('inventory.quantity'), number_format($scrapOrder->scrap_qty, 2) . ' ' . $scrapOrder->product?->uom?->name],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                    </div>
                    @endforeach
                </div>
                <div>
                    @foreach([
                        [__('inventory.source_location'), $scrapOrder->location?->complete_name],
                        [__('inventory.scrap_location'), $scrapOrder->scrapLocation?->complete_name],
                        [__('inventory.date'), $scrapOrder->date_done?->format('M d, Y')],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $value ?: '-' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter model-type="App\Models\Inventory\ScrapOrder" :model-id="$scrapOrder->id"
                :can-comment="auth()->user()->can('comment', $scrapOrder)" />
        </div>
    </div>
</div>
@endsection
