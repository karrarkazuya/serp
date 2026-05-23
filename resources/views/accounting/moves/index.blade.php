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
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->can('create', \App\Models\Accounting\AccountMove::class) ? route('accounting.moves.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Journal Entries</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
    <x-search
        :model="\App\Models\Accounting\AccountMove::class"
        :action="route('accounting.moves.index')"
        :quick-filters="$quickFilters"
    />
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
</div>
@endsection
