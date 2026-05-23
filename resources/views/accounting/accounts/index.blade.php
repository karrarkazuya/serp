@extends('layouts.app')
@section('title', 'Chart of Accounts')

@php
    $view = $view ?? request('view', 'list');
    $quickFilters = [
        ['label' => 'Active',   'params' => ['filter' => ''],         'url' => route('accounting.accounts.index', array_merge(request()->except('page','filter'), ['filter' => '']))],
        ['label' => 'Archived', 'params' => ['filter' => 'archived'], 'url' => route('accounting.accounts.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => 'All',      'params' => ['filter' => 'all'],      'url' => route('accounting.accounts.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $listUrl = route('accounting.accounts.index', array_merge(request()->except('view','page'), ['view' => 'list']));
    $treeUrl = route('accounting.accounts.index', array_merge(request()->except('view','page'), ['view' => 'tree']));
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full {{ $view === 'tree' ? 'bg-gray-50' : 'bg-white' }}">
    <div class="flex flex-wrap items-center gap-2 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Accounting\Account::class)
        <a href="{{ route('accounting.accounts.create') }}" class="px-3 py-1.5 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-medium rounded shadow-sm shrink-0">New</a>
        @endcan
        <div class="flex flex-col leading-tight shrink-0">
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">Accounting</a>
            <span class="text-sm font-semibold text-gray-800">Chart of Accounts</span>
        </div>

        @if($view !== 'tree')
        <x-search
            :model="\App\Models\Accounting\Account::class"
            :action="route('accounting.accounts.index')"
            :quick-filters="$quickFilters"
            :preserve="['view' => $view]"
        />
        @endif

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            <span class="text-sm font-semibold text-gray-600">
                @if($view === 'tree')
                    {{ $total ?? 0 }}
                @else
                    {{ $accounts->total() > 0 ? $accounts->firstItem().'-'.$accounts->lastItem() : 0 }} / {{ $accounts->total() }}
                @endif
            </span>

            {{-- View toggle: list vs tree --}}
            <div class="hidden sm:flex items-center rounded overflow-hidden bg-gray-200">
                <a href="{{ $listUrl }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'list' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="List view">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 5h12v2H4V5zm0 4h12v2H4V9zm0 4h12v2H4v-2z"/>
                    </svg>
                </a>
                <a href="{{ $treeUrl }}"
                   class="w-10 h-10 inline-flex items-center justify-center border border-gray-300 {{ $view === 'tree' ? 'bg-purple-100 text-gray-900 border-purple-400' : 'text-gray-600 hover:bg-gray-100' }}"
                   title="Tree view">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h6m-6 6h3m0 0v6m0-6h7m0 0v6m0-6h3"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    @if($view === 'tree')
        <x-tree :nodes="$treeNodes" empty-text="No accounts yet." />
    @else
        <x-list :paginator="$accounts" empty-text="No accounts yet.">
            <x-slot:columns>
                <x-sortable-th column="code" label="Code"  class="px-4 py-2" :default="true" />
                <x-sortable-th column="name" label="Name"  class="px-3 py-2" />
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">English</th>
                <x-sortable-th column="account_type" label="Type" class="px-3 py-2" />
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Parent</th>
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Reconcile</th>
                <th class="px-3 py-2 text-xs font-semibold text-gray-500 uppercase tracking-wide text-left">Company</th>
            </x-slot:columns>

            @foreach($accounts as $account)
            <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('accounting.accounts.show', $account) }}'">
                <td class="px-4 py-2 font-medium text-gray-900 tabular-nums">{{ $account->code }}</td>
                <td class="px-3 py-2 text-gray-800">
                    {{ $account->name }}
                    @if(!$account->active)<span class="ms-1.5 text-[10px] text-amber-600 font-semibold uppercase">Archived</span>@endif
                </td>
                <td class="px-3 py-2 text-gray-500 text-xs">{{ $account->name_en ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->type_label }}</td>
                <td class="px-3 py-2 text-gray-500 text-xs">{{ $account->parent ? $account->parent->code : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->reconcile ? 'Yes' : '—' }}</td>
                <td class="px-3 py-2 text-gray-600">{{ $account->company?->name }}</td>
            </tr>
            @endforeach
        </x-list>
    @endif
</div>
@endsection
