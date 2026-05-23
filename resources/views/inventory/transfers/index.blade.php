@extends('layouts.app')
@section('title', 'Transfers')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Picking::class)
        <a href="{{ route('inventory.transfers.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">New</a>
        @endcan

        <span class="text-xl font-semibold text-gray-700 shrink-0">Transfers</span>

        <x-search :model="\App\Models\Inventory\Picking::class" :action="route('inventory.transfers.index')" />

        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if($pickings->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $pickings->firstItem() }}-{{ $pickings->lastItem() }} / {{ $pickings->total() }}</span>
            @endif
            <div class="flex items-center gap-1">
                @if($pickings->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $pickings->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($pickings->hasMorePages())
                    <a href="{{ $pickings->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    {{-- State filter tabs --}}
    <div class="flex items-center gap-1 px-4 py-2 border-b border-gray-200 bg-white text-sm">
        @foreach([
            '' => 'All',
            'draft' => 'Draft',
            'confirmed' => 'Waiting',
            'assigned' => 'Ready',
            'done' => 'Done',
            'cancelled' => 'Cancelled',
        ] as $state => $label)
        <a href="{{ route('inventory.transfers.index', array_merge(request()->except('state','page'), ['state' => $state])) }}"
           class="px-3 py-1 rounded text-xs font-medium {{ request('state', '') === $state ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:text-gray-700' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    <x-list :paginator="$pickings" empty-text="No transfers found.">
        <x-slot:columns>
            <x-sortable-th column="name" label="Reference" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">From</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">To</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Operation Type</th>
            <x-sortable-th column="scheduled_date" label="Scheduled Date" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Status</th>
        </x-slot:columns>
        @foreach($pickings as $picking)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.transfers.show', $picking) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $picking->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->srcLocation?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->destLocation?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->operationType?->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->scheduled_date?->format('M d, Y') }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $picking->state_color }}-100 text-{{ $picking->state_color }}-700">
                    {{ $picking->state_label }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
