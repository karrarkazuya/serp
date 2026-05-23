@extends('layouts.app')
@section('title', 'Journal Items')

@php
    $quickFilters = [
        ['label' => 'Posted',    'params' => ['state' => 'posted'],    'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => 'posted']))],
        ['label' => 'Draft',     'params' => ['state' => 'draft'],     'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => 'draft']))],
        ['label' => 'Cancelled', 'params' => ['state' => 'cancelled'], 'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => 'cancelled']))],
        ['label' => 'All',       'params' => ['state' => ''],          'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => '']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <div class="flex flex-col leading-tight shrink-0">
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Journal Items</span>
        </div>

        <x-search
            :model="\App\Models\Accounting\AccountMoveLine::class"
            :action="route('accounting.items.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            <span class="text-sm font-semibold text-gray-600">
                {{ $items->total() > 0 ? $items->firstItem().'-'.$items->lastItem() : 0 }} / {{ $items->total() }}
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 border-b border-gray-200 bg-gray-50">
        <div class="px-4 py-2">
            <p class="text-[11px] font-semibold uppercase text-gray-500">Debit</p>
            <p class="text-sm font-semibold text-gray-900 tabular-nums">{{ number_format($totalDebit, 2) }}</p>
        </div>
        <div class="px-4 py-2 border-t sm:border-t-0 sm:border-s border-gray-200">
            <p class="text-[11px] font-semibold uppercase text-gray-500">Credit</p>
            <p class="text-sm font-semibold text-gray-900 tabular-nums">{{ number_format($totalCredit, 2) }}</p>
        </div>
        <div class="px-4 py-2 border-t sm:border-t-0 sm:border-s border-gray-200">
            <p class="text-[11px] font-semibold uppercase text-gray-500">Balance</p>
            <p class="text-sm font-semibold {{ $totalBalance === 0.0 ? 'text-gray-900' : ($totalBalance > 0 ? 'text-green-700' : 'text-red-700') }} tabular-nums">
                {{ number_format($totalBalance, 2) }}
            </p>
        </div>
    </div>

    <x-list :paginator="$items" empty-text="No journal items yet.">
        <x-slot:columns>
            <x-sortable-th column="date" label="Date" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Journal Entry</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Journal</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Account</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Partner</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Label</th>
            <x-sortable-th column="debit" label="Debit" class="px-3 py-2 text-right" />
            <x-sortable-th column="credit" label="Credit" class="px-3 py-2 text-right" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">Balance</th>
            <x-sortable-th column="state" label="State" class="px-3 py-2" />
        </x-slot:columns>

        @foreach($items as $item)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.moves.show', $item->move) }}'">
            <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($item->date)->format('Y-m-d') }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">{{ $item->move?->name ?: '(Draft)' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $item->journal?->code ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-700">
                @if($item->account)
                    <span class="font-medium tabular-nums">{{ $item->account->code }}</span>
                    <span class="text-gray-500">{{ $item->account->name }}</span>
                @else
                    —
                @endif
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $item->partner?->name ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $item->name }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $item->debit, 2) }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $item->credit, 2) }}</td>
            <td class="px-3 py-2 text-right tabular-nums {{ $item->balance < 0 ? 'text-red-700' : 'text-gray-800' }}">
                {{ number_format($item->balance, 2) }}
            </td>
            <td class="px-3 py-2">
                @php
                    $color = match($item->state) {
                        'posted'    => 'bg-green-100 text-green-700',
                        'draft'     => 'bg-amber-100 text-amber-700',
                        'cancelled' => 'bg-gray-200 text-gray-600',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $color }}">{{ ucfirst($item->state) }}</span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
