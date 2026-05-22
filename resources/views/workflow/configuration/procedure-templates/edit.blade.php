@extends('layouts.app')
@section('title', __('common.edit') . ' — ' . $procedureTemplate->name)

@php
    $selectedDepartments = old('departments', $procedureTemplate->departments->pluck('id')->toArray());
    $sortedSteps         = $procedureTemplate->steps->sortBy('id')->values();
    $storeStepUrl        = route('workflow.config.procedure-templates.steps.store', $procedureTemplate);
    $flowchartEditUrl    = $flowchartUrl . '?mode=edit';
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50"
     x-data="editTemplatePage()"
     @keydown.escape.window="if (showAddStep) { showAddStep = false; $event.preventDefault(); } else if (showStepEdit) { closeStepEdit(); $event.preventDefault(); }">

    {{-- ── Header ── --}}
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.config.procedure-templates.show', $procedureTemplate) }}"
               class="text-xs text-purple-600 hover:text-purple-700">{{ $procedureTemplate->name }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('common.edit') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('workflow.config.procedure-templates.show', $procedureTemplate) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
            {{ __('workflow.discard') }}
        </a>
        <button form="template-form" type="submit"
                class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">
            {{ __('workflow.save_template') }}
        </button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    {{-- ── Body ── --}}
    <div class="flex flex-1 min-h-0">

        {{-- ── Left sidebar ── --}}
        <div class="w-80 shrink-0 flex flex-col border-r border-gray-200 bg-white"
             x-data="{ sideTab: 'template' }">

            {{-- Sidebar tabs --}}
            <div class="flex shrink-0 border-b border-gray-200">
                <button type="button" @click="sideTab = 'template'"
                        class="flex-1 py-2.5 text-xs font-semibold transition-colors"
                        :class="sideTab === 'template' ? 'text-[#714B67] border-b-2 border-[#714B67]' : 'text-gray-400 hover:text-gray-600'">
                    {{ __('workflow.show_template') }}
                </button>
                <button type="button" @click="sideTab = 'steps'"
                        class="flex-1 py-2.5 text-xs font-semibold transition-colors"
                        :class="sideTab === 'steps' ? 'text-[#714B67] border-b-2 border-[#714B67]' : 'text-gray-400 hover:text-gray-600'">
                    {{ __('workflow.show_steps') }} ({{ $sortedSteps->count() }})
                </button>
            </div>

            {{-- Sidebar content --}}
            <div class="flex-1 overflow-y-auto">

                {{-- ── Template tab ── --}}
                <div x-show="sideTab === 'template'" style="display:none">
                    <form id="template-form" method="POST"
                          action="{{ route('workflow.config.procedure-templates.update', $procedureTemplate) }}">
                        @csrf @method('PUT')

                        @if($errors->any())
                        <div class="mx-4 mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded-lg">
                            <ul class="list-disc list-inside text-xs text-red-600 space-y-0.5">
                                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                            </ul>
                        </div>
                        @endif

                        @if(session('success'))
                        <div class="mx-4 mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded-lg text-green-700 text-xs">{{ session('success') }}</div>
                        @endif

                        {{-- ── Section: General ── --}}
                        <div class="px-4 pt-4 pb-1">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-3">{{ __('workflow.general_section') }}</p>

                            {{-- Name --}}
                            <div class="py-2.5 border-b border-gray-100">
                                <label class="block text-[10px] font-medium text-gray-400 mb-1">{{ __('workflow.template_name_label') }} <span class="text-red-400">*</span></label>
                                <input type="text" name="name" value="{{ old('name', $procedureTemplate->name) }}" required
                                       placeholder="e.g. Employee Onboarding"
                                       class="w-full text-sm font-semibold text-gray-800 placeholder-gray-300 border-0 focus:outline-none focus:ring-0 bg-transparent">
                            </div>

                            {{-- Description --}}
                            <div class="py-2.5 border-b border-gray-100">
                                <label class="block text-[10px] font-medium text-gray-400 mb-0.5">{{ __('common.description') }}</label>
                                <p class="text-[10px] text-gray-400 mb-1.5">{{ __('workflow.description_shown_hint') }}</p>
                                <textarea name="description" rows="3" placeholder="{{ __('workflow.describe_procedure') }}"
                                          class="w-full text-sm text-gray-800 placeholder-gray-300 border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-y">{{ old('description', $procedureTemplate->description) }}</textarea>
                            </div>

                            {{-- Default Group --}}
                            <x-relation-dropdown
                                table="workflow_groups"
                                field="name"
                                name="default_group_id"
                                :label="__('workflow.default_group_label')"
                                :selected="old('default_group_id', $procedureTemplate->default_group_id)"
                                relation="many2one"
                            />

                            {{-- Departments --}}
                            <div class="py-2.5 border-b border-gray-100">
                                <label class="block text-[10px] font-medium text-gray-400 mb-1">{{ __('workflow.who_can_open') }}</label>
                                <x-relation-dropdown
                                    table="hr_departments"
                                    field="name"
                                    name="departments"
                                    :label="__('workflow.who_can_open')"
                                    :selected="$selectedDepartments"
                                    relation="many2many"
                                    :compact="true"
                                    :list="true"
                                />
                                <p class="text-[10px] text-gray-400 mt-1">{{ __('workflow.restrict_departments_hint') }}</p>
                            </div>

                            {{-- SLA --}}
                            <div class="py-2.5 border-b border-gray-100">
                                <label class="block text-[10px] font-medium text-gray-400 mb-1">{{ __('workflow.max_resolution_time') }}</label>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="resolve_max_duration" min="1"
                                           value="{{ old('resolve_max_duration', $procedureTemplate->resolve_max_duration) }}"
                                           class="w-24 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5"
                                           placeholder="—">
                                    <span class="text-xs text-gray-400">{{ __('workflow.hours_label') }}</span>
                                </div>
                            </div>
                        </div>

                        {{-- ── Section: Options ── --}}
                        <div class="px-4 pt-4 pb-1">
                            <p class="text-[10px] font-semibold uppercase tracking-widest text-gray-400 mb-3">{{ __('workflow.options_section') }}</p>

                            <label class="flex items-start gap-3 py-2.5 border-b border-gray-100 cursor-pointer group">
                                <input type="checkbox" name="enabled" value="1"
                                       {{ $procedureTemplate->enabled ? 'checked' : '' }}
                                       class="mt-0.5 shrink-0 rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 group-hover:text-gray-900 leading-snug">{{ __('workflow.enabled_text') }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ __('workflow.enabled_allow_desc') }}</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 py-2.5 cursor-pointer group">
                                <input type="checkbox" name="creator_see_tasks" value="1"
                                       {{ $procedureTemplate->creator_see_tasks ? 'checked' : '' }}
                                       class="mt-0.5 shrink-0 rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                                <div>
                                    <p class="text-sm font-medium text-gray-700 group-hover:text-gray-900 leading-snug">{{ __('workflow.creator_sees_tasks') }}</p>
                                    <p class="text-[10px] text-gray-400 mt-0.5">{{ __('workflow.creator_sees_tasks_desc') }}</p>
                                </div>
                            </label>
                        </div>

                    </form>
                </div>

                {{-- ── Steps tab ── --}}
                <div x-show="sideTab === 'steps'" style="display:none">
                    @if($sortedSteps->isNotEmpty())
                    <div class="divide-y divide-gray-100">
                        @foreach($sortedSteps as $step)
                        <div class="group px-4 py-3 hover:bg-gray-50/60 transition-colors"
                             x-data="{ confirming: false }">
                            <form x-ref="deleteForm" method="POST"
                                  action="{{ route('workflow.config.procedure-templates.steps.destroy', [$procedureTemplate, $step]) }}">
                                @csrf @method('DELETE')
                            </form>
                            <div class="flex items-start gap-2.5">
                                <span class="mt-0.5 w-1.5 h-1.5 rounded-full bg-purple-300 shrink-0"></span>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-gray-800 leading-snug">{{ $step->name }}</p>
                                    <div class="flex items-center gap-2 mt-1 flex-wrap">
                                        @if($step->inputs->isNotEmpty())
                                        <span class="text-[10px] text-gray-400">{{ $step->inputs->count() }} field{{ $step->inputs->count() !== 1 ? 's' : '' }}</span>
                                        @endif
                                        @if($step->nextSteps->isNotEmpty())
                                        <span class="text-[10px] text-blue-400 truncate max-w-36">→ {{ $step->nextSteps->pluck('name')->join(', ') }}</span>
                                        @endif
                                        @if(!$step->enabled)
                                        <span class="text-[10px] bg-gray-100 text-gray-400 px-1 py-0.5 rounded">{{ __('workflow.off_label') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @can('update', $procedureTemplate)
                                <div class="shrink-0 flex items-center gap-0.5">
                                    {{-- Edit --}}
                                    <a href="{{ route('workflow.config.procedure-templates.steps.edit', [$procedureTemplate, $step]) }}"
                                       title="{{ __('workflow.edit_step_title') }}"
                                       class="p-1 text-gray-300 hover:text-gray-600 rounded transition-colors opacity-0 group-hover:opacity-100">
                                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    {{-- Delete with inline confirm --}}
                                    <div class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        <button type="button" x-show="!confirming" @click="confirming = true"
                                                class="p-1 text-gray-300 hover:text-red-400 rounded transition-colors">
                                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a1 1 0 011-1h4a1 1 0 011 1v2"/></svg>
                                        </button>
                                    </div>
                                    <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                        <button type="button" @click="$refs.deleteForm.submit()"
                                                class="text-[10px] bg-red-600 text-white px-1.5 py-0.5 rounded">{{ __('common.yes') }}</button>
                                        <button type="button" @click="confirming = false"
                                                class="text-[10px] text-gray-400 hover:text-gray-600">{{ __('common.no') }}</button>
                                    </div>
                                </div>
                                @endcan
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @else
                    <div class="px-4 py-10 text-center text-xs text-gray-400">{{ __('workflow.no_steps_yet') }}</div>
                    @endif
                </div>

            </div>
        </div>

        {{-- ── Right panel: flowchart ── --}}
        <div class="flex-1 min-w-0 flex flex-col min-h-0 relative">

            {{-- Toolbar --}}
            <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
                <span class="text-xs text-gray-400">{{ __('workflow.flowchart_tab') }}</span>
                <div class="flex items-center gap-2">
                    <span x-show="stepAdded" x-transition:enter="transition ease-out duration-200"
                          x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                          x-transition:leave="transition ease-in duration-150"
                          x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                          class="text-xs text-green-600 font-medium" style="display:none">
                        {{ __('workflow.step_added') }}
                    </span>
                    @can('update', $procedureTemplate)
                    <button type="button" @click="openAddStep()"
                            class="flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded transition-colors">
                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                        {{ __('workflow.add_step') }}
                    </button>
                    @endcan
                </div>
            </div>

            {{-- Flowchart iframe --}}
            <iframe x-ref="flowchartFrame"
                    src="{{ $flowchartEditUrl }}"
                    class="flex-1 border-0 min-h-0"
                    title="Procedure Flowchart">
            </iframe>

            {{-- ── Delete confirm overlay ── --}}
            <div x-show="pendingDelete" style="display:none"
                 class="absolute bottom-6 left-1/2 -translate-x-1/2 z-30 bg-white border-2 border-red-200 rounded-xl shadow-2xl px-6 py-4 text-center"
                 style="min-width: 280px">
                <p class="text-sm font-medium text-gray-800 mb-0.5">{{ __('workflow.delete_step_confirm') }}</p>
                <p class="text-xs text-gray-500 mb-3">"<span x-text="pendingDelete?.name" class="font-medium"></span>" {{ __('workflow.delete_step_body') }}</p>
                <div class="flex items-center justify-center gap-2">
                    <button type="button" @click="executeDelete()" :disabled="deleteLoading"
                            class="px-4 py-1.5 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg disabled:opacity-50 transition-colors">
                        <span x-show="!deleteLoading">{{ __('common.delete') }}</span>
                        <span x-show="deleteLoading" style="display:none">{{ __('workflow.deleting') }}</span>
                    </button>
                    <button type="button" @click="pendingDelete = null"
                            class="px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                </div>
            </div>

        </div>
    </div>

    {{-- ── Step Edit Modal (iframe) ── --}}
    <div x-show="showStepEdit" style="display:none"
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl flex flex-col overflow-hidden"
             style="width: min(1100px, 90vw); height: 90vh;"
             @click.stop>
            {{-- Modal header --}}
            <div class="shrink-0 flex items-center justify-between px-4 py-2 bg-white border-b border-gray-200">
                <span class="text-sm font-semibold text-gray-700">{{ __('workflow.edit_step_title') }}</span>
                <button type="button" @click="closeStepEdit()"
                        class="p-1 text-gray-400 hover:text-gray-700 transition-colors rounded">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            {{-- Iframe --}}
            <iframe x-ref="stepEditFrame"
                    :src="stepEditUrl || 'about:blank'"
                    class="flex-1 border-0 min-h-0"
                    title="Edit Step">
            </iframe>
        </div>
    </div>

    {{-- ── Add Step Modal ── --}}
    <template x-if="showAddStep">
    <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-sm"
         @click.self="showAddStep = false">

        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-xl max-h-[90vh] flex flex-col">

            {{-- Header --}}
            <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between shrink-0">
                <h3 class="text-base font-semibold text-gray-800">{{ __('workflow.add_step') }}</h3>
                <button type="button" @click="showAddStep = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>

            {{-- Errors --}}
            <div x-show="stepErrors.length > 0" style="display:none" class="mx-6 mt-4 px-4 py-3 bg-red-50 border border-red-200 rounded-lg shrink-0">
                <template x-for="err in stepErrors" :key="err">
                    <p class="text-sm text-red-600" x-text="err"></p>
                </template>
            </div>

            {{-- Form --}}
            <form x-ref="addStepForm"
                  method="POST"
                  action="{{ $storeStepUrl }}"
                  @submit.prevent="submitAddStep()"
                  class="overflow-y-auto flex-1 px-6 divide-y divide-gray-100"
                  x-init="$nextTick(() => $el.querySelector('[name=name]')?.focus())">
                @csrf

                {{-- Name --}}
                <div class="flex items-center gap-4 py-3">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('common.name') }} <span class="text-red-400">*</span></label>
                    <input type="text" name="name" required placeholder="{{ __('workflow.step_name_placeholder') }}"
                           class="flex-1 text-sm border-0 focus:outline-none focus:ring-0 bg-transparent text-gray-800 placeholder-gray-300">
                </div>

                {{-- Description --}}
                <div class="flex items-start gap-4 py-3">
                    <label class="w-36 shrink-0 text-sm text-gray-500 pt-1">{{ __('common.description') }}</label>
                    <textarea name="description" rows="2" placeholder="{{ __('workflow.internal_description') }}"
                              class="flex-1 text-sm border-0 focus:outline-none focus:ring-0 bg-transparent text-gray-800 placeholder-gray-300 resize-none"></textarea>
                </div>

                {{-- Department --}}
                <x-relation-dropdown
                    table="hr_departments"
                    field="name"
                    name="default_department_id"
                    :label="__('workflow.step_department_label')"
                    relation="many2one"
                />

                {{-- SLA --}}
                <div class="flex items-center gap-4 py-3">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.max_resolution_time') }}</label>
                    <div class="flex items-center gap-2">
                        <input type="number" name="resolve_max_duration" value="24" min="1"
                               class="w-20 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                        <span class="text-xs text-gray-400">{{ __('workflow.hours_label') }}</span>
                    </div>
                </div>

                {{-- Next Steps --}}
                <x-relation-dropdown
                    table="workflow_procedure_steps"
                    field="name"
                    name="next_step_ids"
                    :label="__('workflow.next_steps_label')"
                    relation="many2many"
                    :lookup-url-override="route('workflow.config.procedure-templates.steps.lookup', $procedureTemplate)"
                />

                {{-- Options --}}
                <div class="py-3">
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wide mb-2.5">{{ __('workflow.options_section') }}</p>
                    <div class="flex flex-wrap gap-x-5 gap-y-2.5">
                        <label class="flex items-center gap-1.5 text-sm text-gray-700 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300 text-purple-600 focus:ring-purple-400">
                            {{ __('workflow.enabled_text') }}
                        </label>
                    </div>
                </div>

            </form>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 flex items-center justify-between shrink-0">
                <p class="text-xs text-gray-400">{{ __('workflow.form_fields_after_save') }}</p>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showAddStep = false"
                            class="px-4 py-1.5 text-sm text-gray-600 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                        {{ __('common.cancel') }}
                    </button>
                    <button type="button" @click="submitAddStep()" :disabled="stepLoading"
                            class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded-lg shadow-sm disabled:opacity-50 transition-colors">
                        <span x-show="!stepLoading">{{ __('workflow.save_step') }}</span>
                        <span x-show="stepLoading" style="display:none">{{ __('workflow.saving') }}</span>
                    </button>
                </div>
            </div>

        </div>
    </div>
    </template>

</div>

<script>
function editTemplatePage() {
    return {
        showAddStep:  false,
        showStepEdit: false,
        stepEditUrl:  '',
        pendingDelete: null,
        deleteLoading: false,
        stepLoading:  false,
        stepErrors:   [],
        stepAdded:    false,

        init() {
            window.addEventListener('message', e => {
                if (!e.data?.action) return;
                switch (e.data.action) {
                    case 'edit-step':
                        this.stepEditUrl  = e.data.url;
                        this.showStepEdit = true;
                        break;
                    case 'step-edit-saved':
                        this.showStepEdit = false;
                        this.$refs.flowchartFrame.src = this.$refs.flowchartFrame.src;
                        break;
                    case 'close-step-edit':
                        this.closeStepEdit();
                        break;
                    case 'confirm-delete-step':
                        this.pendingDelete = { url: e.data.url, name: e.data.name };
                        break;
                }
            });
        },

        closeStepEdit() {
            this.showStepEdit = false;
            // Always reload flowchart after editing a step
            this.$refs.flowchartFrame.src = this.$refs.flowchartFrame.src;
        },

        async executeDelete() {
            if (this.deleteLoading || !this.pendingDelete) return;
            this.deleteLoading = true;
            try {
                const resp = await fetch(this.pendingDelete.url, {
                    method: 'DELETE',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content || '',
                    },
                });
                if (resp.ok) {
                    this.pendingDelete = null;
                    this.$refs.flowchartFrame.src = this.$refs.flowchartFrame.src;
                }
            } catch {}
            this.deleteLoading = false;
        },

        openAddStep() {
            this.stepErrors = [];
            this.showAddStep = true;
        },

        async submitAddStep() {
            if (this.stepLoading) return;
            this.stepLoading = true;
            this.stepErrors  = [];
            try {
                const resp = await fetch(this.$refs.addStepForm.action, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: new FormData(this.$refs.addStepForm),
                });
                if (resp.ok) {
                    this.showAddStep = false;
                    this.stepAdded = true;
                    setTimeout(() => { this.stepAdded = false; }, 3000);
                    this.$refs.flowchartFrame.src = this.$refs.flowchartFrame.src;
                } else {
                    const data = await resp.json().catch(() => ({}));
                    this.stepErrors = data.errors
                        ? Object.values(data.errors).flat()
                        : [data.message || @js(__('workflow.failed_to_save_step'))];
                }
            } catch {
                this.stepErrors = [@js(__('workflow.network_error_retry'))];
            }
            this.stepLoading = false;
        },
    };
}
</script>
@endsection
