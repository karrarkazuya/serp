@extends('layouts.app')
@section('title', 'Companies')
@include('settings._sidebar')

@section('content')
@php
    $companyQuickFilters = [
        ['label' => 'Active', 'params' => ['filter' => ''], 'clear_params' => ['filter' => 'all'], 'url' => route('settings.companies.index', array_merge(request()->except('page', 'filter'), ['filter' => '']))],
        ['label' => 'Archived', 'params' => ['filter' => 'inactive'], 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['filter' => 'inactive']))],
        ['label' => 'All', 'params' => ['filter' => 'all'], 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $companyGroups = [
        ['label' => 'Country', 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['group_by' => 'country']))],
        ['label' => 'Currency', 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['group_by' => 'currency']))],
        ['label' => 'Status', 'url' => route('settings.companies.index', array_merge(request()->except('page'), ['group_by' => 'active']))],
    ];
@endphp
<div class="flex flex-col h-full">

    {{-- Header bar --}}
    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3 flex-wrap">
        @include('components.breadcrumb', ['items' => [
            ['label' => 'Settings', 'url' => route('settings.index')],
            ['label' => 'Companies'],
        ]])

        @can('create', \App\Models\Settings\Company::class)
        <a href="{{ route('settings.companies.create') }}"
           class="ml-auto flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Company
        </a>
        @endcan

        <x-search
            :model="\App\Models\Settings\Company::class"
            :action="route('settings.companies.index')"
            placeholder="Search companies..."
            :quick-filters="$companyQuickFilters"
            :group-by="$companyGroups"
        />
    </div>

    {{-- Table --}}
    <div class="flex-1 overflow-auto bg-white">
        <table class="w-full erp-table">
            <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                    <x-sortable-th column="name" label="Company" class="px-6" :default="true" />
                    <x-sortable-th column="email" label="Email" />
                    <x-sortable-th column="phone" label="Phone" />
                    <x-sortable-th column="city" label="City" />
                    <x-sortable-th column="users" label="Users" />
                    <x-sortable-th column="status" label="Status" />
                    <th class="text-right px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($companies as $company)
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-3">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                                {{ strtoupper(substr($company->name, 0, 2)) }}
                            </div>
                            <div>
                                <a href="{{ route('settings.companies.show', $company) }}"
                                   class="text-sm font-medium text-gray-900 hover:text-purple-600 transition-colors">
                                    {{ $company->name }}
                                </a>
                                @if($company->tax_id)
                                    <div class="text-xs text-gray-500">VAT: {{ $company->tax_id }}</div>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $company->email ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $company->phone ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">{{ $company->city ?? '—' }}</td>
                    <td class="px-4 py-3 text-sm text-gray-600">
                        <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full">
                            {{ $company->users_count ?? $company->users->count() }} users
                        </span>
                    </td>
                    <td class="px-4 py-3">
                        @if($company->active)
                            <span class="inline-flex items-center gap-1 text-xs text-green-700">
                                <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Active
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                                <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> Archived
                            </span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <a href="{{ route('settings.companies.show', $company) }}"
                               class="text-xs text-gray-500 hover:text-purple-600 transition-colors px-2 py-1 rounded hover:bg-purple-50">
                                View
                            </a>
                            @can('update', $company)
                            <a href="{{ route('settings.companies.edit', $company) }}"
                               class="text-xs text-gray-500 hover:text-purple-600 transition-colors px-2 py-1 rounded hover:bg-purple-50">
                                Edit
                            </a>
                            @endcan
                            @can('delete', $company)
                            <form method="POST" action="{{ route('settings.companies.delete', $company) }}"
                                  onsubmit="return confirm('Delete {{ $company->name }}? This cannot be undone.')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-xs text-red-500 hover:text-red-700 transition-colors px-2 py-1 rounded hover:bg-red-50">
                                    Delete
                                </button>
                            </form>
                            @endcan
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400">No companies found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($companies->hasPages())
    <div class="bg-white border-t border-gray-200 px-6 py-3">
        {{ $companies->withQueryString()->links() }}
    </div>
    @endif
</div>
@endsection
