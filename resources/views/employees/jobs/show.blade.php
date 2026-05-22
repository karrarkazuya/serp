@extends('layouts.app')
@section('title', $job->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.jobs.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.jobs_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $job->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', $job)
        <a href="{{ route('employees.jobs.edit', $job) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        @endcan

        @can('update', $job)
        <form method="POST" action="{{ $job->active ? route('employees.jobs.archive', $job) : route('employees.jobs.unarchive', $job) }}">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                {{ $job->active ? __('common.archive') : __('common.unarchive') }}
            </button>
        </form>
        @endcan

        @can('delete', $job)
        <div x-data="{ confirming: false }">
            <button type="button" x-show="!confirming" @click="confirming = true"
                    class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                <form method="POST" action="{{ route('employees.jobs.delete', $job) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                </form>
                <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">{{ __('common.cancel') }}</button>
            </div>
        </div>
        @endcan
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            @if(!$job->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('employees.job_is_archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.job_position') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $job->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.department') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $job->department?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.company') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $job->company?->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.status') }}</p>
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $job->state === 'open' ? 'text-green-700 bg-green-50' : 'text-gray-600 bg-gray-100' }}">
                        {{ $job->state === 'open' ? __('employees.recruiting') : __('employees.not_recruiting') }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.current_employees') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $job->no_of_employee }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.expected_employees') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $job->expected_employees }}</p>
                </div>
                @if($job->description)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.description') }}</p>
                    <p class="text-sm text-gray-700 mt-0.5 whitespace-pre-line">{{ $job->description }}</p>
                </div>
                @endif
                @if($job->requirements)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.requirements') }}</p>
                    <p class="text-sm text-gray-700 mt-0.5 whitespace-pre-line">{{ $job->requirements }}</p>
                </div>
                @endif
            </div>
        </div>

        @if($job->employees->isNotEmpty())
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
            <div class="px-5 py-3 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('common.employees') }} ({{ $job->employees->count() }})</h3>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($job->employees as $emp)
                <li class="px-5 py-3 hover:bg-purple-50/30">
                    <a href="{{ route('employees.show', $emp) }}" class="flex items-center gap-3">
                        @if($emp->avatar_url)
                            <img src="{{ $emp->avatar_url }}" alt="{{ $emp->name }}" class="w-8 h-8 rounded-full object-cover shrink-0">
                        @else
                            <div class="w-8 h-8 rounded-full bg-purple-100 flex items-center justify-center text-xs font-bold text-purple-700 shrink-0">{{ strtoupper(substr($emp->name, 0, 2)) }}</div>
                        @endif
                        <p class="text-sm font-medium text-gray-900">{{ $emp->name }}</p>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\Job"
                :model-id="$job->id"
                :can-comment="auth()->user()->can('comment', $job)"
                :comment-url="route('employees.jobs.comment', $job)"
            />
        </div>
    </div>
</div>
@endsection
