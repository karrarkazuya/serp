@extends('layouts.app')
@section('title', $group->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.config.groups.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.groups_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $group->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', $group)
        <a href="{{ route('workflow.config.groups.edit', $group) }}" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        @endcan
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        @if(session('success'))
        <div class="mx-4 mt-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="px-6 py-5">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $group->name }}</h1>
                </div>

                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('common.status') }}</label>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $group->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $group->active ? __('common.active') : __('common.inactive') }}
                    </span>
                </div>
                <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('workflow.workflow_users_label') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $group->workflowUsers->map(fn ($wu) => $wu->user?->name)->filter()->join(', ') ?: '—' }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Workflow\Group"
                :model-id="$group->id"
                :can-comment="auth()->user()->can('comment', $group)"
            />
        </div>
    </div>
</div>
@endsection
