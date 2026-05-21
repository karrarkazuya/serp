@extends('layouts.app')
@section('title', __('workflow.tasks_title'))

@php
    $taskQuickFilters = [
        ['label' => __('workflow.state_waiting'), 'params' => ['state' => 'draft'], 'url' => route('workflow.tasks.index', array_merge(request()->except('page'), ['state' => 'draft']))],
        ['label' => __('workflow.state_in_progress'), 'params' => ['state' => 'pending'], 'url' => route('workflow.tasks.index', array_merge(request()->except('page'), ['state' => 'pending']))],
        ['label' => __('workflow.state_completed'), 'params' => ['state' => 'completed'], 'url' => route('workflow.tasks.index', array_merge(request()->except('page'), ['state' => 'completed']))],
        ['label' => __('workflow.state_returned'), 'params' => ['state' => 'rejected'], 'url' => route('workflow.tasks.index', array_merge(request()->except('page'), ['state' => 'rejected']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <span class="text-xl font-semibold text-gray-700">{{ __('workflow.tasks_title') }}</span>

        <x-search
            :model="\App\Models\Workflow\Task::class"
            :action="route('workflow.tasks.index')"
            :quick-filters="$taskQuickFilters"
        />

        <div class="ms-auto flex items-center gap-3 text-sm text-gray-500 shrink-0">
            @if($tasks->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ $tasks->firstItem() }}-{{ $tasks->lastItem() }} / {{ $tasks->total() }}</span>
            @else
                <span class="text-sm font-semibold text-gray-400">{{ __('workflow.zero_records') }}</span>
            @endif
            <div class="flex items-center gap-1">
                @if($tasks->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $tasks->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($tasks->hasMorePages())
                    <a href="{{ $tasks->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$tasks" :empty-text="__('workflow.tasks_no_records')">
        <x-slot:columns>
            <x-sortable-th column="name"     :label="__('workflow.task_label')"    class="px-4 py-2.5" />
            <x-sortable-th column="state"    :label="__('workflow.state_col')"     class="px-3 py-2.5" />
            <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('workflow.procedure_col') }}</th>
            <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('workflow.department_label') }}</th>
            <th class="px-3 py-2.5 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">{{ __('workflow.assigned_to_label') }}</th>
            <x-sortable-th column="deadline" :label="__('workflow.deadline_label')" class="px-3 py-2.5" :default="true" />
        </x-slot:columns>

        @foreach($tasks as $task)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.procedures.show', $task->procedure) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $task->name }}</td>
            <td class="px-3 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $task->stateColor() }}">{{ $task->stateLabel() }}</span></td>
            <td class="px-3 py-2 text-gray-600">{{ $task->procedure?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->assignedDepartment?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->assignedUser?->name }}</td>
            <td class="px-3 py-2 text-gray-500 text-xs">{{ $task->resolve_deadline?->format('M j, Y H:i') }}</td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
