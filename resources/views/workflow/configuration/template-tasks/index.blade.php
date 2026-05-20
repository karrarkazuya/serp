@extends('layouts.app')
@section('title', 'Task Templates')

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <span class="text-xl font-semibold text-gray-700">Task Templates</span>
        <span class="text-xs text-gray-400">//to do direct task-template editing; currently managed through procedure templates</span>
        <x-search
            :model="\App\Models\Workflow\TemplateTask::class"
            :action="route('workflow.config.template-tasks.index')"
        />
    </div>

    <x-list :paginator="$templateTasks" empty-text="No task templates found.">
        <x-slot:columns>
            <x-sortable-th column="name"       label="Task"               class="px-4 py-2.5" />
            <x-sortable-th column="template"   label="Procedure Template" class="px-3 py-2.5" :default="true" />
            <x-sortable-th column="department" label="Department"         class="px-3 py-2.5" />
            <x-sortable-th column="sla"        label="SLA"                class="px-3 py-2.5" />
            <x-sortable-th column="enabled"    label="Status"             class="px-3 py-2.5" />
        </x-slot:columns>

        @foreach($templateTasks as $task)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.procedure-templates.show', $task->procedureTemplate) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $task->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->procedureTemplate?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->defaultDepartment?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->resolve_max_duration }} hours</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $task->enabled && $task->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $task->enabled && $task->active ? 'Enabled' : 'Disabled' }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
