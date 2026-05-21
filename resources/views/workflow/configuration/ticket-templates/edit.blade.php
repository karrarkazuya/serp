@extends('layouts.app')
@section('title', __('workflow.new_ticket_template'))

@php
    $selectedDepartments = old('departments', $ticketTemplate->departments->pluck('id')->toArray());
    $existingInputs = old('inputs', $ticketTemplate->inputs->map(fn($inp) => [
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
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.ticket-templates.show', $ticketTemplate) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $ticketTemplate->name }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('common.edit') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('workflow.config.ticket-templates.show', $ticketTemplate) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.discard') }}</a>
            <button form="template-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save') }}</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="template-form" method="POST" action="{{ route('workflow.config.ticket-templates.update', $ticketTemplate) }}">
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

                <div class="p-6">
                    <div class="mb-6">
                        <input type="text" name="name" value="{{ old('name', $ticketTemplate->name) }}" required placeholder="{{ __('workflow.template_name_label') }}"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <x-relation-dropdown
                        table="workflow_groups"
                        field="name"
                        name="default_group_id"
                        :label="__('workflow.default_group_label')"
                        :selected="old('default_group_id', $ticketTemplate->default_group_id)"
                        relation="many2one"
                    />

                    <x-relation-dropdown
                        table="hr_departments"
                        field="name"
                        name="default_department_id"
                        :label="__('workflow.default_dept_label2')"
                        :selected="old('default_department_id', $ticketTemplate->default_department_id)"
                        relation="many2one"
                    />

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.sla_hours_label') }}</label>
                        <input type="number" name="resolve_max_duration" value="{{ old('resolve_max_duration', $ticketTemplate->resolve_max_duration) }}" min="1"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.enabled_label') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" {{ $ticketTemplate->enabled ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.enabled_text') }}</span>
                        </label>
                    </div>

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
                            <button type="button" @click="tab = 'departments'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'departments' ? 'text-gray-900 border-gray-300 -mb-px pb-2.25' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.departments_label') }}
                            </button>
                        </div>

                        <div class="min-h-36">
                            {{-- Form Fields Tab --}}
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

                            <div x-show="tab === 'description'" style="display:none">
                                <textarea name="description" rows="5" placeholder="{{ __('workflow.internal_description') }}"
                                          class="w-full px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ old('description', $ticketTemplate->description) }}</textarea>
                            </div>

                            <div x-show="tab === 'departments'" style="display:none" class="p-4">
                                <x-relation-dropdown
                                    table="hr_departments"
                                    field="name"
                                    name="departments"
                                    :label="__('workflow.departments_label')"
                                    :selected="$selectedDepartments"
                                    relation="many2many"
                                />
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
