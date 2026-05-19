@extends('layouts.app')
@section('title', 'Configure — ' . $user->name)

@php
    $selectedGroups      = old('groups',      $wu->groups->pluck('id')->toArray());
    $selectedDepartments = old('departments', $wu->assignableDepartments->pluck('id')->toArray());
@endphp

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.users.show', $user) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $user->name }}</a>
            <span class="text-sm font-semibold text-gray-800">Configure</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('workflow.config.users.show', $user) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="user-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

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
                        table="workflow_departments"
                        field="name"
                        name="default_department_id"
                        label="Default Dept."
                        :selected="old('default_department_id', $wu->default_department_id)"
                        relation="many2one"
                    />

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">Active</label>
                        <label class="flex items-center gap-2 text-sm text-gray-800 cursor-pointer">
                            <input type="checkbox" name="active" value="1" {{ $wu->active ? 'checked' : '' }} class="rounded border-gray-300 text-purple-600">
                            <span>Enrolled & active</span>
                        </label>
                    </div>

                    <div class="mt-6 border-t border-gray-200" x-data="{ tab: 'groups' }">
                        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
                            <button type="button" @click="tab = 'groups'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'groups' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                Groups
                            </button>
                            <button type="button" @click="tab = 'departments'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'departments' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                Assignable Departments
                            </button>
                        </div>

                        <div class="min-h-36 pt-4 pb-2">
                            <div x-show="tab === 'groups'" style="display:none" class="pt-1">
                                <x-relation-dropdown
                                    table="workflow_groups"
                                    field="name"
                                    name="groups"
                                    label="Groups"
                                    :selected="$selectedGroups"
                                    relation="many2many"
                                />
                            </div>
                            <div x-show="tab === 'departments'" style="display:none" class="pt-1">
                                <x-relation-dropdown
                                    table="workflow_departments"
                                    field="name"
                                    name="departments"
                                    label="Assignable Depts."
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
