@extends('layouts.app')
@section('title', __('workflow.task_templates_title'))

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-3 px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        <span class="text-xl font-semibold text-gray-700">{{ __('workflow.task_templates_title') }}</span>
        <span class="text-xs text-gray-400">//to do direct task-template editing; currently managed through procedure templates</span>
        <x-search
            :model="\App\Models\Workflow\TemplateTask::class"
            :action="route('workflow.config.template-tasks.index')"
        />
    </div>

    <x-list :paginator="$templateTasks" :empty-text="__('workflow.no_task_templates')">
        <x-slot:columns>
            <x-sortable-th column="name"       :label="__('workflow.task_label')"          class="px-4 py-2.5" />
            <x-sortable-th column="template"   :label="__('workflow.procedure_template_col')" class="px-3 py-2.5" :default="true" />
            <x-sortable-th column="department" :label="__('workflow.department_label')"    class="px-3 py-2.5" />
            <x-sortable-th column="sla"        :label="__('workflow.sla_hrs_label')"        class="px-3 py-2.5" />
            <x-sortable-th column="enabled"    :label="__('common.status')"                 class="px-3 py-2.5" />
        </x-slot:columns>

        @foreach($templateTasks as $task)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('workflow.config.procedure-templates.show', $task->procedureTemplate) }}'">
            <td class="px-4 py-2 font-medium text-gray-900">{{ $task->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->procedureTemplate?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->defaultDepartment?->name }}</td>
            <td class="px-3 py-2 text-gray-600">{{ $task->resolve_max_duration }} {{ __('workflow.hours_label') }}</td>
            <td class="px-3 py-2">
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $task->enabled && $task->active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                    {{ $task->enabled && $task->active ? __('workflow.enabled_text') : __('workflow.disabled_text') }}
                </span>
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
