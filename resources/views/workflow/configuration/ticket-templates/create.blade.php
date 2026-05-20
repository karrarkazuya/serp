@extends('layouts.app')
@section('title', 'New Ticket Template')

@php
    $selectedDepartments = old('departments', []);
    $existingInputs = old('inputs', []);
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.ticket-templates.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Ticket Templates</a>
            <span class="text-sm font-semibold text-gray-800">New Ticket Template</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('workflow.config.ticket-templates.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="template-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="template-form" method="POST" action="{{ route('workflow.config.ticket-templates.store') }}">
                @csrf

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
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Template Name"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <x-relation-dropdown
                        table="workflow_groups"
                        field="name"
                        name="default_group_id"
                        label="Default Group"
                        :selected="old('default_group_id')"
                        relation="many2one"
                    />

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">SLA (hours)</label>
                        <input type="number" name="resolve_max_duration" value="{{ old('resolve_max_duration') }}" min="1"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">Enabled</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300 text-purple-600">
                            <span>Enabled</span>
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
                                Form Fields
                            </button>
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
                            {{-- Form Fields Tab --}}
                            <div x-show="tab === 'fields'" style="display:none" class="p-4">
                                <table class="w-full text-sm border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200">
                                            <th class="pb-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3">Name</th>
                                            <th class="pb-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3 w-32">Type</th>
                                            <th class="pb-2 text-center text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3 w-20">Required</th>
                                            <th class="pb-2 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide pr-3">Options <span class="normal-case font-normal text-gray-300">(select only)</span></th>
                                            <th class="w-8"></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="(inp, i) in inputs" :key="i">
                                            <tr class="border-b border-gray-50 align-top">
                                                <td class="py-2 pr-3">
                                                    <input type="hidden" :name="`inputs[${i}][id]`" :value="inp.id">
                                                    <input type="hidden" :name="`inputs[${i}][sort_order]`" :value="i">
                                                    <input type="text" :name="`inputs[${i}][name]`" x-model="inp.name" placeholder="Field name"
                                                           class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                                </td>
                                                <td class="py-2 pr-3">
                                                    <select :name="`inputs[${i}][type]`" x-model="inp.type"
                                                            class="w-full text-sm border border-gray-200 rounded px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                                        <option value="char">Text</option>
                                                        <option value="int">Number</option>
                                                        <option value="float">Decimal</option>
                                                        <option value="date">Date</option>
                                                        <option value="datetime">Date &amp; Time</option>
                                                        <option value="boolean">Yes/No</option>
                                                        <option value="select">Select</option>
                                                        <option value="multiselect">Multi-Select</option>
                                                        <option value="textarea">Long Text</option>
                                                        <option value="file">File Upload</option>
                                                        <option value="label">Label</option>
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
                                                                  rows="3" placeholder="One option per line"
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
                                    Add field
                                </button>
                            </div>

                            <div x-show="tab === 'description'" style="display:none">
                                <textarea name="description" rows="5" placeholder="Internal description..."
                                          class="w-full px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ old('description') }}</textarea>
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
    </div>
</div>
@endsection
