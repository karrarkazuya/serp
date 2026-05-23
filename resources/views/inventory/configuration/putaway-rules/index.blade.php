@extends('layouts.app')
@section('title', 'Putaway Rules')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\PutawayRule::class)
        <a href="{{ route('inventory.config.putaway-rules.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">Putaway Rules</span>
        <x-search :model="\App\Models\Inventory\PutawayRule::class" :action="route('inventory.config.putaway-rules.index')" />
    </div>

    <x-list :paginator="$putawayRules" empty-text="No putaway rules found.">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">When Arriving In</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Product / Category</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Store To</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Seq.</th>
            <th class="px-3 py-2"></th>
        </x-slot:columns>
        @foreach($putawayRules as $rule)
        <tr class="hover:bg-purple-50/30">
            <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $rule->location?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->product?->name ?? $rule->productCategory?->name ?? 'All' }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->fixedLocation?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600 text-right">{{ $rule->sequence }}</td>
            <td class="px-3 py-2 text-right">
                <div class="flex items-center gap-2 justify-end">
                    <a href="{{ route('inventory.config.putaway-rules.edit', $rule) }}" class="text-xs text-purple-600 hover:underline">Edit</a>
                    <div x-data="{ confirming: false }">
                        <form method="POST" action="{{ route('inventory.config.putaway-rules.delete', $rule) }}">
                            @csrf @method('DELETE')
                            <button type="button" x-show="!confirming" @click="confirming = true" class="text-xs text-red-600 hover:underline">Delete</button>
                            <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                <button type="submit" class="text-xs bg-red-600 text-white px-1.5 py-0.5 rounded">Yes</button>
                                <button type="button" @click="confirming = false" class="text-xs text-gray-500 border px-1.5 py-0.5 rounded">No</button>
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
