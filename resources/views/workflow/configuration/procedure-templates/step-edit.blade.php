@extends(request()->boolean('frame') ? 'layouts.frame' : 'layouts.app')
@section('title', __('workflow.edit_step_title') . ' — ' . $step->name)

@php
    $existingNextStepRecords = $step->nextSteps->map(fn($s) => [
        'id'    => $s->id,
        'label' => $s->name,
        'color' => null,
    ])->values()->toArray();
    $pathChoiceNamesInitial = old('path_choice_names', $step->pathChoices->pluck('name', 'target_step_id')->toArray());
    $existingInputs = old('inputs', $step->inputs->map(fn($inp) => [
        'id'          => $inp->id,
        'name'        => $inp->name,
        'type'        => $inp->type,
        'is_required' => $inp->is_required ? '1' : '0',
        'sort_order'  => $inp->sort_order,
        'options'     => $inp->options->pluck('name')->implode("\n"),
    ])->values()->toArray());
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            @unless(request()->boolean('frame'))
            <div class="flex items-center gap-1 text-xs text-gray-400">
                <a href="{{ route('workflow.config.procedure-templates.index') }}" class="hover:text-purple-600">{{ __('workflow.procedure_templates_title') }}</a>
                <span>/</span>
                <a href="{{ route('workflow.config.procedure-templates.edit', $procedureTemplate) }}" class="hover:text-purple-600">{{ $procedureTemplate->name }}</a>
            </div>
            @endunless
            <span class="text-sm font-semibold text-gray-800">{{ $step->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a data-discard href="{{ route('workflow.config.procedure-templates.edit', $procedureTemplate) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.discard') }}</a>
        <button form="step-form" type="submit"
                class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="step-form" method="POST"
                  action="{{ route('workflow.config.procedure-templates.steps.update', [$procedureTemplate, $step]) . (request()->boolean('frame') ? '?frame=1' : '') }}">
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
                        <input type="text" name="name" value="{{ old('name', $step->name) }}" required placeholder="{{ __('workflow.step_name_placeholder') }}"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    {{-- Shared fields --}}
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.step_department_label') }}</label>
                        <select name="default_department_id"
                                class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                            <option value="">—</option>
                            @foreach($departments as $dept)
                            <option value="{{ $dept->id }}" {{ old('default_department_id', $step->default_department_id) == $dept->id ? 'selected' : '' }}>{{ $dept->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.step_sla_label') }}</label>
                        <input type="number" name="resolve_max_duration" value="{{ old('resolve_max_duration', $step->resolve_max_duration) }}" min="1"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.step_enabled_label') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" {{ old('enabled', $step->enabled) ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.enabled_text') }}</span>
                        </label>
                    </div>

                    {{-- Step-specific flags (visible because procedure_template_id is set) --}}
                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.approve_only_label') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="is_approve_only" value="1" {{ old('is_approve_only', $step->is_approve_only) ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.approve_only_desc') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.ignore_state_label') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="ignore_state" value="1" {{ old('ignore_state', $step->ignore_state) ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.ignore_state_desc') }}</span>
                        </label>
                    </div>

                    <div class="flex items-start gap-4 py-3 border-b border-gray-100"
                         x-data="{ on: {{ old('has_procedures', $step->has_procedures) ? 'true' : 'false' }} }">
                        <label class="w-36 shrink-0 text-sm text-gray-500 pt-2">{{ __('workflow.has_sub_procedures_label') }}</label>
                        <div class="flex-1 flex flex-col gap-2 py-1">
                            <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                <input type="checkbox" name="has_procedures" value="1" x-model="on" class="rounded border-gray-300 text-purple-600">
                                <span>{{ __('workflow.enabled_text') }}</span>
                            </label>
                            <div x-show="on" x-transition style="display:none" class="flex flex-col gap-3">
                                <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                    <input type="checkbox" name="procedures_required" value="1"
                                           {{ old('procedures_required', $step->procedures_required) ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-purple-600">
                                    <span>{{ __('workflow.sub_procedures_required_desc') }}</span>
                                </label>
                                <x-relation-dropdown
                                    table="workflow_procedure_templates"
                                    field="name"
                                    name="sub_procedure_ids"
                                    :label="__('workflow.sub_procedures_label')"
                                    :selected="old('sub_procedure_ids', $step->subProcedures->pluck('id')->toArray())"
                                    relation="many2many"
                                    :exclude="[$procedureTemplate->id]"
                                    :compact="true"
                                />
                            </div>
                        </div>
                    </div>

                    {{-- Path choice + Next Steps (shared Alpine scope) --}}
                    <div x-data="{
                            pathOn: {{ old('has_path_choice', $step->has_path_choice) ? 'true' : 'false' }},
                            selectedNextStepRecords: {{ Js::from($existingNextStepRecords) }},
                            pathChoiceNames: {{ Js::from($pathChoiceNamesInitial) }},
                         }"
                         @step-next-steps-changed.window="selectedNextStepRecords = $event.detail.records">

                        {{-- Path choice row --}}
                        <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500 pt-2">{{ __('workflow.path_choice_label') }}</label>
                            <div class="flex-1 flex flex-col gap-2 py-1">
                                <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                                    <input type="checkbox" name="has_path_choice" value="1" x-model="pathOn" class="rounded border-gray-300 text-purple-600">
                                    <span>{{ __('workflow.path_choice_desc') }}</span>
                                </label>
                                <div x-show="pathOn" x-transition style="display:none" class="flex flex-col gap-2">
                                    <input type="text" name="path_choice_question"
                                           value="{{ old('path_choice_question', $step->path_choice_question) }}"
                                           placeholder="{{ __('workflow.path_choice_question_placeholder') }}"
                                           class="w-full text-sm border border-gray-200 rounded-lg px-3 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                    <label class="flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
                                        <input type="checkbox" name="path_choice_required" value="1"
                                               {{ old('path_choice_required', $step->path_choice_required) ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-purple-600">
                                        <span>{{ __('workflow.path_choice_required_desc') }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        {{-- Next Steps row --}}
                        <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('workflow.next_steps_label') }}</label>
                            <div class="flex-1 flex flex-col gap-2">
                                <x-relation-dropdown
                                    table="workflow_procedure_steps"
                                    field="name"
                                    name="next_step_ids"
                                    :label="__('workflow.next_steps_label')"
                                    :selected="old('next_step_ids', $step->nextSteps->pluck('id')->toArray())"
                                    relation="many2many"
                                    :exclude="[$step->id]"
                                    :lookup-url-override="route('workflow.config.procedure-templates.steps.lookup', $procedureTemplate)"
                                    event="step-next-steps-changed"
                                    :compact="true"
                                />

                                {{-- Path choice labels — shown per selected next step when path choice is on --}}
                                <div x-show="pathOn && selectedNextStepRecords.length > 0" style="display:none" class="mt-2 flex flex-col gap-1">
                                    <p class="text-xs text-gray-400 font-medium mb-1">{{ __('workflow.path_choice_names_hint') }}</p>
                                    <template x-for="step in selectedNextStepRecords" :key="step.id">
                                        <div class="flex items-center gap-2 max-w-md">
                                            <span class="w-36 shrink-0 text-sm text-gray-600 truncate" x-text="step.label"></span>
                                            <svg class="shrink-0 text-gray-300" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                                            <input type="text"
                                                   :name="`path_choice_names[${step.id}]`"
                                                   :value="pathChoiceNames[step.id] ?? ''"
                                                   @input="pathChoiceNames[step.id] = $event.target.value"
                                                   placeholder="e.g. Approve"
                                                   class="flex-1 text-sm border border-gray-200 rounded-lg px-2.5 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Tabs: Form Fields / Description --}}
                    <div class="mt-6 border-t border-gray-200"
                         x-data="{
                            tab: 'fields',
                            inputs: {{ Js::from($existingInputs) }},
                            addInput() {
                                this.inputs.push({ id: 0, name: '', type: 'char', is_required: '0', sort_order: this.inputs.length, options: '' });
                            },
                            removeInput(i) {
                                this.inputs.splice(i, 1);
                            }
                         }">
                        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
                            <button type="button" @click="tab = 'fields'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'fields' ? 'text-gray-900 border-gray-300 -mb-px pb-2.25' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.form_fields_tab') }}
                            </button>
                            <button type="button" @click="tab = 'description'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'description' ? 'text-gray-900 border-gray-300 -mb-px pb-2.25' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.description_tab') }}
                            </button>
                        </div>

                        <div class="min-h-36">
                            {{-- Form Fields --}}
                            <div x-show="tab === 'fields'" style="display:none" class="p-4">
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="pb-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3">{{ __('workflow.field_name_col') }}</th>
                                            <th class="pb-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3 w-32">{{ __('workflow.field_type_col') }}</th>
                                            <th class="pb-2 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3 w-20">{{ __('workflow.field_required_col') }}</th>
                                            <th class="pb-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3">{{ __('workflow.field_options_col') }} <span class="normal-case font-normal text-gray-300">{{ __('workflow.field_options_hint') }}</span></th>
                                            <th class="w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(inp, i) in inputs" :key="i">
                                            <tr class="border-b border-gray-50 align-top">
                                                <td class="py-2 pr-3">
                                                    <input type="hidden" :name="`inputs[${i}][id]`" :value="inp.id">
                                                    <input type="hidden" :name="`inputs[${i}][sort_order]`" :value="i">
                                                    <input type="text" :name="`inputs[${i}][name]`" x-model="inp.name" placeholder="{{ __('workflow.field_name_placeholder') }}"
                                                           class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                                </td>
                                                <td class="py-2 pr-3">
                                                    <select :name="`inputs[${i}][type]`" x-model="inp.type"
                                                            class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                                        <option value="char">{{ __('workflow.field_type_text') }}</option>
                                                        <option value="int">{{ __('workflow.field_type_number') }}</option>
                                                        <option value="float">{{ __('workflow.field_type_decimal') }}</option>
                                                        <option value="date">{{ __('workflow.field_type_date') }}</option>
                                                        <option value="datetime">{{ __('workflow.field_type_datetime') }}</option>
                                                        <option value="boolean">{{ __('workflow.field_type_boolean') }}</option>
                                                        <option value="select">{{ __('workflow.field_type_select') }}</option>
                                                        <option value="multiselect">{{ __('workflow.field_type_multiselect') }}</option>
                                                        <option value="textarea">{{ __('workflow.field_type_textarea') }}</option>
                                                        <option value="file">{{ __('workflow.field_type_file') }}</option>
                                                        <option value="label">{{ __('workflow.field_type_label') }}</option>
                                                    </select>
                                                </td>
                                                <td class="py-2 pr-3 text-center">
                                                    <input type="hidden" :name="`inputs[${i}][is_required]`" value="0">
                                                    <input type="checkbox" :name="`inputs[${i}][is_required]`" value="1"
                                                           :checked="inp.is_required == '1'"
                                                           @change="inp.is_required = $event.target.checked ? '1' : '0'"
                                                           class="rounded border-gray-300 text-purple-600 mt-2">
                                                </td>
                                                <td class="py-2 pr-3">
                                                    <template x-if="inp.type === 'select' || inp.type === 'multiselect'">
                                                        <textarea :name="`inputs[${i}][options]`" x-model="inp.options"
                                                                  rows="3" placeholder="{{ __('workflow.one_option_per_line') }}"
                                                                  class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-y"></textarea>
                                                    </template>
                                                    <template x-if="inp.type !== 'select' && inp.type !== 'multiselect'">
                                                        <span class="text-gray-300 text-xs">—</span>
                                                    </template>
                                                </td>
                                                <td class="py-2">
                                                    <button type="button" @click="removeInput(i)"
                                                            class="text-gray-300 hover:text-red-400 transition-colors">
                                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                                    </button>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                                <button type="button" @click="addInput()"
                                        class="mt-3 flex items-center gap-1 text-sm text-purple-600 hover:text-purple-800">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    {{ __('workflow.add_field') }}
                                </button>
                            </div>

                            {{-- Description --}}
                            <div x-show="tab === 'description'" style="display:none">
                                <textarea name="description" rows="5" placeholder="{{ __('workflow.internal_description') }}"
                                          class="w-full px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ old('description', $step->description) }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@if(request()->boolean('frame'))
<script>
    @if(session('success'))
    window.parent.postMessage({ action: 'step-edit-saved' }, '*');
    @endif
    document.addEventListener('DOMContentLoaded', () => {
        const a = document.querySelector('[data-discard]');
        if (a) a.addEventListener('click', e => {
            e.preventDefault();
            window.parent.postMessage({ action: 'close-step-edit' }, '*');
        });
    });
</script>
@endif
@endsection
