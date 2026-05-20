@extends('layouts.app')
@section('title', $manager->workflowUser?->user?->name ?? 'Manager')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.managers.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Managers</a>
            <span class="text-sm font-semibold text-gray-800">{{ $manager->workflowUser?->user?->name ?? 'Manager' }}</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <span class="text-xs text-gray-400">//to do manager edit action</span>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if(session('success'))
        <div class="mx-4 mt-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="px-6 py-5">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $manager->workflowUser?->user?->name ?? 'Manager' }}</h1>
                    @if($manager->workflowUser?->user?->email)
                    <p class="text-sm text-gray-400 mt-1">{{ $manager->workflowUser->user->email }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">Default Dept.</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $manager->workflowUser?->defaultDepartment?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">Status</label>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $manager->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $manager->active ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500 pt-0.5">Departments</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $manager->departments->pluck('name')->join(', ') ?: '—' }}</span>
                </div>
            </div>
        </div>

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Workflow\Manager"
                :model-id="$manager->id"
                :can-comment="auth()->user()->can('comment', $manager)"
            />
        </div>
    </div>
</div>
@endsection
