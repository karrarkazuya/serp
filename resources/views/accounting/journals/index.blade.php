@extends('layouts.app')
@section('title', 'Journals')

@php
    $quickFilters = [
        ['label' => 'Active',    'params' => ['filter' => ''],          'url' => route('accounting.journals.index', array_merge(request()->except('page','filter'), ['filter' => '']))],
        ['label' => 'Sales',     'params' => ['type' => 'sales'],       'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'sales']))],
        ['label' => 'Purchase',  'params' => ['type' => 'purchase'],    'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'purchase']))],
        ['label' => 'Bank',      'params' => ['type' => 'bank'],        'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'bank']))],
        ['label' => 'Cash',      'params' => ['type' => 'cash'],        'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'cash']))],
        ['label' => 'Misc',      'params' => ['type' => 'general'],     'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['type' => 'general']))],
        ['label' => 'Archived',  'params' => ['filter' => 'archived'],  'url' => route('accounting.journals.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Accounting\AccountJournal::class)
        <a href="{{ route('accounting.journals.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded shadow-sm shrink-0">New</a>
        @endcan
        <div class="flex flex-col leading-tight shrink-0">
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Journals</span>
        </div>

        <x-search
            :model="\App\Models\Accounting\AccountJournal::class"
            :action="route('accounting.journals.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            <span class="text-sm font-semibold text-gray-600">
                {{ $journals->total() > 0 ? $journals->firstItem().'-'.$journals->lastItem() : 0 }} / {{ $journals->total() }}
            </span>
        </div>
    </div>

    <x-list :paginator="$journals" empty-text="No journals yet.">
        <x-slot:columns>
            <x-sortable-th column="code" label="Code" class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" label="Name" class="px-3 py-2" />
            <x-sortable-th column="type" label="Type" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Default Account</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Currency</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Next #</th>
        </x-slot:columns>

        @foreach($journals as $journal)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.journals.show', $journal) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $journal->code }}</td>
            <td class="px-3 py-2 text-gray-800">
                {{ $journal->name }}
                @if(!$journal->active)<span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">Archived</span>@endif
            </td>
            <td class="px-3 py-2 text-gray-600">{{ $journal->type_label }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $journal->defaultAccount ? $journal->defaultAccount->code.' '.$journal->defaultAccount->name : '—' }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $journal->currency ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600 tabular-nums">{{ $journal->sequence_next_number }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
