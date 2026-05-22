@extends('layouts.app')
@section('title', $skillType->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.skill-types.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.skill_types_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $skillType->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', $skillType)
        <a href="{{ route('employees.skill-types.edit', $skillType) }}"
           class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>

        @if($skillType->active)
        <form method="POST" action="{{ route('employees.skill-types.archive', $skillType) }}">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.archive') }}</button>
        </form>
        @else
        <form method="POST" action="{{ route('employees.skill-types.unarchive', $skillType) }}">
            @csrf @method('PATCH')
            <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.unarchive') }}</button>
        </form>
        @endif
        @endcan

        @can('delete', $skillType)
        <div x-data="{ confirming: false }">
            <button type="button" x-show="!confirming" @click="confirming = true"
                    class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
            <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                <form method="POST" action="{{ route('employees.skill-types.delete', $skillType) }}">
                    @csrf @method('DELETE')
                    <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                </form>
                <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">{{ __('common.cancel') }}</button>
            </div>
        </div>
        @endcan
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-3 text-sm text-green-700">{{ session('success') }}</div>
        @endif

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ $skillType->name }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.name') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $skillType->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.status') }}</p>
                    <p class="text-sm mt-0.5">
                        @if($skillType->active)
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">{{ __('common.active') }}</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-500">{{ __('common.archived') }}</span>
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('employees.skills_section') }} ({{ $skillType->skills->count() }})</h3>
                @if($skillType->skills->isEmpty())
                    <p class="text-sm text-gray-400 italic">{{ __('employees.no_skills_defined') }}</p>
                @else
                    <ul class="space-y-1">
                        @foreach($skillType->skills as $skill)
                            <li class="text-sm text-gray-800 py-1 border-b border-gray-100 last:border-0">{{ $skill->name }}</li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('employees.levels_section') }} ({{ $skillType->levels->count() }})</h3>
                @if($skillType->levels->isEmpty())
                    <p class="text-sm text-gray-400 italic">{{ __('employees.no_levels_defined') }}</p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-xs text-gray-400 uppercase">
                                <th class="text-left pb-1">{{ __('common.name') }}</th>
                                <th class="text-right pb-1">{{ __('employees.progress') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($skillType->levels as $level)
                            <tr class="border-t border-gray-100">
                                <td class="py-1 text-gray-800">{{ $level->name }}</td>
                                <td class="py-1 text-right text-gray-500">{{ $level->level_progress }}%</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\SkillType"
                :model-id="$skillType->id"
                :can-comment="auth()->user()->can('comment', $skillType)"
                :comment-url="route('employees.skill-types.comment', $skillType)"
            />
        </div>
    </div>
</div>
@endsection
