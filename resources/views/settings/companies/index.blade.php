@extends('layouts.app')
@section('title', __('settings.companies'))
@include('settings._sidebar')

@section('content')
@php
    $companyQuickFilters = [
        ['label' => __('common.active'), 'params' => ['filter' => ''], 'clear_params' => ['filter' => 'all'], 'url' => route('settings.companies.index', array_merge(request()->except('page', 'filter'), ['filter' => '']))],
        ['label' => __('common.archived'), 'params' => ['filter' => 'inactive'], 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['filter' => 'inactive']))],
        ['label' => __('common.all'), 'params' => ['filter' => 'all'], 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $companyGroups = [
        ['label' => __('settings.country'), 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['group_by' => 'country']))],
        ['label' => __('settings.currency'), 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['group_by' => 'currency']))],
        ['label' => __('common.status'), 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['group_by' => 'active']))],
    ];
@endphp
<div class="flex flex-col h-full">

    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 flex-wrap shrink-0">
        @can('create', \App\Models\Settings\Company::class)
        <a href="{{ route('settings.companies.create') }}"
           class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0 transition-colors">
            {{ __('settings.new_company') }}
        </a>
        @endcan

        <span class="text-lg font-semibold text-gray-700">{{ __('settings.companies') }}</span>

        <x-search
            :model="\App\Models\Settings\Company::class"
            :action="route('settings.companies.index')"
            :quick-filters="$companyQuickFilters"
            :group-by="$companyGroups"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            @if($companies->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $companies->firstItem() }}-{{ $companies->lastItem() }} / {{ $companies->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            <div class="flex items-center gap-1">
                @if($companies->onFirstPage())
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $companies->previousPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($companies->hasMorePages())
                    <a href="{{ $companies->nextPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$companies" :empty-text="__('settings.no_companies')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('settings.company_name')" class="px-6" :default="true" />
            <x-sortable-th column="email" :label="__('common.email')" />
            <x-sortable-th column="phone" :label="__('common.phone')" />
            <x-sortable-th column="city" :label="__('settings.city')" />
            <x-sortable-th column="users" :label="__('settings.users')" />
            <x-sortable-th column="status" :label="__('common.status')" />
            <th class="text-end px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.actions') }}</th>
        </x-slot:columns>

        @foreach($companies as $company)
        <tr class="hover:bg-[#714B67]/5 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.companies.show', $company) }}'">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg overflow-hidden bg-[#714B67]/10 flex items-center justify-center text-xs font-bold text-[#714B67] shrink-0">
                        @if($company->logo_url)
                            <img src="{{ $company->logo_url }}" alt="{{ $company->name }}" class="w-full h-full object-cover">
                        @else
                            {{ strtoupper(substr($company->name, 0, 2)) }}
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('settings.companies.show', $company) }}"
                           class="text-sm font-medium text-gray-900 hover:text-[#714B67] transition-colors" onclick="event.stopPropagation()">
                            {{ $company->name }}
                        </a>
                        @if($company->tax_id)
                            <div class="text-xs text-gray-500">{{ __('settings.tax_id') }}: {{ $company->tax_id }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $company->email ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $company->phone ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $company->city ?? '—' }}</td>
            <td class="px-4 py-3 text-sm text-gray-600">
                <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                    {{ $company->users_count ?? $company->users->count() }} {{ __('settings.users') }}
                </span>
            </td>
            <td class="px-4 py-3">
                @if($company->active)
                    <span class="inline-flex items-center gap-1 text-xs text-green-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('common.active') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> {{ __('common.archived') }}
                    </span>
                @endif
            </td>
            <td class="px-4 py-3 text-end" onclick="event.stopPropagation()">
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('settings.companies.show', $company) }}"
                       class="text-xs text-gray-500 hover:text-[#714B67] transition-colors px-2 py-1 rounded hover:bg-[#714B67]/5">
                        {{ __('common.view') }}
                    </a>
                    @can('update', $company)
                    <a href="{{ route('settings.companies.edit', $company) }}"
                       class="text-xs text-gray-500 hover:text-[#714B67] transition-colors px-2 py-1 rounded hover:bg-[#714B67]/5">
                        {{ __('common.edit') }}
                    </a>
                    @endcan
                    @can('delete', $company)
                    <form method="POST" action="{{ route('settings.companies.delete', $company) }}"
                          @submit.prevent="$dispatch('confirm-delete', { message: '{{ __('common.confirm_delete') }}', form: $el })">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded hover:bg-red-50">
                            {{ __('common.delete') }}
                        </button>
                    </form>
                    @endcan
                </div>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
