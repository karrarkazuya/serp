@extends('layouts.app')
@section('title', __('accounting.journal_items'))

@php
    $quickFilters = [
        ['label' => __('accounting.status_posted'),    'params' => ['state' => 'posted'],    'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => 'posted']))],
        ['label' => __('accounting.status_draft'),     'params' => ['state' => 'draft'],     'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => 'draft']))],
        ['label' => __('accounting.status_cancelled'), 'params' => ['state' => 'cancelled'], 'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => 'cancelled']))],
        ['label' => __('accounting.all_models'),       'params' => ['state' => ''],          'url' => route('accounting.items.index', array_merge(request()->except('page','state'), ['state' => '']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.journal_items') }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search
                :model="\App\Models\Accounting\AccountMoveLine::class"
                :action="route('accounting.items.index')"
                :quick-filters="$quickFilters"
            />
        </x-slot:search>
        <x-slot:actions>
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600 shrink-0">{{ $groups->sum('count') }} records</span>
            @elseif(isset($items))
            <span class="text-sm font-semibold text-gray-600 shrink-0">
                {{ $items->total() > 0 ? $items->firstItem().'-'.$items->lastItem() : 0 }} / {{ $items->total() }}
            </span>
            @endif
        </x-slot:actions>
    </x-toolbar>

    <div class="shrink-0 grid grid-cols-1 sm:grid-cols-3 border-b border-gray-200 bg-gray-50">
        <div class="px-4 py-2">
            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('accounting.col_debit') }}</p>
            <p class="text-sm font-semibold text-gray-900 tabular-nums"><x-money :amount="(float) $totalDebit" /></p>
        </div>
        <div class="px-4 py-2 border-t sm:border-t-0 sm:border-s border-gray-200">
            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('accounting.col_credit') }}</p>
            <p class="text-sm font-semibold text-gray-900 tabular-nums"><x-money :amount="(float) $totalCredit" /></p>
        </div>
        <div class="px-4 py-2 border-t sm:border-t-0 sm:border-s border-gray-200">
            <p class="text-[11px] font-semibold uppercase text-gray-500">{{ __('accounting.col_balance') }}</p>
            <p class="text-sm font-semibold {{ $totalBalance === 0.0 ? 'text-gray-900' : ($totalBalance > 0 ? 'text-green-700' : 'text-red-700') }} tabular-nums">
                <x-money :amount="(float) $totalBalance" />
            </p>
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('accounting.no_items')">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('accounting.col_date')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.journal_entries') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_journal') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_account') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_partner') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_label') }}</th>
            <x-sortable-th column="debit" :label="__('accounting.col_debit')" class="px-3 py-2 text-right" />
            <x-sortable-th column="credit" :label="__('accounting.col_credit')" class="px-3 py-2 text-right" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">{{ __('accounting.col_balance') }}</th>
            <x-sortable-th column="state" :label="__('accounting.col_state')" class="px-3 py-2" />
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
            @foreach($group['items'] as $item)
            @php
                $itemMoveUrl = match($item->move?->move_type) {
                    'out_invoice' => route('accounting.invoices.show', $item->move),
                    'in_invoice'  => route('accounting.bills.show', $item->move),
                    'out_refund'  => route('accounting.credit-notes.show', $item->move),
                    'in_refund'   => route('accounting.refunds.show', $item->move),
                    default       => route('accounting.moves.show', $item->move),
                };
            @endphp
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ $itemMoveUrl }}'">
                <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($item->date)->format('Y-m-d') }}</td>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $item->move?->display_name ?: '('.__('accounting.status_draft').')' }}</td>
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
                <td class="px-3 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $item->debit" /></td>
                <td class="px-3 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $item->credit" /></td>
                <td class="px-3 py-2 text-right tabular-nums {{ $item->balance < 0 ? 'text-red-700' : 'text-gray-800' }}">
                    <x-money :amount="(float) $item->balance" />
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
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_items') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$items" :empty-text="__('accounting.no_items')">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('accounting.col_date')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.journal_entries') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_journal') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_account') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_partner') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_label') }}</th>
            <x-sortable-th column="debit" :label="__('accounting.col_debit')" class="px-3 py-2 text-right" />
            <x-sortable-th column="credit" :label="__('accounting.col_credit')" class="px-3 py-2 text-right" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-right">{{ __('accounting.col_balance') }}</th>
            <x-sortable-th column="state" :label="__('accounting.col_state')" class="px-3 py-2" />
        </x-slot:columns>

        @foreach($items as $item)
        @php
            $itemMoveUrl = match($item->move?->move_type) {
                'out_invoice' => route('accounting.invoices.show', $item->move),
                'in_invoice'  => route('accounting.bills.show', $item->move),
                'out_refund'  => route('accounting.credit-notes.show', $item->move),
                'in_refund'   => route('accounting.refunds.show', $item->move),
                default       => route('accounting.moves.show', $item->move),
            };
        @endphp
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ $itemMoveUrl }}'">
            <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($item->date)->format('Y-m-d') }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">{{ $item->move?->display_name ?: '('.__('accounting.status_draft').')' }}</td>
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
            <td class="px-3 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $item->debit" /></td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-800"><x-money :amount="(float) $item->credit" /></td>
            <td class="px-3 py-2 text-right tabular-nums {{ $item->balance < 0 ? 'text-red-700' : 'text-gray-800' }}">
                <x-money :amount="(float) $item->balance" />
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
    @endif
</div>
@endsection
