@extends('layouts.app')
@section('title', __('workflow.workflow_users_title'))

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 shrink-0">
        <span class="text-xl font-semibold text-gray-700">{{ __('workflow.workflow_users_title') }}</span>
        <x-search
            :model="\App\Models\User::class"
            :action="route('workflow.config.users.index')"
        />
        <div class="ms-auto flex items-center gap-2 shrink-0">
            @if(isset($groups))
            <span class="text-sm font-semibold text-gray-600">{{ collect($groups)->sum('count') }} records</span>
            @elseif(isset($users) && $users->total() > 0)
            <span class="text-sm font-semibold text-gray-600">{{ $users->firstItem() }}-{{ $users->lastItem() }} / {{ $users->total() }}</span>
            @endif
        </div>
    </div>

    @if(session('success'))
    <div class="mx-4 mt-3 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
    @endif

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('workflow.no_active_users')">
        <x-slot:columns>
            <x-sortable-th column="name"         :label="__('common.name')"          class="px-4 py-2" :default="true" />
            <x-sortable-th column="email"        :label="__('common.email')"         class="px-3 py-2" />
            <x-sortable-th column="job_position" :label="__('workflow.position_label')" class="px-3 py-2 hidden sm:table-cell" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('workflow.default_dept_col') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">{{ __('workflow.groups_col') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.status') }}</th>
            <th class="px-3 py-2"></th>
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
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.users.show', $user) }}'">
                <td class="px-4 py-2">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                            {{ $user->initials }}
                        </div>
                        <span class="font-medium text-gray-900">{{ $user->name }}</span>
                    </div>
                </td>
                <td class="px-3 py-2 text-gray-500 text-sm">{{ $user->email }}</td>
                <td class="px-3 py-2 text-gray-500 text-sm hidden sm:table-cell">{{ $user->job_position ?: '—' }}</td>
                <td class="px-3 py-2 text-gray-600 text-sm">{{ $user->workflowUser?->defaultDepartment?->name ?? '—' }}</td>
                <td class="px-3 py-2 text-gray-600 text-sm hidden md:table-cell">{{ $user->workflowUser?->groups->pluck('name')->join(', ') ?: '—' }}</td>
                <td class="px-3 py-2">
                    @if($user->workflowUser)
                        <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $user->workflowUser->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $user->workflowUser->active ? __('workflow.enrolled_label') : __('workflow.inactive_label') }}
                        </span>
                    @else
                        <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-400">{{ __('workflow.not_enrolled_label') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2"></td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('workflow.no_active_users') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$users" :empty-text="__('workflow.no_active_users')">
        <x-slot:columns>
            <x-sortable-th column="name"         :label="__('common.name')"          class="px-4 py-2" :default="true" />
            <x-sortable-th column="email"        :label="__('common.email')"         class="px-3 py-2" />
            <x-sortable-th column="job_position" :label="__('workflow.position_label')" class="px-3 py-2 hidden sm:table-cell" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('workflow.default_dept_col') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">{{ __('workflow.groups_col') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ __('common.status') }}</th>
            <th class="px-3 py-2"></th>
        </x-slot:columns>

        @foreach($users as $user)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.users.show', $user) }}'">
            <td class="px-4 py-2">
                <div class="flex items-center gap-2">
                    <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">
                        {{ $user->initials }}
                    </div>
                    <span class="font-medium text-gray-900">{{ $user->name }}</span>
                </div>
            </td>
            <td class="px-3 py-2 text-gray-500 text-sm">{{ $user->email }}</td>
            <td class="px-3 py-2 text-gray-500 text-sm hidden sm:table-cell">{{ $user->job_position ?: '—' }}</td>
            <td class="px-3 py-2 text-gray-600 text-sm">{{ $user->workflowUser?->defaultDepartment?->name ?? '—' }}</td>
            <td class="px-3 py-2 text-gray-600 text-sm hidden md:table-cell">{{ $user->workflowUser?->groups->pluck('name')->join(', ') ?: '—' }}</td>
            <td class="px-3 py-2">
                @if($user->workflowUser)
                    <span class="inline-flex px-2 py-0.5 rounded text-xs {{ $user->workflowUser->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $user->workflowUser->active ? __('workflow.enrolled_label') : __('workflow.inactive_label') }}
                    </span>
                @else
                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-400">{{ __('workflow.not_enrolled_label') }}</span>
                @endif
            </td>
            <td class="px-3 py-2 text-right" onclick="event.stopPropagation()">
                <a href="{{ route('workflow.config.users.show', $user) }}" class="text-xs text-purple-600 hover:text-purple-700 mr-3">{{ __('common.view') }}</a>
                @can('create', \App\Models\Workflow\WorkflowUser::class)
                <a href="{{ route('workflow.config.users.edit', $user) }}" class="text-xs text-gray-600 hover:text-gray-700">{{ __('workflow.configure_btn') }}</a>
                @endcan
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
