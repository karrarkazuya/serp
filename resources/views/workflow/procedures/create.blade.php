@extends('layouts.app')
@section('title', __('workflow.new_procedure_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">

    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.procedures.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.procedures_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('workflow.new_procedure_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('workflow.procedures.index') }}"
                   class="px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded hover:bg-gray-50 transition-colors">
                    {{ __('workflow.discard') }}
                </a>
                <button form="procedure-form" type="submit"
                        class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm transition-colors">
                    {{ __('workflow.start_procedure') }}
                </button>
            </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="mx-4 my-4"
             x-data="procForm()"
             @procedure-template-selected="handleSelect($event)">

            @if($errors->any())
            <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
            @endif

            <form id="procedure-form" method="POST" action="{{ route('workflow.procedures.store') }}">
                @csrf

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm">

                    {{-- Title + Description --}}
                    <div class="px-6 pt-6 pb-5 border-b border-gray-100">
                        <input type="text" name="name" value="{{ old('name') }}" required
                               placeholder="{{ __('workflow.procedure_name_placeholder') }}"
                               autofocus
                               class="w-full text-2xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b border-gray-100 focus:outline-none focus:border-[#714B67] pb-2 bg-transparent transition-colors">
                        <textarea name="description" rows="1"
                                  placeholder="{{ __('workflow.describe_procedure') }}"
                                  @input="$el.style.height = 'auto'; $el.style.height = $el.scrollHeight + 'px'"
                                  class="w-full mt-2 text-sm text-gray-500 placeholder-gray-300 bg-transparent border-0 focus:outline-none focus:ring-0 resize-none overflow-hidden">{{ old('description') }}</textarea>
                    </div>

                    {{-- Template --}}
                    <div class="px-6">
                        <x-relation-dropdown
                            table="workflow_procedure_templates"
                            field="name"
                            name="procedure_template_id"
                            :label="__('workflow.template_required')"
                            :selected="old('procedure_template_id', $selectedTemplate?->id)"
                            relation="many2one"
                            event="procedure-template-selected"
                        />

                        {{-- Inline template preview (indented to align under the input) --}}
                        <div x-show="selectedId" x-cloak class="ml-36 mb-4 rounded-lg border border-gray-200 bg-gray-50 overflow-hidden">

                            <div x-show="tpl && tpl.description" class="px-3 py-2.5 border-b border-gray-100">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1">{{ __('workflow.about_label') }}</p>
                                <p class="text-xs text-gray-600 leading-relaxed" x-text="tpl?.description"></p>
                            </div>

                            <div x-show="tpl && tpl.steps && tpl.steps.length > 0" class="px-3 py-2.5">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">{{ __('workflow.steps_label') }}</p>
                                    <span class="text-[10px] text-gray-400 bg-white border border-gray-200 rounded-full px-2 py-0.5"
                                          x-text="tpl?.steps?.length + ' {{ __('workflow.steps_count') }}'"></span>
                                </div>
                                <template x-for="(step, i) in tpl?.steps ?? []" :key="i">
                                    <div class="flex items-stretch gap-2">
                                        <div class="flex flex-col items-center shrink-0 w-3.5">
                                            <div class="w-1.5 h-1.5 rounded-full bg-[#714B67]/40 mt-1.5 shrink-0"></div>
                                            <div class="w-px flex-1 min-h-2 mt-0.5 bg-gray-200" x-show="i < (tpl?.steps?.length ?? 0) - 1"></div>
                                        </div>
                                        <div class="flex-1 min-w-0 pb-2">
                                            <p class="text-xs font-medium text-gray-700" x-text="step.name"></p>
                                            <p class="text-[11px] text-gray-400" x-show="step.department" x-text="step.department"></p>
                                        </div>
                                    </div>
                                </template>
                            </div>

                            <div x-show="tpl && !tpl.description && (!tpl.steps || tpl.steps.length === 0)" class="px-3 py-3 text-center">
                                <p class="text-xs text-gray-400">{{ __('workflow.no_template_details') }}</p>
                            </div>

                        </div>
                    </div>

                </div>
            </form>
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
