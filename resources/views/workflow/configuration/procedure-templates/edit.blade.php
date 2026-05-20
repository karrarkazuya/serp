@extends('layouts.app')
@section('title', 'Edit Procedure Template')

@php
    $selectedDepartments = old('departments', $procedureTemplate->departments->pluck('id')->toArray());
    $sortedSteps = $procedureTemplate->steps->sortBy('task_sequence')->values();
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.procedure-templates.show', $procedureTemplate) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $procedureTemplate->name }}</a>
            <span class="text-sm font-semibold text-gray-800">Edit</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('workflow.config.procedure-templates.show', $procedureTemplate) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="template-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="p-4 space-y-4">

            {{-- Template metadata form --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm">
                <form id="template-form" method="POST" action="{{ route('workflow.config.procedure-templates.update', $procedureTemplate) }}">
                    @csrf @method('PUT')

                    @if($errors->any())
                    <div class="px-6 pt-4 pb-0">
                        <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                            <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                    </div>
                    @endif

                    @if(session('success'))
                    <div class="px-6 pt-4 pb-0">
                        <div class="bg-green-50 border border-green-200 rounded-lg px-4 py-2 text-green-700 text-sm">{{ session('success') }}</div>
                    </div>
                    @endif

                    <div class="p-6">
                        <div class="mb-6">
                            <input type="text" name="name" value="{{ old('name', $procedureTemplate->name) }}" required placeholder="Template Name"
                                   class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                        </div>

                        <x-relation-dropdown
                            table="workflow_groups"
                            field="name"
                            name="default_group_id"
                            label="Default Group"
                            :selected="old('default_group_id', $procedureTemplate->default_group_id)"
                            relation="many2one"
                        />

                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500">SLA (hours)</label>
                            <input type="number" name="resolve_max_duration" value="{{ old('resolve_max_duration', $procedureTemplate->resolve_max_duration) }}" min="1"
                                   class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                        </div>

                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500">Creator sees tickets</label>
                            <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                <input type="checkbox" name="creator_see_tasks" value="1" {{ $procedureTemplate->creator_see_tasks ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                                <span>Enabled</span>
                            </label>
                        </div>

                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500">Enabled</label>
                            <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                <input type="checkbox" name="enabled" value="1" {{ $procedureTemplate->enabled ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                                <span>Enabled</span>
                            </label>
                        </div>

                        <div class="mt-6 border-t border-gray-200" x-data="{ tab: 'description' }">
                            <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
                                <button type="button" @click="tab = 'description'"
                                        class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                        :class="tab === 'description' ? 'text-gray-900 border-gray-300 -mb-px pb-2.25' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                    Description
                                </button>
                                <button type="button" @click="tab = 'departments'"
                                        class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                        :class="tab === 'departments' ? 'text-gray-900 border-gray-300 -mb-px pb-2.25' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                    Departments
                                </button>
                            </div>

                            <div class="min-h-36">
                                <div x-show="tab === 'description'" style="display:none">
                                    <textarea name="description" rows="5" placeholder="Internal description..."
                                              class="w-full px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ old('description', $procedureTemplate->description) }}</textarea>
                                </div>

                                <div x-show="tab === 'departments'" style="display:none" class="p-4">
                                    <x-relation-dropdown
                                        table="workflow_departments"
                                        field="name"
                                        name="departments"
                                        label="Departments"
                                        :selected="$selectedDepartments"
                                        relation="many2many"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>

            {{-- Steps management --}}
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ addingStep: false }">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800">Steps</span>
                    @can('update', $procedureTemplate)
                    <button type="button" @click="addingStep = !addingStep"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded transition-colors">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        Add Step
                    </button>
                    @endcan
                </div>

                {{-- Existing steps --}}
                @if($sortedSteps->isNotEmpty())
                <div class="divide-y divide-gray-100">
                    @foreach($sortedSteps as $step)
                    <div class="group">
                        {{-- Step row --}}
                        <div class="px-4 py-3 flex items-start gap-3 hover:bg-gray-50/60 transition-colors">
                            <span class="w-5 h-5 rounded-full bg-purple-100 text-purple-700 flex items-center justify-center text-[11px] font-bold shrink-0 mt-0.5">{{ $step->task_sequence }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-800">{{ $step->name }}</p>
                                @if($step->description)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $step->description }}</p>
                                @endif
                                <div class="flex items-center gap-3 mt-1 flex-wrap">
                                    @if($step->inputs->isNotEmpty())
                                    <span class="text-[11px] text-gray-400">{{ $step->inputs->count() }} field{{ $step->inputs->count() !== 1 ? 's' : '' }}</span>
                                    @endif
                                    @if($step->nextSteps->isNotEmpty())
                                    <span class="text-[11px] text-blue-500">→ {{ $step->nextSteps->pluck('name')->join(', ') }}</span>
                                    @endif
                                    @if($step->is_approve_only)
                                    <span class="text-[11px] bg-amber-50 text-amber-600 px-1.5 py-0.5 rounded">Approve only</span>
                                    @endif
                                    @if(!$step->enabled)
                                    <span class="text-[11px] bg-gray-100 text-gray-400 px-1.5 py-0.5 rounded">Disabled</span>
                                    @endif
                                </div>
                            </div>
                            <div class="shrink-0 text-right space-y-0.5 mr-2">
                                @if($step->defaultDepartment)
                                <p class="text-[11px] text-gray-500">{{ $step->defaultDepartment->name }}</p>
                                @endif
                                @if($step->resolve_max_duration)
                                <p class="text-[11px] text-gray-400">{{ $step->resolve_max_duration }}h SLA</p>
                                @endif
                            </div>
                            @can('update', $procedureTemplate)
                            <div class="shrink-0 flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <a href="{{ route('workflow.config.procedure-templates.steps.edit', [$procedureTemplate, $step]) }}"
                                   class="p-1.5 text-gray-400 hover:text-gray-600 hover:bg-gray-100 rounded transition-colors">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                </a>
                                <form method="POST" action="{{ route('workflow.config.procedure-templates.steps.destroy', [$procedureTemplate, $step]) }}"
                                      @submit.prevent="$dispatch('confirm-delete', { message: 'Are you sure you want to delete this step?', form: $el })">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded transition-colors">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                    </button>
                                </form>
                            </div>
                            @endcan
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-4 py-8 text-center text-sm text-gray-400">No steps yet. Add the first step below.</div>
                @endif

                {{-- Add Step form --}}
                @can('update', $procedureTemplate)
                <div x-show="addingStep" style="display:none" class="border-t border-purple-100 bg-purple-50/30">
                    <form method="POST" action="{{ route('workflow.config.procedure-templates.steps.store', $procedureTemplate) }}" class="p-4 space-y-3">
                        @csrf

                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-semibold text-purple-700 uppercase tracking-wide">New Step</span>
                            <button type="button" @click="addingStep = false" class="text-xs text-gray-400 hover:text-gray-600">Cancel</button>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Name <span class="text-red-400">*</span></label>
                                <input type="text" name="name" required
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white" placeholder="Step name">
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Sequence <span class="text-red-400">*</span></label>
                                <input type="number" name="task_sequence" value="{{ $sortedSteps->count() + 1 }}" required min="1"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-gray-500 mb-1">Description</label>
                            <textarea name="description" rows="2"
                                      class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white resize-none" placeholder="Optional description..."></textarea>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">Department</label>
                                <select name="default_department_id"
                                        class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white">
                                    <option value="">— None —</option>
                                    @foreach($departments as $dept)
                                    <option value="{{ $dept->id }}">{{ $dept->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-500 mb-1">SLA (hours)</label>
                                <input type="number" name="resolve_max_duration" min="1"
                                       class="w-full text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 bg-white" placeholder="-">
                            </div>
                        </div>

                        @if($sortedSteps->isNotEmpty())
                        <div>
                            <label class="block text-xs text-gray-500 mb-1.5">Next Steps</label>
                            <div class="flex flex-wrap gap-x-4 gap-y-1.5">
                                @foreach($sortedSteps as $otherStep)
                                <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                    <input type="checkbox" name="next_step_ids[]" value="{{ $otherStep->id }}" class="rounded border-gray-300 text-purple-600">
                                    <span>{{ $otherStep->task_sequence }}. {{ $otherStep->name }}</span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <div class="flex flex-wrap gap-x-5 gap-y-2 pt-1">
                            <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="is_approve_only" value="1" class="rounded border-gray-300 text-purple-600">
                                Approve only
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="has_procedures" value="1" class="rounded border-gray-300 text-purple-600">
                                Has sub-procedures
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="ignore_state" value="1" class="rounded border-gray-300 text-purple-600">
                                Ignore state
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="has_path_choice" value="1" class="rounded border-gray-300 text-purple-600">
                                Path choice
                            </label>
                            <label class="flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                                <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300 text-purple-600">
                                Enabled
                            </label>
                        </div>

                        <div class="flex justify-end pt-1">
                            <button type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Add Step</button>
                        </div>
                    </form>
                </div>
                @endcan
            </div>

        </div>
    </div>
</div>
@endsection
