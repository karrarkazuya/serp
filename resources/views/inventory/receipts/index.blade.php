@extends('layouts.app')
@section('title', __('inventory.receipts'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Inventory\Picking::class)
        <a href="{{ route('inventory.receipts.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">{{ __('inventory.new') }}</a>
        @endcan

        <span class="text-xl font-semibold text-gray-700 shrink-0">{{ __('inventory.receipts') }}</span>

        <x-search :model="\App\Models\Inventory\Picking::class" :action="route('inventory.receipts.index')" />

        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600">{{ $groups->sum('count') }} {{ __('inventory.receipts') }}</span>
            @elseif(isset($pickings) && $pickings->total() > 0)
                <span class="text-sm font-semibold text-gray-600">{{ $pickings->firstItem() }}-{{ $pickings->lastItem() }} / {{ $pickings->total() }}</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(!isset($pickings) || $pickings->onFirstPage())
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $pickings->previousPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($pickings) && $pickings->hasMorePages())
                    <a href="{{ $pickings->nextPageUrl() }}" class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-9 h-9 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    {{-- State filter tabs --}}
    <div class="flex items-center gap-1 px-4 py-2 border-b border-gray-200 bg-white text-sm">
        @foreach([
            '' => __('inventory.all'),
            'draft' => __('inventory.status_draft'),
            'confirmed' => __('inventory.status_waiting'),
            'assigned' => __('inventory.status_ready'),
            'done' => __('inventory.status_done'),
            'cancelled' => __('inventory.status_cancelled'),
        ] as $state => $label)
        <a href="{{ route('inventory.receipts.index', array_merge(request()->except('state', 'page'), ['state' => $state])) }}"
           class="px-3 py-1 rounded text-xs font-medium {{ request('state', '') === $state ? 'bg-purple-100 text-purple-700' : 'text-gray-500 hover:text-gray-700' }}">
            {{ $label }}
        </a>
        @endforeach
    </div>

    @if(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('inventory.no_receipts') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_reference')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_vendor') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_from') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_to') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_source_doc') }}</th>
            <x-sortable-th column="scheduled_date" :label="__('inventory.col_scheduled_date')" class="px-3 py-2" />
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
            @foreach($group['items'] as $picking)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.transfers.show', $picking) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $picking->name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->partner?->name ?? '-' }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->srcLocation?->complete_name }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->destLocation?->complete_name }}</td>
                <td class="px-3 py-2 text-sm text-gray-500">{{ $picking->origin ?? '-' }}</td>
                <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->scheduled_date?->format('M d, Y') }}</td>
                <td class="px-3 py-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $picking->state_color }}-100 text-{{ $picking->state_color }}-700">
                        {{ $picking->state_label }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('inventory.no_receipts') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$pickings" empty-text="{{ __('inventory.no_receipts') }}">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('inventory.col_reference')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_vendor') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_from') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_to') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_source_doc') }}</th>
            <x-sortable-th column="scheduled_date" :label="__('inventory.col_scheduled_date')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-start">{{ __('inventory.col_status') }}</th>
        </x-slot:columns>
        @foreach($pickings as $picking)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('inventory.transfers.show', $picking) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $picking->name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->partner?->name ?? '-' }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->srcLocation?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->destLocation?->complete_name }}</td>
            <td class="px-3 py-2 text-sm text-gray-500">{{ $picking->origin ?? '-' }}</td>
            <td class="px-3 py-2 text-sm text-gray-600">{{ $picking->scheduled_date?->format('M d, Y') }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-{{ $picking->state_color }}-100 text-{{ $picking->state_color }}-700">
                    {{ $picking->state_label }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
