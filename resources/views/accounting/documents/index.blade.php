@extends('layouts.app')
@section('title', $config['title'])

@php
    $quickFilters = [
        ['label' => 'Draft',     'params' => ['state' => 'draft'],     'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => 'draft']))],
        ['label' => 'Posted',    'params' => ['state' => 'posted'],    'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => 'posted']))],
        ['label' => 'Cancelled', 'params' => ['state' => 'cancelled'], 'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => 'cancelled']))],
        ['label' => 'All',       'params' => ['state' => ''],          'url' => route($config['routes']['index'], array_merge(request()->except('page','state'), ['state' => '']))],
    ];
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="!empty($config['routes']['create']) && auth()->user()->can('create', \App\Models\Accounting\AccountMove::class) ? route($config['routes']['create']) : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
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

    <div class="flex-1 overflow-y-auto p-4">
    <x-list :paginator="$documents" :empty-text="'No ' . strtolower($config['title']) . ' yet.'">
        <x-slot:columns>
            <x-sortable-th column="date" label="Date" class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" label="Number" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ $config['partner_label'] }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Due Date</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Reference</th>
            <x-sortable-th column="amount_total" label="Total" class="px-3 py-2 text-right" />
            <x-sortable-th column="state" label="State" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Payment</th>
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
    </div>
</div>
@endsection
