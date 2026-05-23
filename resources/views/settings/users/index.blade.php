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
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ collect($groups)->sum('count') }} records</span>
            @elseif(isset($users) && $users->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $users->firstItem() }}-{{ $users->lastItem() }} / {{ $users->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($users) && $users->onFirstPage())
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @elseif(isset($users))
                    <a href="{{ $users->previousPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($users) && $users->hasMorePages())
                    <a href="{{ $users->nextPageUrl() }}" class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @elseif(isset($users))
                    <span class="w-8 h-8 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    @can('export', \App\Models\User::class)
    <x-export
        :fields="config('exportable')['users']['fields'] ?? []"
        :export-url="route('export')"
        model-key="users"
    />
    @endcan

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('settings.no_users')">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('common.name')" class="px-6" :default="true" />
            <x-sortable-th column="email" :label="__('common.email')" />
            <th class="text-start px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('settings.roles') }}</th>
            <x-sortable-th column="status" :label="__('common.status')" />
            <th class="text-end px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.actions') }}</th>
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
            @foreach($group['items'] as $user)
            <tr x-show="open" class="hover:bg-[#714B67]/5 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.users.show', $user) }}'">
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
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('settings.no_users') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$users" :empty-text="__('settings.no_users')" :selectable="true" :total-count="$users->total()">
        <x-slot:columns>
            <x-sortable-th column="name" :label="__('common.name')" class="px-6" :default="true" />
            <x-sortable-th column="email" :label="__('common.email')" />
            <th class="text-start px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('settings.roles') }}</th>
            <x-sortable-th column="status" :label="__('common.status')" />
            <th class="text-end px-4 py-2.5 text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.actions') }}</th>
        </x-slot:columns>

        @foreach($users as $user)
        <tr class="hover:bg-[#714B67]/5 transition-colors cursor-pointer" onclick="window.location='{{ route('settings.users.show', $user) }}'">
            <td class="w-10 px-3 py-2 text-center" @click.stop>
                <input type="checkbox"
                       class="list-checkbox rounded border-gray-300 text-purple-600 focus:ring-purple-500 cursor-pointer"
                       x-model="selected"
                       value="{{ $user->id }}">
            </td>
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
    @endif
</div>
@endsection
