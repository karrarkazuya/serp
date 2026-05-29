@extends('layouts.app')
@section('title', __('accounting.journals'))

@php
    // Quick-filter chip labels were hardcoded English. The translation
    // keys already existed for every label; routing them through __() so
    // Arabic users see the local copy on every journal index load.
    $quickFilters = [
        ['label' => __('common.active'),                  'params' => ['filter' => ''],          'url' => route('accounting.journals.index', array_merge(request()->except('page','filter'), ['filter' => '']))],
        ['label' => __('accounting.journal_type_sales'),  'params' => ['type' => 'sales'],       'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'sales']))],
        ['label' => __('accounting.journal_type_purchase'),'params' => ['type' => 'purchase'],   'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'purchase']))],
        ['label' => __('accounting.journal_type_bank'),   'params' => ['type' => 'bank'],        'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'bank']))],
        ['label' => __('accounting.journal_type_cash'),   'params' => ['type' => 'cash'],        'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'cash']))],
        ['label' => __('accounting.journal_type_general'),'params' => ['type' => 'general'],     'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'general']))],
        ['label' => __('common.archived'),                'params' => ['filter' => 'archived'],  'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
    ];
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->can('create', \App\Models\Accounting\AccountJournal::class) ? route('accounting.journals.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.journals') }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search
                :model="\App\Models\Accounting\AccountJournal::class"
                :action="route('accounting.journals.index')"
                :quick-filters="$quickFilters"
            />
        </x-slot:search>
    </x-toolbar>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('accounting.no_journals')">
        <x-slot:columns>
            <x-sortable-th column="code" :label="__('accounting.col_code')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" :label="__('accounting.col_name')" class="px-3 py-2" />
            <x-sortable-th column="type" :label="__('accounting.col_type')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_default_account') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_currency') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_next_number') }}</th>
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
            @foreach($group['items'] as $journal)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.journals.show', $journal) }}'">
                <td class="px-4 py-2 font-medium text-gray-900">{{ $journal->code }}</td>
                <td class="px-3 py-2 text-gray-800">
                    {{ $journal->name }}
                    @if(!$journal->active)<span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('accounting.status_archived') }}</span>@endif
                </td>
                <td class="px-3 py-2 text-gray-600">{{ $journal->type_label }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $journal->defaultAccount ? $journal->defaultAccount->code.' '.$journal->defaultAccount->name : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $journal->currency ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600 tabular-nums">{{ $journal->sequence_next_number }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_journals') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$journals" :empty-text="__('accounting.no_journals')">
        <x-slot:columns>
            <x-sortable-th column="code" :label="__('accounting.col_code')" class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" :label="__('accounting.col_name')" class="px-3 py-2" />
            <x-sortable-th column="type" :label="__('accounting.col_type')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_default_account') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_currency') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_next_number') }}</th>
        </x-slot:columns>

        @foreach($journals as $journal)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.journals.show', $journal) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $journal->code }}</td>
            <td class="px-3 py-2 text-gray-800">
                {{ $journal->name }}
                @if(!$journal->active)<span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('accounting.status_archived') }}</span>@endif
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $journal->type_label }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $journal->defaultAccount ? $journal->defaultAccount->code.' '.$journal->defaultAccount->name : '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $journal->currency ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600 tabular-nums">{{ $journal->sequence_next_number }}</td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
