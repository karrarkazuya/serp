@extends('layouts.app')
@section('title', $config['title'])

@php
    $quickFilters = [
        ['label' => __('accounting.status_draft'),     'params' => ['state' => 'draft'],     'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => 'draft']))],
        ['label' => __('accounting.status_posted'),    'params' => ['state' => 'posted'],    'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => 'posted']))],
        ['label' => __('accounting.status_cancelled'), 'params' => ['state' => 'cancelled'], 'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => 'cancelled']))],
        ['label' => __('accounting.view_all'),         'params' => ['state' => ''],          'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => '']))],
    ];
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="!empty($config['routes']['create']) && auth()->user()->can('create', \App\Models\Accounting\AccountMove::class) ? route($config['routes']['create']) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $config['title'] }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search
                :model="\App\Models\Accounting\AccountMove::class"
                :action="route($config['routes']['index'])"
                :quick-filters="$quickFilters"
            />
        </x-slot:search>
    </x-toolbar>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="'No ' . strtolower($config['title']) . ' yet.'">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('accounting.col_date')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" :label="__('accounting.col_number')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ $config['partner_label'] }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_due_date') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_reference') }}</th>
            <x-sortable-th column="amount_total" :label="__('accounting.col_total')" class="px-3 py-2 text-right" />
            <x-sortable-th column="state" :label="__('accounting.col_state')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_payment') }}</th>
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
            @foreach($group['items'] as $document)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route($config['routes']['show'], $document) }}'">
                <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($document->date)->format('Y-m-d') }}</td>
                <td class="px-3 py-2 font-medium text-gray-900">{{ $document->name ?: '(Draft)' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $document->partner?->name ?: '—' }}</td>
                <td class="px-3 py-2 tabular-nums {{ $document->invoice_date_due && $document->invoice_date_due->isPast() && !$document->isPaid() ? 'text-red-600 font-medium' : 'text-gray-600' }}">{{ optional($document->invoice_date_due)->format('Y-m-d') ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $document->ref ?: '—' }}</td>
                <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $document->amount_total, 2) }}</td>
                <td class="px-3 py-2">
                    @php
                        $color = match($document->state) {
                            'posted'    => 'bg-green-100 text-green-700',
                            'draft'     => 'bg-amber-100 text-amber-700',
                            'cancelled' => 'bg-gray-200 text-gray-600',
                            default     => 'bg-gray-100 text-gray-600',
                        };
                    @endphp
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $color }}">{{ $document->state_label }}</span>
                </td>
                <td class="px-3 py-2">
                    @if($document->isPosted())
                    @php
                        $payColor = match($document->payment_state ?? 'not_paid') {
                            'paid'     => 'bg-green-100 text-green-700',
                            'partial'  => 'bg-blue-100 text-blue-700',
                            default    => 'bg-orange-100 text-orange-700',
                        };
                    @endphp
                    <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $payColor }}">{{ $document->payment_state_label }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_records') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$documents" :empty-text="'No ' . strtolower($config['title']) . ' yet.'">
        <x-slot:columns>
            <x-sortable-th column="date" :label="__('accounting.col_date')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" :label="__('accounting.col_number')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ $config['partner_label'] }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_due_date') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_reference') }}</th>
            <x-sortable-th column="amount_total" :label="__('accounting.col_total')" class="px-3 py-2 text-right" />
            <x-sortable-th column="state" :label="__('accounting.col_state')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_payment') }}</th>
        </x-slot:columns>

        @foreach($documents as $document)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route($config['routes']['show'], $document) }}'">
            <td class="px-4 py-2 text-gray-700 tabular-nums">{{ optional($document->date)->format('Y-m-d') }}</td>
            <td class="px-3 py-2 font-medium text-gray-900">{{ $document->name ?: '(Draft)' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $document->partner?->name ?: '—' }}</td>
            <td class="px-3 py-2 tabular-nums {{ $document->invoice_date_due && $document->invoice_date_due->isPast() && !$document->isPaid() ? 'text-red-600 font-medium' : 'text-gray-600' }}">{{ optional($document->invoice_date_due)->format('Y-m-d') ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $document->ref ?: '—' }}</td>
            <td class="px-3 py-2 text-right tabular-nums text-gray-800">{{ number_format((float) $document->amount_total, 2) }}</td>
            <td class="px-3 py-2">
                @php
                    $color = match($document->state) {
                        'posted'    => 'bg-green-100 text-green-700',
                        'draft'     => 'bg-amber-100 text-amber-700',
                        'cancelled' => 'bg-gray-200 text-gray-600',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $color }}">{{ $document->state_label }}</span>
            </td>
            <td class="px-3 py-2">
                @if($document->isPosted())
                @php
                    $payColor = match($document->payment_state ?? 'not_paid') {
                        'paid'     => 'bg-green-100 text-green-700',
                        'partial'  => 'bg-blue-100 text-blue-700',
                        default    => 'bg-orange-100 text-orange-700',
                    };
                @endphp
                <span class="inline-block px-2 py-0.5 rounded text-[11px] font-medium {{ $payColor }}">{{ $document->payment_state_label }}</span>
                @endif
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
