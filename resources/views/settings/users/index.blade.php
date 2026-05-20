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

    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 flex-wrap shrink-0">
        @can('create', \App\Models\User::class)
        <a href="{{ route('settings.users.create') }}"
           class="px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0 transition-colors">
            {{ __('settings.new_user') }}
        </a>
        @endcan

        <span class="text-lg font-semibold text-gray-700">{{ __('settings.users') }}</span>

        <x-search
            :model="\App\Models\User::class"
            :action="route('settings.users.index')"
            :quick-filters="$userQuickFilters"
            :group-by="$userGroups"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            @if($users->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $users->firstItem() }}-{{ $users->lastItem() }} / {{ $users->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            <div class="flex items-center gap-1">
                @if($users->onFirstPage())
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $users->previousPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
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
        <tr class="hover:bg-[#714B67]/5 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.users.show', $user) }}'">
            <td class="px-6 py-3">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-[#714B67]/10 flex items-center justify-center text-xs font-bold text-[#714B67]">
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
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#714B67]/10 text-[#714B67]">
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
                       class="text-xs text-gray-500 hover:text-[#714B67] transition-colors px-2 py-1 rounded hover:bg-[#714B67]/5">
                        {{ __('common.edit') }}
                    </a>
                    @endcan
                    @can('delete', $user)
                    <form method="POST" action="{{ route('settings.users.delete', $user) }}"
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
