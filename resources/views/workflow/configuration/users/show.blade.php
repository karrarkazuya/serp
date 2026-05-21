@extends('layouts.app')
@section('title', $user->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.users.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.workflow_users_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $user->name }}</span>
        </div>
        <div class="flex items-center gap-2">
            @can('create', \App\Models\Workflow\WorkflowUser::class)
            <a href="{{ route('workflow.config.users.edit', $user) }}"
               class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.configure_btn') }}</a>
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if(session('success'))
        <div class="mx-4 mt-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif

        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="px-6 py-5">
                <div class="flex items-center gap-4 mb-6">
                    <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-lg font-bold text-purple-700 shrink-0">
                        {{ $user->initials }}
                    </div>
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h1>
                        <p class="text-sm text-gray-400">{{ $user->email }}{{ $user->job_position ? ' · ' . $user->job_position : '' }}</p>
                    </div>
                    @if($wu)
                        <span class="ms-auto inline-flex px-2 py-0.5 rounded text-xs font-medium {{ $wu->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $wu->active ? __('workflow.enrolled_label') : __('workflow.inactive_label') }}
                        </span>
                    @else
                        <span class="ms-auto inline-flex px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-400">{{ __('workflow.not_enrolled_label') }}</span>
                    @endif
                </div>

                @if($wu)
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.default_dept_col') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $wu->defaultDepartment?->name ?? '—' }}</span>
                </div>
                <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('workflow.groups_col') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $wu->groups->pluck('name')->join(', ') ?: '—' }}</span>
                </div>
                <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('workflow.assignable_depts_label') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $wu->assignableDepartments->pluck('name')->join(', ') ?: '—' }}</span>
                </div>
                @if($wu->manager)
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.role_label') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ __('workflow.manager_role') }}</span>
                </div>
                @endif
                @else
                <div class="py-6 text-center text-sm text-gray-400">
                    {{ __('workflow.no_workflow_profile') }}
                    @can('create', \App\Models\Workflow\WorkflowUser::class)
                    <a href="{{ route('workflow.config.users.edit', $user) }}" class="text-purple-600 hover:text-purple-700 ml-1">{{ __('workflow.set_it_up') }}</a>
                    @endcan
                </div>
                @endif
            </div>
        </div>

        @if($wu)
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Workflow\WorkflowUser"
                :model-id="$wu->id"
                :can-comment="auth()->user()->can('create', \App\Models\Workflow\WorkflowUser::class)"
            />
        </div>
        @else
        <div class="mb-4"></div>
        @endif
    </div>
</div>
@endsection
