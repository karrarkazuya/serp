@extends('layouts.app')
@section('title', __('settings.users'))
@include('settings._sidebar')

@section('content')
@php
    $userQuickFilters = [
        ['label' => __('common.active'), 'url' => route('settings.users.index', array_merge(request()->except('page'), ['filters' => json_encode([['field' => 'active', 'operator' => '=', 'value' => '1', 'display' => 'Yes']])]))],
        ['label' => __('common.inactive'), 'url' => route('settings.users.index', array_merge(request()->except('page'), ['filters' => json_encode([['field' => 'active', 'operator' => '=', 'value' => '0', 'display' => 'No']])]))],
    ];
    $userGroups = [
        ['label' => __('settings.default_company'), 'url' => route('settings.users.index', array_merge(request()->except('page'), ['group_by' => 'company_id']))],
        ['label' => __('common.status'), 'url' => route('settings.users.index', array_merge(request()->except('page'), ['group_by' => 'active']))],
    ];
@endphp
<div class="flex flex-col h-full">

    <div class="bg-white border-b border-gray-200 px-6 py-3 flex items-center gap-3 flex-wrap">
        @include('components.breadcrumb', ['items' => [
            ['label' => __('settings.title'), 'url' => route('settings.index')],
            ['label' => __('settings.users')],
        ]])

        @can('create', \App\Models\User::class)
        <a href="{{ route('settings.users.create') }}"
           class="ms-auto flex items-center gap-1.5 px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white text-sm font-medium rounded-md transition-colors shadow-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            {{ __('settings.new_user') }}
        </a>
        @endcan

        <x-search
            :model="\App\Models\User::class"
            :action="route('settings.users.index')"
            :quick-filters="$userQuickFilters"
            :group-by="$userGroups"
        />
    </div>

    <x-list :paginator="$users" :empty-text="__('settings.no_users')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('common.name')" class="px-6" :default="true" />
            <x-sortable-th column="email" :label="__('common.email')" />
            <th class="text-start px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('settings.roles') }}</th>
            <x-sortable-th column="status" :label="__('common.status')" />
            <th class="text-end px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.actions') }}</th>
        </x-slot:columns>

        @foreach($users as $user)
        <tr class="hover:bg-purple-50/30 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.users.show', $user) }}'">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700">
                        {{ $user->initials }}
                    </div>
                    <div>
                        <div class="text-sm font-medium text-gray-900">{{ $user->name }}</div>
                        @if($user->job_position)
                            <div class="text-xs text-gray-500">{{ $user->job_position }}</div>
                        @endif
                    </div>
                </div>
            </td>
            <td class="px-4 py-3 text-sm text-gray-600">{{ $user->email }}</td>
            <td class="px-4 py-3">
                <div class="flex flex-wrap gap-1">
                    @forelse($user->roles as $role)
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-50 text-purple-700">
                            {{ $role->name }}
                        </span>
                    @empty
                        <span class="text-xs text-gray-400">{{ __('settings.no_roles') }}</span>
                    @endforelse
                </div>
            </td>
            <td class="px-4 py-3">
                @if($user->active)
                    <span class="inline-flex items-center gap-1 text-xs text-green-700">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> {{ __('common.active') }}
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 text-xs text-gray-400">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span> {{ __('common.inactive') }}
                    </span>
                @endif
            </td>
            <td class="px-4 py-3 text-end" onclick="event.stopPropagation()">
                <div class="flex items-center justify-end gap-2">
                    @can('update', $user)
                    <a href="{{ route('settings.users.edit', $user) }}"
                       class="text-xs text-gray-500 hover:text-purple-600 transition-colors px-2 py-1 rounded hover:bg-purple-50">
                        {{ __('common.edit') }}
                    </a>
                    @endcan
                    @can('delete', $user)
                    <form method="POST" action="{{ route('settings.users.delete', $user) }}"
                          onsubmit="return confirm('{{ __('common.confirm_delete') }}')">
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
