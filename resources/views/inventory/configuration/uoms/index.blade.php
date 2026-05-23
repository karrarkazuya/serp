@extends('layouts.app')
@section('title', __('inventory.units_of_measure'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <a href="{{ route('inventory.config.uoms.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.units_of_measure') }}</span>
        <x-search :model="\App\Models\Inventory\Uom::class" :action="route('inventory.config.uoms.index')" />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} {{ __('inventory.records') }}</span>
            @elseif(isset($uoms) && $uoms->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $uoms->firstItem() }}-{{ $uoms->lastItem() }} / {{ $uoms->total() }}</span>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_uoms') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_name')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.category') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_type') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_ratio') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_rounding') }}</th>
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
            @foreach($group['items'] as $uom)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.uoms.show', $uom) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $uom->name }}@if($uom->symbol) <span class="text-gray-400 text-xs">({{ $uom->symbol }})</span>@endif</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $uom->category?->name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ ucfirst($uom->uom_type) }}</td>
                <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ $uom->ratio }}</td>
                <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ $uom->rounding }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_uoms') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$uoms" empty-text="{{ __('inventory.no_uoms') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_name')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.category') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_type') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_ratio') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-end">{{ __('inventory.col_rounding') }}</th>
        </x-slot:columns>
        @foreach($uoms as $uom)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.config.uoms.show', $uom) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $uom->name }}@if($uom->symbol) <span class="text-gray-400 text-xs">({{ $uom->symbol }})</span>@endif</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $uom->category?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ ucfirst($uom->uom_type) }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ $uom->ratio }}</td>
            <td class="px-3 py-2 text-sm text-gray-800 text-end">{{ $uom->rounding }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
