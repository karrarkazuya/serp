@extends('layouts.app')
@section('title', $lot->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    @can('create', \App\Models\Inventory\Lot::class)
        @php $newHref = route('inventory.lots.create'); @endphp
    @endcan
    <x-toolbar
        :new-href="$newHref ?? null"
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.lots.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.lots.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.lots.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Lots / Serial Numbers</a>
            <span class="text-sm font-semibold text-gray-800">{{ $lot->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $lot)
                <a href="{{ route('inventory.lots.edit', $lot) }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">Edit</a>
                @endcan
                @can('delete', $lot)
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.lots.delete', $lot) }}">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">Are you sure?</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">Yes</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">Cancel</button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-6">{{ $lot->name }}</h1>
            <div class="grid grid-cols-2 gap-x-8">
                <div>
                    @foreach([
                        ['Product', $lot->product?->name],
                        ['Company', $lot->company?->name],
                        ['Manufacture Date', $lot->manufacture_date?->format('M d, Y')],
                        ['Expiration Date', $lot->expiration_date?->format('M d, Y')],
                    ] as [$label, $value])
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">{{ $label }}</span>
                        <span class="flex-1 text-sm {{ $label === 'Expiration Date' && $lot->isExpired() ? 'text-red-600 font-medium' : 'text-gray-800' }}">{{ $value ?: '-' }}</span>
                    </div>
                    @endforeach
                </div>
                <div>
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">On Hand Qty</span>
                        <span class="flex-1 text-sm font-semibold text-gray-900">{{ number_format($lot->getOnHandQty(), 2) }}</span>
                    </div>
                    @if($lot->description)
                    <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                        <span class="w-40 shrink-0 text-sm text-gray-500">Description</span>
                        <span class="flex-1 text-sm text-gray-800">{{ $lot->description }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter model-type="App\Models\Inventory\Lot" :model-id="$lot->id"
                :can-comment="auth()->user()->can('comment', $lot)" />
        </div>
    </div>
</div>
@endsection
