@extends('layouts.app')
@section('title', $inventoryAdjustment->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar
        :position="$recordPosition ?: null"
        :total="$recordTotal ?? null"
        :prev-href="$prevId ? route('inventory.adjustments.show', $prevId) : null"
        :next-href="$nextId ? route('inventory.adjustments.show', $nextId) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('inventory.adjustments.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Physical Inventory</a>
            <span class="text-sm font-semibold text-gray-800">{{ $inventoryAdjustment->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                @can('update', $inventoryAdjustment)
                @if($inventoryAdjustment->isDraft())
                <form method="POST" action="{{ route('inventory.adjustments.start', $inventoryAdjustment) }}">
                    @csrf
                    <button class="px-3 py-1.5 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded">Start Inventory</button>
                </form>
                @elseif($inventoryAdjustment->isInProgress())
                <form method="POST" action="{{ route('inventory.adjustments.validate', $inventoryAdjustment) }}" x-data="{ confirming: false }">
                    @csrf
                    <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm font-semibold text-white bg-green-600 hover:bg-green-700 rounded">Validate Inventory</button>
                    <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                        <span class="text-xs text-gray-600">Post adjustments?</span>
                        <button type="submit" class="px-2 py-1 text-xs bg-green-600 text-white rounded">Validate</button>
                        <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">Cancel</button>
                    </div>
                </form>
                @endif
                @endcan
                @can('delete', $inventoryAdjustment)
                @if($inventoryAdjustment->isDraft())
                <div x-data="{ confirming: false }">
                    <form method="POST" action="{{ route('inventory.adjustments.delete', $inventoryAdjustment) }}">
                        @csrf @method('DELETE')
                        <button type="button" x-show="!confirming" @click="confirming = true" class="px-3 py-1.5 text-sm text-red-700 border border-red-200 rounded hover:bg-red-50">Delete</button>
                        <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                            <span class="text-xs text-red-600">Are you sure?</span>
                            <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">Yes</button>
                            <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border rounded">Cancel</button>
                        </div>
                    </form>
                </div>
                @endif
                @endcan
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="p-6 border-b border-gray-100">
                <div class="flex items-center gap-3 mb-4">
                    <h1 class="text-xl font-bold text-gray-900">{{ $inventoryAdjustment->name }}</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $inventoryAdjustment->state_color }}-100 text-{{ $inventoryAdjustment->state_color }}-700">
                        {{ $inventoryAdjustment->state_label }}
                    </span>
                </div>
                <div class="grid grid-cols-3 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Company: </span>
                        <span class="text-gray-900">{{ $inventoryAdjustment->company?->name }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Date: </span>
                        <span class="text-gray-900">{{ $inventoryAdjustment->date?->format('M d, Y') }}</span>
                    </div>
                    @if($inventoryAdjustment->note)
                    <div>
                        <span class="text-gray-500">Note: </span>
                        <span class="text-gray-900">{{ $inventoryAdjustment->note }}</span>
                    </div>
                    @endif
                </div>
            </div>

            {{-- Lines --}}
            @if($inventoryAdjustment->lines->isNotEmpty())
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200">
                        <tr>
                            <th class="px-6 py-2 text-left text-xs font-semibold text-gray-500">Product</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Location</th>
                            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500">Lot</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Theoretical</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Counted</th>
                            <th class="px-3 py-2 text-right text-xs font-semibold text-gray-500">Difference</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($inventoryAdjustment->lines as $line)
                        <tr class="border-b border-gray-100 {{ $line->difference_qty != 0 ? 'bg-amber-50/40' : '' }}">
                            <td class="px-6 py-2 text-gray-800 font-medium">{{ $line->product?->name }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $line->location?->complete_name }}</td>
                            <td class="px-3 py-2 text-gray-600">{{ $line->lot?->name ?? '-' }}</td>
                            <td class="px-3 py-2 text-right text-gray-600">{{ number_format($line->theoretical_qty, 2) }}</td>
                            <td class="px-3 py-2 text-right">
                                @if($inventoryAdjustment->isInProgress())
                                <form method="POST" action="{{ route('inventory.adjustments.update-line', [$inventoryAdjustment, $line]) }}">
                                    @csrf
                                    <input type="number" name="inventory_qty" value="{{ $line->inventory_qty }}"
                                           step="0.001" min="0" onchange="this.form.submit()"
                                           class="w-20 text-right text-sm border border-gray-200 rounded px-1 py-0.5 focus:border-purple-400 focus:outline-none">
                                </form>
                                @else
                                {{ number_format($line->inventory_qty, 2) }}
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-medium {{ $line->difference_qty > 0 ? 'text-green-600' : ($line->difference_qty < 0 ? 'text-red-600' : 'text-gray-400') }}">
                                {{ $line->difference_qty > 0 ? '+' : '' }}{{ number_format($line->difference_qty, 2) }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @elseif($inventoryAdjustment->isDraft())
            <div class="px-6 py-12 text-center text-gray-400 text-sm">
                Click "Start Inventory" to load current stock quantities.
            </div>
            @endif
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter model-type="App\Models\Inventory\InventoryAdjustment" :model-id="$inventoryAdjustment->id"
                :can-comment="auth()->user()->can('comment', $inventoryAdjustment)" />
        </div>
    </div>
</div>
@endsection
