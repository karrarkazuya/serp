@extends('layouts.app')
@section('title', __('inventory.replenishment'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\ReorderRule::class)
        <a href="{{ route('inventory.replenishment.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new_reorder_rule') }}</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.replenishment') }}</span>
        <x-search :model="\App\Models\Inventory\ReorderRule::class" :action="route('inventory.replenishment.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($rules) && $rules->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $rules->firstItem() }}-{{ $rules->lastItem() }} / {{ $rules->total() }}</span>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_reorder_rules') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.product') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.location') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.min_qty') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.max_qty') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.route') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-center">{{ __('inventory.col_status') }}</th>
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
                <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $rule->product?->name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->location?->complete_name }}</td>
                <td class="px-3 py-2 text-sm text-end {{ $rule->needsReplenishment() ? 'text-red-600 font-semibold' : 'text-gray-800' }}">{{ number_format($rule->qty_on_hand, 2) }}</td>
                <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ number_format($rule->qty_min, 2) }}</td>
                <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ number_format($rule->qty_max, 2) }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->route?->name }}</td>
                <td class="px-3 py-2 text-center">
                    @if($rule->needsReplenishment())
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ __('inventory.to_replenish') }}</span>
                    @else
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('inventory.status_ok') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2">
                    <div class="flex items-center gap-2 justify-end">
                        @can('update', $rule)
                        <a href="{{ route('inventory.replenishment.edit', $rule) }}" class="px-2 py-1 text-xs text-gray-600 border border-gray-200 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                        @endcan
                        <form method="POST" action="{{ route('inventory.replenishment.replenish', $rule) }}">
                            @csrf
                            <button class="px-2 py-1 text-xs font-semibold text-purple-700 border border-purple-200 rounded hover:bg-purple-50">{{ __('inventory.replenish') }}</button>
                        </form>
                        <div x-data="{ confirming: false }">
                            <form method="POST" action="{{ route('inventory.replenishment.delete', $rule) }}">
                                @csrf @method('DELETE')
                                <button type="button" x-show="!confirming" @click="confirming = true" class="px-2 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50">{{ __('inventory.delete') }}</button>
                                <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                    <button type="submit" class="px-1.5 py-0.5 text-xs bg-red-600 text-white rounded">{{ __('inventory.yes') }}</button>
                                    <button type="button" @click="confirming = false" class="px-1.5 py-0.5 text-xs text-gray-500 border rounded">{{ __('inventory.no') }}</button>
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
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_reorder_rules') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$rules" empty-text="{{ __('inventory.no_reorder_rules') }}">
        <x-slot:columns>
            <th class="px-4 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.product') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.location') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_on_hand') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.min_qty') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.max_qty') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.route') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-center">{{ __('inventory.col_status') }}</th>
            <th class="px-3 py-2"></th>
        </x-slot:columns>
        @foreach($rules as $rule)
        <tr class="hover:bg-purple-50/30">
            <td class="px-4 py-2 text-sm text-gray-900 font-medium">{{ $rule->product?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->location?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-end {{ $rule->needsReplenishment() ? 'text-red-600 font-semibold' : 'text-gray-800' }}">{{ number_format($rule->qty_on_hand, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ number_format($rule->qty_min, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ number_format($rule->qty_max, 2) }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $rule->route?->name }}</td>
            <td class="px-3 py-2 text-center">
                @if($rule->needsReplenishment())
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-700">{{ __('inventory.to_replenish') }}</span>
                @else
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">{{ __('inventory.status_ok') }}</span>
                @endif
            </td>
            <td class="px-3 py-2">
                <div class="flex items-center gap-2 justify-end">
                    @can('update', $rule)
                    <a href="{{ route('inventory.replenishment.edit', $rule) }}" class="px-2 py-1 text-xs text-gray-600 border border-gray-200 rounded hover:bg-gray-50">{{ __('inventory.edit') }}</a>
                    @endcan
                    <form method="POST" action="{{ route('inventory.replenishment.replenish', $rule) }}">
                        @csrf
                        <button class="px-2 py-1 text-xs font-semibold text-purple-700 border border-purple-200 rounded hover:bg-purple-50">{{ __('inventory.replenish') }}</button>
                    </form>
                    <div x-data="{ confirming: false }">
                        <form method="POST" action="{{ route('inventory.replenishment.delete', $rule) }}">
                            @csrf @method('DELETE')
                            <button type="button" x-show="!confirming" @click="confirming = true" class="px-2 py-1 text-xs text-red-600 border border-red-200 rounded hover:bg-red-50">{{ __('inventory.delete') }}</button>
                            <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                <button type="submit" class="px-1.5 py-0.5 text-xs bg-red-600 text-white rounded">{{ __('inventory.yes') }}</button>
                                <button type="button" @click="confirming = false" class="px-1.5 py-0.5 text-xs text-gray-500 border rounded">{{ __('inventory.no') }}</button>
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
