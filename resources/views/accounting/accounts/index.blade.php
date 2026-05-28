@extends('layouts.app')
@section('title', __('accounting.chart_of_accounts'))

@php
    $view = $view ?? request('view', 'list');
    $quickFilters = [
        ['label' => __('accounting.status_active'),   'params' => ['filter' => ''],         'url' => route('accounting.accounts.index', array_merge(request()->except('page','filter'), ['filter' => '']))],
        ['label' => __('accounting.status_archived'), 'params' => ['filter' => 'archived'], 'url' => route('accounting.accounts.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('accounting.all_models'),      'params' => ['filter' => 'all'],      'url' => route('accounting.accounts.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $listUrl = route('accounting.accounts.index', array_merge(request()->except('view','page'), ['view' => 'list']));
    $treeUrl = route('accounting.accounts.index', array_merge(request()->except('view','page'), ['view' => 'tree']));
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar :new-href="auth()->user()->can('create', \App\Models\Accounting\Account::class) ? route('accounting.accounts.create') : null">
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.chart_of_accounts') }}</span>
        </x-slot:breadcrumb>
        <x-slot:search>
            <x-search
                :model="\App\Models\Accounting\Account::class"
                :action="route('accounting.accounts.index')"
                :quick-filters="$quickFilters"
                :preserve="['view' => $view]"
            />
        </x-slot:search>
        <x-slot:actions>
            <div class="flex items-center rounded overflow-hidden border border-gray-300">
                <a href="{{ $listUrl }}"
                   class="w-8 h-8 inline-flex items-center justify-center {{ $view === 'list' ? 'bg-purple-100 text-purple-700' : 'bg-white text-gray-500 hover:bg-gray-50' }}"
                   title="{{ __('accounting.title_list_view') }}">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/></svg>
                </a>
                <a href="{{ $treeUrl }}"
                   class="w-8 h-8 inline-flex items-center justify-center border-l border-gray-300 {{ $view === 'tree' ? 'bg-purple-100 text-purple-700' : 'bg-white text-gray-500 hover:bg-gray-50' }}"
                   title="{{ __('accounting.title_tree_view') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h6m-6 6h3m0 0v6m0-6h7m0 0v6m0-6h3"/></svg>
                </a>
            </div>
        </x-slot:actions>
    </x-toolbar>

    @if($view === 'tree')
        <x-tree :nodes="$treeNodes" empty-text="{{ __('accounting.no_accounts') }}" />
    @elseif(isset($groups))
    <x-list :grouped="true" empty-text="{{ __('accounting.no_accounts') }}">
        <x-slot:columns>
            <x-sortable-th column="code" :label="__('accounting.col_code')"  class="px-4 py-2" :default="true" />
            <x-sortable-th column="name" :label="__('accounting.col_name')"  class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_english') }}</th>
            <x-sortable-th column="account_type" :label="__('accounting.col_type')" class="px-3 py-2" />
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_parent') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_reconcile') }}</th>
            <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_company') }}</th>
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
            @foreach($group['items'] as $account)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.accounts.show', $account) }}'">
                <td class="px-4 py-2 font-medium text-gray-900 tabular-nums">{{ $account->code }}</td>
                <td class="px-3 py-2 text-gray-800">
                    {{ $account->name }}
                    @if(!$account->active)<span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('accounting.status_archived') }}</span>@endif
                </td>
                <td class="px-3 py-2 text-gray-500 text-xs">{{ $account->name_en ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->type_label }}</td>
                <td class="px-3 py-2 text-gray-500 text-xs">{{ $account->parent ? $account->parent->code : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->reconcile ? __('accounting.yes') : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->company?->name }}</td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('accounting.no_accounts') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
        <x-list :paginator="$accounts" empty-text="{{ __('accounting.no_accounts') }}">
            <x-slot:columns>
                <x-sortable-th column="code" :label="__('accounting.col_code')"  class="px-4 py-2" :default="true" />
                <x-sortable-th column="name" :label="__('accounting.col_name')"  class="px-3 py-2" />
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_english') }}</th>
                <x-sortable-th column="account_type" :label="__('accounting.col_type')" class="px-3 py-2" />
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_parent') }}</th>
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_reconcile') }}</th>
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">{{ __('accounting.col_company') }}</th>
            </x-slot:columns>

            @foreach($accounts as $account)
            <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.accounts.show', $account) }}'">
                <td class="px-4 py-2 font-medium text-gray-900 tabular-nums">{{ $account->code }}</td>
                <td class="px-3 py-2 text-gray-800">
                    {{ $account->name }}
                    @if(!$account->active)<span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">{{ __('accounting.status_archived') }}</span>@endif
                </td>
                <td class="px-3 py-2 text-gray-500 text-xs">{{ $account->name_en ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->type_label }}</td>
                <td class="px-3 py-2 text-gray-500 text-xs">{{ $account->parent ? $account->parent->code : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->reconcile ? __('accounting.yes') : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->company?->name }}</td>
            </tr>
            @endforeach
        </x-list>
    @endif
</div>
@endsection
