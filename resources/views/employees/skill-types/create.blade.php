@extends('layouts.app')
@section('title', __('employees.new_skill_type'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.skill-types.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.skill_types_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('employees.new_skill_type') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('employees.skill-types.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
        <button form="skill-type-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form id="skill-type-form" method="POST" action="{{ route('employees.skill-types.store') }}"
              x-data="{
                skills: [{ name: '' }],
                levels: [{ name: '', level_progress: 0, sequence: 0 }],
                addSkill() { this.skills.push({ name: '' }); },
                removeSkill(i) { this.skills.splice(i, 1); },
                addLevel() { this.levels.push({ name: '', level_progress: 0, sequence: this.levels.length }); },
                removeLevel(i) { this.levels.splice(i, 1); }
              }">
            @csrf

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.name') }} <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent" required>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.skills_section') }}</h3>
                        <button type="button" @click="addSkill()" class="text-xs text-purple-600 hover:text-purple-800 font-medium">{{ __('employees.add_item') }}</button>
                    </div>
                    <template x-for="(skill, i) in skills" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="`skills[${i}][name]`" x-model="skill.name" placeholder="{{ __('employees.skill_name_ph') }}"
                                   class="flex-1 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent">
                            <button type="button" @click="removeSkill(i)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </div>
                    </template>
                    <p x-show="skills.length === 0" class="text-sm text-gray-400 italic">{{ __('employees.no_skills_defined') }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.levels_section') }}</h3>
                        <button type="button" @click="addLevel()" class="text-xs text-purple-600 hover:text-purple-800 font-medium">{{ __('employees.add_item') }}</button>
                    </div>
                    <template x-for="(level, i) in levels" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="`levels[${i}][name]`" x-model="level.name" placeholder="{{ __('employees.level_name_ph') }}"
                                   class="flex-1 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent">
                            <input type="number" :name="`levels[${i}][level_progress]`" x-model="level.level_progress" min="0" max="100" placeholder="%"
                                   class="w-16 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent text-center">
                            <input type="hidden" :name="`levels[${i}][sequence]`" :value="i">
                            <button type="button" @click="removeLevel(i)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </div>
                    </template>
                    <p x-show="levels.length === 0" class="text-sm text-gray-400 italic">{{ __('employees.no_levels_defined') }}</p>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
