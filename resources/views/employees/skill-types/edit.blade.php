@extends('layouts.app')
@section('title', 'Edit ' . $skillType->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('employees.skill-types.show', $skillType) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $skillType->name }}</a>
            <span class="text-sm font-semibold text-gray-800">Edit</span>
        </div>

        <div class="ms-auto flex items-center gap-2">
            <a href="{{ route('employees.skill-types.show', $skillType) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Cancel</a>
            <button form="skill-type-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if ($errors->any())
            <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-sm text-red-700">
                <ul class="list-disc list-inside space-y-1">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @php
            $initialSkills = $skillType->skills->map(fn($s) => ['name' => $s->name])->values()->toArray();
            $initialLevels = $skillType->levels->map(fn($l) => ['name' => $l->name, 'level_progress' => $l->level_progress, 'sequence' => $l->sequence])->values()->toArray();
        @endphp

        <form id="skill-type-form" method="POST" action="{{ route('employees.skill-types.update', $skillType) }}"
              x-data="{
                skills: {{ json_encode(old('skills', $initialSkills)) }},
                levels: {{ json_encode(old('levels', $initialLevels)) }},
                addSkill() { this.skills.push({ name: '' }); },
                removeSkill(i) { this.skills.splice(i, 1); },
                addLevel() { this.levels.push({ name: '', level_progress: 0, sequence: this.levels.length }); },
                removeLevel(i) { this.levels.splice(i, 1); }
              }">
            @csrf @method('PUT')

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Name <span class="text-red-500">*</span></label>
                        <input type="text" name="name" value="{{ old('name', $skillType->name) }}"
                               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent" required>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">Skills</h3>
                        <button type="button" @click="addSkill()" class="text-xs text-purple-600 hover:text-purple-800 font-medium">+ Add</button>
                    </div>
                    <template x-for="(skill, i) in skills" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="`skills[${i}][name]`" x-model="skill.name" placeholder="Skill name"
                                   class="flex-1 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent">
                            <button type="button" @click="removeSkill(i)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </div>
                    </template>
                    <p x-show="skills.length === 0" class="text-sm text-gray-400 italic">No skills added.</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-semibold text-gray-700">Levels</h3>
                        <button type="button" @click="addLevel()" class="text-xs text-purple-600 hover:text-purple-800 font-medium">+ Add</button>
                    </div>
                    <template x-for="(level, i) in levels" :key="i">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="`levels[${i}][name]`" x-model="level.name" placeholder="Level name"
                                   class="flex-1 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent">
                            <input type="number" :name="`levels[${i}][level_progress]`" x-model="level.level_progress" min="0" max="100" placeholder="%"
                                   class="w-16 border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1 text-sm bg-transparent text-center">
                            <input type="hidden" :name="`levels[${i}][sequence]`" :value="i">
                            <button type="button" @click="removeLevel(i)" class="text-red-400 hover:text-red-600 text-xs">✕</button>
                        </div>
                    </template>
                    <p x-show="levels.length === 0" class="text-sm text-gray-400 italic">No levels added.</p>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection
