@extends('layouts.app')
@section('title', __('inventory.physical_inventory'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\InventoryAdjustment::class)
        <a href="{{ route('inventory.adjustments.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        @endcan
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.physical_inventory') }}</span>
        <x-search :model="\App\Models\Inventory\InventoryAdjustment::class" :action="route('inventory.adjustments.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($adjustments) && $adjustments->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $adjustments->firstItem() }}-{{ $adjustments->lastItem() }} / {{ $adjustments->total() }}</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($adjustments) && $adjustments->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @elseif(isset($adjustments))
                    <a href="{{ $adjustments->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($adjustments) && $adjustments->hasMorePages())
                    <a href="{{ $adjustments->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @elseif(isset($adjustments))
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_physical_inventory') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_reference')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.company') }}</th>
            <x-sortable-th column="date" :label="__('inventory.col_date')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_status') }}</th>
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
            @foreach($group['items'] as $adj)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.adjustments.show', $adj) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $adj->name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $adj->company?->name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $adj->date?->format('M d, Y') }}</td>
                <td class="px-3 py-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $adj->state_color }}-100 text-{{ $adj->state_color }}-700">
                        {{ $adj->state_label }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_physical_inventory') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$adjustments" empty-text="{{ __('inventory.no_physical_inventory') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_reference')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.company') }}</th>
            <x-sortable-th column="date" :label="__('inventory.col_date')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_status') }}</th>
        </x-slot:columns>
        @foreach($adjustments as $adj)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.adjustments.show', $adj) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $adj->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $adj->company?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $adj->date?->format('M d, Y') }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $adj->state_color }}-100 text-{{ $adj->state_color }}-700">
                    {{ $adj->state_label }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
