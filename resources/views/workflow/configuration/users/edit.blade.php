@extends('layouts.app')
@section('title', __('workflow.configure_title') . ' — ' . $user->name)

@php
    $selectedGroups      = old('groups',      $wu->groups->pluck('id')->toArray());
    $selectedDepartments = old('departments', $wu->assignableDepartments->pluck('id')->toArray());
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.config.users.show', $user) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $user->name }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('workflow.configure_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('workflow.config.users.show', $user) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.discard') }}</a>
        <button form="user-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="user-form" method="POST" action="{{ route('workflow.config.users.update', $user) }}">
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
                    <div class="flex items-center gap-4 mb-6">
                        <div class="w-12 h-12 rounded-full bg-purple-100 flex items-center justify-center text-lg font-bold text-purple-700 shrink-0">
                            {{ $user->initials }}
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900">{{ $user->name }}</h2>
                            <p class="text-sm text-gray-400">{{ $user->email }}{{ $user->job_position ? ' · ' . $user->job_position : '' }}</p>
                        </div>
                    </div>

                    <x-relation-dropdown
                        table="hr_departments"
                        field="name"
                        name="default_department_id"
                        :label="__('workflow.default_dept_col')"
                        :selected="old('default_department_id', $wu->default_department_id)"
                        relation="many2one"
                    />

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('common.active') }}</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="active" value="1" {{ $wu->active ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>{{ __('workflow.enrolled_active_label') }}</span>
                        </label>
                    </div>

                    <div class="mt-6 border-t border-gray-200" x-data="{ tab: 'groups' }">
                        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
                            <button type="button" @click="tab = 'groups'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'groups' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.groups_col') }}
                            </button>
                            <button type="button" @click="tab = 'departments'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'departments' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                {{ __('workflow.assignable_departments_tab') }}
                            </button>
                        </div>

                        <div class="min-h-36 pt-4 pb-2">
                            <div x-show="tab === 'groups'" style="display:none" class="pt-1">
                                <x-relation-dropdown
                                    table="workflow_groups"
                                    field="name"
                                    name="groups"
                                    :label="__('workflow.groups_col')"
                                    :selected="$selectedGroups"
                                    relation="many2many"
                                />
                            </div>
                            <div x-show="tab === 'departments'" style="display:none" class="pt-1">
                                <x-relation-dropdown
                                    table="hr_departments"
                                    field="name"
                                    name="departments"
                                    :label="__('workflow.assignable_depts_label')"
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
