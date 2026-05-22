@extends('layouts.app')
@section('title', $procedureTemplate->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50" x-data="{ tab: 'flowchart' }">

    {{-- Header --}}
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.config.procedure-templates.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.procedure_templates_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $procedureTemplate->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        @can('update', $procedureTemplate)
        <a href="{{ route('workflow.config.procedure-templates.edit', $procedureTemplate) }}" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
        @endcan
        @can('create', \App\Models\Workflow\Procedure::class)
        <a href="{{ route('workflow.procedures.create', ['template_id' => $procedureTemplate->id]) }}"
           class="px-3 py-1.5 text-sm bg-[#714B67] text-white rounded hover:bg-[#5c3d55]">{{ __('workflow.start_procedure') }}</a>
        @endcan
    </div>
        </x-slot:actions>
    </x-toolbar>

    @if(session('success'))
    <div class="mx-4 mt-3 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg shrink-0">{{ session('success') }}</div>
    @endif

    {{-- Body: sidebar + right panel --}}
    <div class="flex flex-1 min-h-0 gap-0">

        {{-- Left sidebar --}}
        <div class="w-72 shrink-0 overflow-y-auto border-r border-gray-200 bg-white p-4 space-y-4">

            {{-- Meta card --}}
            <div class="bg-gray-50 rounded-xl border border-gray-200">
                <div class="px-4 py-3 border-b border-gray-100">
                    <p class="text-sm font-semibold text-gray-800">{{ $procedureTemplate->name }}</p>
                    @if($procedureTemplate->description)
                    <p class="text-xs text-gray-500 mt-1 leading-relaxed">{{ $procedureTemplate->description }}</p>
                    @endif
                </div>
                <div class="px-4 py-2.5 space-y-2">
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-gray-400 shrink-0">{{ __('workflow.default_group_label') }}</span>
                        <span class="text-gray-700 font-medium truncate">{{ $procedureTemplate->defaultGroup?->name ?? '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-gray-400 shrink-0">{{ __('workflow.sla_hrs_label') }}</span>
                        <span class="text-gray-700 font-medium">{{ $procedureTemplate->resolve_max_duration ? $procedureTemplate->resolve_max_duration . 'h' : '—' }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-gray-400 shrink-0">{{ __('workflow.creator_sees_tickets') }}</span>
                        <span class="text-gray-700 font-medium">{{ $procedureTemplate->creator_see_tasks ? __('workflow.yes_label') : __('workflow.no_label') }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2 text-xs">
                        <span class="text-gray-400 shrink-0">{{ __('common.status') }}</span>
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium {{ $procedureTemplate->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                            {{ $procedureTemplate->enabled ? __('workflow.enabled_text') : __('workflow.disabled_text') }}
                        </span>
                    </div>
                    @if($procedureTemplate->departments->isNotEmpty())
                    <div class="flex items-start justify-between gap-2 text-xs">
                        <span class="text-gray-400 shrink-0">{{ __('workflow.departments_label') }}</span>
                        <span class="text-gray-700 font-medium text-right">{{ $procedureTemplate->departments->pluck('name')->join(', ') }}</span>
                    </div>
                    @endif
                </div>
                <div class="px-4 py-2.5 border-t border-gray-100 text-xs text-gray-400">
                    {{ __('common.created') }} {{ $procedureTemplate->created_at->format('M d, Y') }}
                </div>
            </div>

            {{-- Steps list --}}
            <div class="bg-gray-50 rounded-xl border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <span class="text-sm font-semibold text-gray-800">{{ __('workflow.show_steps') }}</span>
                    <span class="text-xs text-gray-400">{{ $procedureTemplate->steps->count() }} {{ __('workflow.steps_count') }}</span>
                </div>
                @if($procedureTemplate->steps->isNotEmpty())
                <div class="divide-y divide-gray-100">
                    @foreach($procedureTemplate->steps->sortBy('id') as $step)
                    <div class="px-4 py-3 flex items-start gap-2.5">
                        <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-purple-300 shrink-0"></span>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-medium text-gray-800 leading-snug">{{ $step->name }}</p>
                            @if($step->description)
                            <p class="text-[11px] text-gray-500 mt-0.5 leading-snug">{{ Str::limit($step->description, 60) }}</p>
                            @endif
                            <div class="flex items-center gap-2 mt-1 flex-wrap">
                                @if($step->inputs->isNotEmpty())
                                <span class="text-[10px] text-gray-400">{{ $step->inputs->count() }} {{ __('workflow.fields_count') }}</span>
                                @endif
                                @if($step->nextSteps->isNotEmpty())
                                <span class="text-[10px] text-blue-400 truncate">→ {{ $step->nextSteps->pluck('name')->join(', ') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>
                @else
                <div class="px-4 py-8 text-center text-xs text-gray-400">{{ __('workflow.no_steps_defined') }}</div>
                @endif
            </div>

        </div>

        {{-- Right panel: tabs --}}
        <div class="flex-1 min-w-0 flex flex-col min-h-0">

            {{-- Tab bar --}}
            <div class="bg-white border-b border-gray-200 px-4 flex items-center gap-0 shrink-0">
                <button type="button"
                    @click="tab = 'flowchart'"
                    :class="tab === 'flowchart' ? 'border-b-2 border-[#714B67] text-[#714B67]' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2.5 text-sm font-medium transition-colors">
                    {{ __('workflow.flowchart_tab') }}
                </button>
                <button type="button"
                    @click="tab = 'activity'"
                    :class="tab === 'activity' ? 'border-b-2 border-[#714B67] text-[#714B67]' : 'text-gray-500 hover:text-gray-700'"
                    class="px-4 py-2.5 text-sm font-medium transition-colors">
                    {{ __('common.activity_tab') }}
                </button>
            </div>

            {{-- Flowchart tab --}}
            <div x-show="tab === 'flowchart'" class="flex-1 min-h-0">
                <iframe src="{{ $flowchartUrl }}"
                    class="w-full h-full border-0"
                    title="Procedure Flowchart">
                </iframe>
            </div>

            {{-- Activity tab --}}
            <div x-show="tab === 'activity'" class="flex-1 min-h-0 overflow-y-auto p-4" style="display:none">
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <x-chatter
                        model-type="App\Models\Workflow\ProcedureTemplate"
                        :model-id="$procedureTemplate->id"
                        :can-comment="auth()->user()->can('comment', $procedureTemplate)"
                    />
                </div>
            </div>

        </div>

    </div>
</div>
@endsection
