@extends('layouts.app')
@section('title', 'Journal Entries')

@php
    $quickFilters = [
        ['label' => 'Draft',     'params' => ['state' => 'draft'],     'url' => route('accounting.moves.index', array_merge(request()->except('page','state'), ['state' => 'draft']))],
        ['label' => 'Posted',    'params' => ['state' => 'posted'],    'url' => route('accounting.moves.index', array_merge(request()->except('page','state'), ['state' => 'posted']))],
        ['label' => 'Cancelled', 'params' => ['state' => 'cancelled'], 'url' => route('accounting.moves.index', array_merge(request()->except('page','state'), ['state' => 'cancelled']))],
        ['label' => 'All',       'params' => ['state' => ''],          'url' => route('accounting.moves.index', array_merge(request()->except('page','state'), ['state' => '']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Accounting\AccountMove::class)
        <a href="{{ route('accounting.moves.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded shadow-sm shrink-0">New</a>
        @endcan
        <div class="flex flex-col leading-tight shrink-0">
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Journal Entries</span>
        </div>

        <x-search
            :model="\App\Models\Accounting\AccountMove::class"
            :action="route('accounting.moves.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            <span class="text-sm font-semibold text-gray-600">
                {{ $moves->total() > 0 ? $moves->firstItem().'-'.$moves->lastItem() : 0 }} / {{ $moves->total() }}
            </span>
        </div>
    </div>

    <x-list :paginator="$moves" empty-text="No journal entries yet.">
        <x-slot:columns>
            <x-sortable-th column="date" label="Date"    class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" label="Number"  class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Journal</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Partner</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Reference</th>
            <x-sortable-th column="amount_total" label="Amount" class="px-3 py-2 text-right" />
            <x-sortable-th column="state" label="State" class="px-3 py-2" />
        </x-slot:columns>

        @foreach($moves as $move)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.moves.show', $move) }}'">
            <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($move->date)->format('Y-m-d') }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">{{ $move->name ?: '(Draft)' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $move->journal?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $move->partner?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $move->ref ?: '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $move->amount_total, 2) }}</td>
            <td class="px-3 py-2">
                @php
                    $color = match($move->state) {
                        'posted'    => 'bg-green-100 text-green-700',
                        'draft'     => 'bg-amber-100 text-amber-700',
                        'cancelled' => 'bg-gray-200 text-gray-600',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $color }}">{{ $move->state_label }}</span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
