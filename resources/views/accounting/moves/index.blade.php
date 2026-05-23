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
        <x-slot:search>
            <x-search
                :model="\App\Models\Accounting\AccountMove::class"
                :action="route('accounting.moves.index')"
                :quick-filters="$quickFilters"
            />
        </x-slot:search>
    </x-toolbar>

    @can('export', \App\Models\Accounting\AccountMove::class)
    <x-export
        :fields="config('exportable')['accounting.moves']['fields'] ?? []"
        :export-url="route('export')"
        model-key="accounting.moves"
    />
    @endcan

    @php
        $moveStateColor = fn($state) => match($state) {
            'posted'    => 'bg-green-100 text-green-700',
            'draft'     => 'bg-amber-100 text-amber-700',
            'cancelled' => 'bg-gray-200 text-gray-600',
            default     => 'bg-gray-100 text-gray-600',
        };
    @endphp

    @if(isset($groups))
    <x-list :grouped="true" empty-text="No journal entries yet.">
        <x-slot:columns>
            <x-sortable-th column="date" label="Date"    class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" label="Number"  class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Journal</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Partner</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Reference</th>
            <x-sortable-th column="amount_total" label="Amount" class="px-3 py-2 text-right" />
            <x-sortable-th column="state" label="State" class="px-3 py-2" />
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
            @foreach($group['items'] as $move)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.moves.show', $move) }}'">
                <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($move->date)->format('Y-m-d') }}</td>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $move->name ?: '(Draft)' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $move->journal?->name ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $move->partner?->name ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $move->ref ?: '—' }}</td>
                <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $move->amount_total, 2) }}</td>
                <td class="px-3 py-2">
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $moveStateColor($move->state) }}">{{ $move->state_label }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">No journal entries yet.</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$moves" empty-text="No journal entries yet." :selectable="true" :total-count="$moves->total()">
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
            <td class="w-10 px-3 py-2 text-center" @click.stop>
                <input type="checkbox"
                       class="list-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
                       x-model="selected"
                       value="{{ $move->id }}">
            </td>
            <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($move->date)->format('Y-m-d') }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">{{ $move->name ?: '(Draft)' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $move->journal?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $move->partner?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $move->ref ?: '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $move->amount_total, 2) }}</td>
            <td class="px-3 py-2">
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $moveStateColor($move->state) }}">{{ $move->state_label }}</span>
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
