@extends('layouts.app')
@section('title', 'Workflow Users')

@section('content')
<div class="flex flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 shrink-0">
        <span class="text-xl font-semibold text-gray-700">Workflow Users</span>
        <x-search
            :model="\App\Models\User::class"
            :action="route('workflow.config.users.index')"
        />
    </div>

    @if(session('success'))
    <div class="mx-4 mt-3 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded">{{ session('success') }}</div>
    @endif

    <x-list :paginator="$users" empty-text="No active users found.">
        <x-slot:columns>
            <x-sortable-th column="name"         label="Name"         class="px-4 py-2" :default="true" />
            <x-sortable-th column="email"        label="Email"        class="px-3 py-2" />
            <x-sortable-th column="job_position" label="Position"     class="px-3 py-2 hidden sm:table-cell" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Default Dept.</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide hidden md:table-cell">Groups</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Status</th>
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
                        {{ $user->workflowUser->active ? 'Enrolled' : 'Inactive' }}
                    </span>
                @else
                    <span class="inline-flex px-2 py-0.5 rounded text-xs bg-gray-100 text-gray-400">Not enrolled</span>
                @endif
            </td>
            <td class="px-3 py-2 text-right" onclick="event.stopPropagation()">
                <a href="{{ route('workflow.config.users.show', $user) }}" class="text-xs text-purple-600 hover:text-purple-700 mr-3">View</a>
                @can('create', \App\Models\Workflow\WorkflowUser::class)
                <a href="{{ route('workflow.config.users.edit', $user) }}" class="text-xs text-gray-600 hover:text-gray-700">Configure</a>
                @endcan
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
