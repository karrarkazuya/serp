@extends('layouts.app')
@section('title', __('workflow.new_procedure_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">

    {{-- Top bar --}}
    <div class="bg-white border-b border-gray-200 px-5 py-2 flex items-center gap-3 shrink-0">
        <div class="flex items-center gap-2 shrink-0">
            <div>
                <a href="{{ route('workflow.procedures.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.procedures_title') }}</a>
                <span class="text-sm font-semibold text-gray-800 block">{{ __('workflow.new_procedure_title') }}</span>
            </div>
        </div>
        <div class="ml-auto flex items-center gap-2 shrink-0">
            <a href="{{ route('workflow.procedures.index') }}"
               class="px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded hover:bg-gray-50 transition-colors">
                {{ __('workflow.discard') }}
            </a>
            <button form="procedure-form" type="submit"
                    class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm transition-colors">
                {{ __('workflow.start_procedure') }}
            </button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="p-5"
             x-data="procForm()"
             @procedure-template-selected="handleSelect($event)">

            {{-- Validation errors --}}
            @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif

            <div class="flex gap-5 items-start">

                {{-- LEFT: form --}}
                <div class="flex-1 min-w-0 bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <form id="procedure-form" method="POST" action="{{ route('workflow.procedures.store') }}">
                        @csrf

                        {{-- Name --}}
                        <div class="px-7 pt-6 pb-5 border-b border-gray-100">
                            <input type="text" name="name" value="{{ old('name') }}" required
                                   placeholder="{{ __('workflow.procedure_name_placeholder') }}"
                                   autofocus
                                   class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 focus:outline-none focus:ring-0 bg-transparent">
                            <p class="text-xs text-gray-400 mt-1.5">{{ __('workflow.give_procedure_name') }}</p>
                        </div>

                        {{-- Template --}}
                        <div class="px-7 py-5 border-b border-gray-100">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-3">
                                {{ __('workflow.template_required') }} <span class="text-red-400 ml-0.5">*</span>
                            </label>
                            <x-relation-dropdown
                                table="workflow_procedure_templates"
                                field="name"
                                name="procedure_template_id"
                                label=""
                                :selected="old('procedure_template_id', $selectedTemplate?->id)"
                                relation="many2one"
                                event="procedure-template-selected"
                            />
                            <p class="text-xs text-gray-400 mt-2">{{ __('workflow.template_defines_steps') }}</p>
                        </div>

                        {{-- Description --}}
                        <div class="px-7 py-5">
                            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide block mb-3">{{ __('workflow.description_label') }}</label>
                            <textarea name="description" rows="5"
                                      placeholder="{{ __('workflow.describe_procedure') }}"
                                      class="w-full text-sm text-gray-700 border border-gray-200 rounded-lg px-3 py-2.5 focus:outline-none focus:ring-1 focus:ring-[#714B67] resize-none placeholder-gray-300">{{ old('description') }}</textarea>
                        </div>

                    </form>
                </div>
                {{-- END LEFT --}}

                {{-- RIGHT: template preview --}}
                <div class="w-72 shrink-0">
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm sticky top-5 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <span class="text-sm font-semibold text-gray-800">{{ __('workflow.template_preview') }}</span>
                        </div>

                        {{-- Empty state --}}
                        <div x-show="!selectedId" style="display:none" class="px-4 py-10 text-center">
                            <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="w-5 h-5 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-400">{{ __('workflow.select_a_template') }}</p>
                            <p class="text-xs text-gray-300 mt-0.5">{{ __('workflow.details_will_appear') }}</p>
                        </div>

                        {{-- Template details --}}
                        <div x-show="selectedId" class="divide-y divide-gray-50">

                            {{-- Description --}}
                            <div class="px-4 py-3" x-show="tpl && tpl.description">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1.5">{{ __('workflow.about_label') }}</p>
                                <p class="text-xs text-gray-600 leading-relaxed" x-text="tpl?.description"></p>
                            </div>

                            {{-- Steps --}}
                            <div class="px-4 py-3" x-show="tpl && tpl.steps && tpl.steps.length > 0">
                                <div class="flex items-center justify-between mb-2.5">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('workflow.steps_label') }}</p>
                                    <span class="text-xs text-gray-400" x-text="tpl?.steps?.length + ' steps'"></span>
                                </div>
                                <div class="space-y-0">
                                    <template x-for="(step, i) in tpl?.steps ?? []" :key="i">
                                        <div class="flex items-stretch gap-2.5 group">
                                            <div class="flex flex-col items-center shrink-0 w-4">
                                                <div class="w-2 h-2 rounded-full bg-gray-300 group-hover:bg-[#714B67]/50 mt-1.5 shrink-0 transition-colors"></div>
                                                <div class="w-px flex-1 min-h-2 mt-1 bg-gray-100" x-show="i < (tpl?.steps?.length ?? 0) - 1"></div>
                                            </div>
                                            <div class="flex-1 min-w-0 pb-2.5">
                                                <p class="text-xs font-medium text-gray-700 leading-snug" x-text="step.name"></p>
                                                <p class="text-[11px] text-gray-400 mt-0.5 truncate" x-show="step.department" x-text="step.department"></p>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            {{-- No description or steps --}}
                            <div class="px-4 py-6 text-center" x-show="tpl && !tpl.description && (!tpl.steps || tpl.steps.length === 0)">
                                <p class="text-xs text-gray-400">{{ __('workflow.no_template_details') }}</p>
                            </div>

                        </div>
                    </div>
                </div>
                {{-- END RIGHT --}}

            </div>
        </div>
    </div>
</div>

@php
$templateData = $templates->mapWithKeys(function ($t) {
    return [
        $t->id => [
            'description' => $t->description,
            'steps' => $t->steps->map(function ($s) {
                return [
                    'name'       => $s->name,
                    'department' => $s->defaultDepartment?->name ?? null,
                ];
            })->values()->all(),
        ],
    ];
});
$templateInitId = old('procedure_template_id', $selectedTemplate?->id ?? '');
@endphp
<script>
function procForm() {
    const data = @json($templateData);

    const initId = @json($templateInitId);

    return {
        selectedId: initId ? String(initId) : '',
        get tpl() { return this.selectedId ? (data[this.selectedId] ?? null) : null; },
        handleSelect(e) {
            this.selectedId = e.detail.value ? String(e.detail.value) : '';
        },
    };
}
</script>
@endsection
