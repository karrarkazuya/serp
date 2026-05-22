@extends('layouts.app')
@section('title', __('workflow.new_procedure_template'))

@php
    $selectedDepartments = old('departments', []);
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.config.procedure-templates.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.procedure_templates_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('workflow.new_procedure_template') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('workflow.config.procedure-templates.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.discard') }}</a>
        <button form="template-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="template-form" method="POST" action="{{ route('workflow.config.procedure-templates.store') }}">
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
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('workflow.template_name_label') }}"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <x-relation-dropdown
                        table="workflow_groups"
                        field="name"
                        name="default_group_id"
                        :label="__('workflow.default_group_label')"
                        :selected="old('default_group_id')"
                        relation="many2one"
                    />

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.sla_hours_label') }}</label>
                        <input type="number" name="resolve_max_duration" value="{{ old('resolve_max_duration') }}" min="1"
                               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5" placeholder="-">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.creator_sees_tasks') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="creator_see_tasks" value="1" {{ old('creator_see_tasks') ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.enabled_text') }}</span>
                        </label>
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.enabled_label') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="enabled" value="1" checked class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.enabled_text') }}</span>
                        </label>
                    </div>

                    <div class="mt-6 border-t border-gray-200" x-data="{ tab: 'description' }">
                        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
                            <button type="button" @click="tab = 'description'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'description' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.description_tab') }}
                            </button>
                            <button type="button" @click="tab = 'departments'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'departments' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.departments_label') }}
                            </button>
                        </div>

                        <div class="min-h-36">
                            <div x-show="tab === 'description'" style="display:none">
                                <textarea name="description" rows="5" placeholder="{{ __('workflow.internal_description') }}"
                                          class="w-full px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ old('description') }}</textarea>
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
