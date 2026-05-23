@extends('layouts.app')
@section('title', 'Replenishment')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\ReorderRule::class)
        <a href="{{ route('inventory.replenishment.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New Reorder Rule</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Replenishment</span>
        <x-search :model="\App\Models\Inventory\ReorderRule::class" :action="route('inventory.replenishment.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($rules->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $rules->firstItem() }}-{{ $rules->lastItem() }} / {{ $rules->total() }}</span>
            @endif
        </div>
    </div>

    <x-list :paginator="$rules" empty-text="No reorder rules found.">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Product</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Location</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">On Hand</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Min Qty</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Max Qty</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Route</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-center">Status</th>
            <th class="px-3 py-2"></th>
        </x-slot:columns>
        @foreach($rules as $rule)
        <tr class="hover:bg-purple-50/30">
            <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $rule->product?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->location?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-right {{ $rule->needsReplenishment() ? 'text-red-600 font-semibold' : 'text-gray-800' }}">{{ number_format($rule->qty_on_hand, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-right">{{ number_format($rule->qty_min, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-right">{{ number_format($rule->qty_max, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->route?->name }}</td>
            <td class="px-3 py-2 text-center">
                @if($rule->needsReplenishment())
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">Needs Replenishment</span>
                @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">OK</span>
                @endif
            </td>
            <td class="px-3 py-2">
                <div class="flex items-center gap-2 justify-end">
                    @can('update', $rule)
                    <a href="{{ route('inventory.replenishment.edit', $rule) }}" class="px-2 py-1 text-xs text-gray-600 border border-gray-200 rounded hover:bg-gray-50">Edit</a>
                    @endcan
                    <form method="POST" action="{{ route('inventory.replenishment.replenish', $rule) }}">
                        @csrf
                        <button class="px-2 py-1 text-xs font-semibold text-purple-700 border border-purple-200 rounded hover:bg-purple-50">Replenish</button>
                    </form>
                    <div x-data="{ confirming: false }">
                        <form method="POST" action="{{ route('inventory.replenishment.delete', $rule) }}">
                            @csrf @method('DELETE')
                            <button type="button" x-show="!confirming" @click="confirming = true" class="px-2 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50">Delete</button>
                            <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                <button type="submit" class="px-1.5 py-0.5 text-xs bg-red-600 text-white rounded">Yes</button>
                                <button type="button" @click="confirming = false" class="px-1.5 py-0.5 text-xs text-gray-500 border rounded">No</button>
                            </div>
                        </form>
                    </div>
                </div>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
