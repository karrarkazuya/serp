@extends('layouts.app')
@section('title', __('inventory.putaway_rules'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\PutawayRule::class)
        <a href="{{ route('inventory.config.putaway-rules.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.putaway_rules') }}</span>
        <x-search :model="\App\Models\Inventory\PutawayRule::class" :action="route('inventory.config.putaway-rules.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($putawayRules) && $putawayRules->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $putawayRules->firstItem() }}-{{ $putawayRules->lastItem() }} / {{ $putawayRules->total() }}</span>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_putaway_rules') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_arriving_in') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_product_cat') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_store_to') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_seq') }}</th>
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $rule)
            <tr x-show="open" class="hover:bg-purple-50/30">
                <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $rule->location?->complete_name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->product?->name ?? $rule->productCategory?->name ?? __('inventory.all') }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->fixedLocation?->complete_name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600 text-end">{{ $rule->sequence }}</td>
                <td class="px-3 py-2 text-end">
                    <div class="flex items-center gap-2 justify-end">
                        <a href="{{ route('inventory.config.putaway-rules.edit', $rule) }}" class="text-xs text-purple-600 hover:underline">{{ __('inventory.edit') }}</a>
                        <div x-data="{ confirming: false }">
                            <form method="POST" action="{{ route('inventory.config.putaway-rules.delete', $rule) }}">
                                @csrf @method('DELETE')
                                <button type="button" x-show="!confirming" @click="confirming = true" class="text-xs text-red-600 hover:underline">{{ __('inventory.delete') }}</button>
                                <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                    <button type="submit" class="text-xs bg-red-600 text-white px-1.5 py-0.5 rounded">{{ __('inventory.yes') }}</button>
                                    <button type="button" @click="confirming = false" class="text-xs text-gray-500 border px-1.5 py-0.5 rounded">{{ __('inventory.no') }}</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_putaway_rules') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$putawayRules" empty-text="{{ __('inventory.no_putaway_rules') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_arriving_in') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_product_cat') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_store_to') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_seq') }}</th>
            <th class="px-3 py-2"></th>
        </x-slot:columns>
        @foreach($putawayRules as $rule)
        <tr class="hover:bg-purple-50/30">
            <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $rule->location?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->product?->name ?? $rule->productCategory?->name ?? __('inventory.all') }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->fixedLocation?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600 text-end">{{ $rule->sequence }}</td>
            <td class="px-3 py-2 text-end">
                <div class="flex items-center gap-2 justify-end">
                    <a href="{{ route('inventory.config.putaway-rules.edit', $rule) }}" class="text-xs text-purple-600 hover:underline">{{ __('inventory.edit') }}</a>
                    <div x-data="{ confirming: false }">
                        <form method="POST" action="{{ route('inventory.config.putaway-rules.delete', $rule) }}">
                            @csrf @method('DELETE')
                            <button type="button" x-show="!confirming" @click="confirming = true" class="text-xs text-red-600 hover:underline">{{ __('inventory.delete') }}</button>
                            <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                <button type="submit" class="text-xs bg-red-600 text-white px-1.5 py-0.5 rounded">{{ __('inventory.yes') }}</button>
                                <button type="button" @click="confirming = false" class="text-xs text-gray-500 border px-1.5 py-0.5 rounded">{{ __('inventory.no') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
