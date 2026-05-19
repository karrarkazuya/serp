@extends('layouts.app')
@section('title', 'New Procedure')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.procedures.index') }}" class="text-xs text-purple-600 hover:text-purple-700">Procedures</a>
            <span class="text-sm font-semibold text-gray-800">New Procedure</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('workflow.procedures.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="procedure-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="procedure-form" method="POST" action="{{ route('workflow.procedures.store') }}" x-data="procForm()" @procedure-template-selected="templateId = String($event.detail.value || ''); templateDescription = templates[templateId] ?? ''">
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
                        <input type="text" name="name" value="{{ old('name') }}" required placeholder="Procedure Name"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <x-relation-dropdown
                        table="workflow_procedure_templates"
                        field="name"
                        name="procedure_template_id"
                        label="Template *"
                        :selected="old('procedure_template_id', $selectedTemplate?->id)"
                        relation="many2one"
                        event="procedure-template-selected"
                    />

                    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100" x-show="templateDescription" style="display:none">
                        <label class="w-36 shrink-0 text-sm text-gray-500"></label>
                        <p class="flex-1 text-xs text-gray-400" x-text="templateDescription"></p>
                    </div>

                    <div class="mt-6 border-t border-gray-200" x-data="{ tab: 'description' }">
                        <div class="flex items-end gap-1 pt-3 border-b border-gray-200">
                            <button type="button" @click="tab = 'description'"
                                    class="px-4 py-2 text-sm font-semibold border border-b-0 rounded-t bg-white"
                                    :class="tab === 'description' ? 'text-gray-900 border-gray-300 -mb-px pb-[9px]' : 'text-[#714B67] border-transparent hover:text-[#5c3d55]'">
                                Description
                            </button>
                        </div>

                        <div class="min-h-36">
                            <div x-show="tab === 'description'" style="display:none">
                                <textarea name="description" rows="5" placeholder="Describe the procedure..."
                                          class="w-full px-4 py-4 border-0 text-sm focus:outline-none focus:ring-0 resize-y text-gray-800 placeholder-gray-400">{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function procForm() {
    const templates = @json($templates->pluck('description', 'id'));
    return {
        templates,
        templateId: '{{ old('procedure_template_id', $selectedTemplate?->id) ?? '' }}',
        templateDescription: templates['{{ old('procedure_template_id', $selectedTemplate?->id) ?? '' }}'] ?? '',
    };
}
</script>
@endsection
