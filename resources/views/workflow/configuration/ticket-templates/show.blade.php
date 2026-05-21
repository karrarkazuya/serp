@extends('layouts.app')
@section('title', $ticketTemplate->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.config.ticket-templates.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.ticket_templates_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $ticketTemplate->name }}</span>
        </div>
        <div class="flex items-center gap-2">
            @can('update', $ticketTemplate)
            <a href="{{ route('workflow.config.ticket-templates.edit', $ticketTemplate) }}" class="px-3 py-1.5 text-sm border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan
            @can('create', \App\Models\Workflow\Ticket::class)
            <a href="{{ route('workflow.tickets.create', ['template_id' => $ticketTemplate->id]) }}"
               class="px-3 py-1.5 text-sm bg-[#714B67] text-white rounded hover:bg-[#5c3d55]">{{ __('workflow.create_ticket_btn') }}</a>
            @endcan
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        @if(session('success'))
        <div class="mx-4 mt-4 px-4 py-2 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">{{ session('success') }}</div>
        @endif

        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <div class="px-6 py-5">
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">{{ $ticketTemplate->name }}</h1>
                    @if($ticketTemplate->description)
                    <p class="text-sm text-gray-500 mt-2">{{ $ticketTemplate->description }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.default_group_label') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $ticketTemplate->defaultGroup?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.default_dept_label2') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $ticketTemplate->defaultDepartment?->name ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.sla_hours_label') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $ticketTemplate->resolve_max_duration ?? '—' }}</span>
                </div>
                <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.enabled_label') }}</label>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $ticketTemplate->enabled ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' }}">
                        {{ $ticketTemplate->enabled ? __('workflow.yes_label') : __('workflow.no_label') }}
                    </span>
                </div>
                <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                    <label class="w-36 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('workflow.departments_label') }}</label>
                    <span class="flex-1 text-sm text-gray-800">{{ $ticketTemplate->departments->pluck('name')->join(', ') ?: '—' }}</span>
                </div>
            </div>
        </div>

        @if($ticketTemplate->inputs->isNotEmpty())
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="px-5 py-3 border-b border-gray-100">
                <span class="text-sm font-semibold text-gray-800">{{ __('workflow.form_fields_label') }}</span>
            </div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50/70">
                        <th class="px-5 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('workflow.field_name_col') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('workflow.field_type_col') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('workflow.required_col') }}</th>
                        <th class="px-4 py-2.5 text-left text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('workflow.options_col') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($ticketTemplate->inputs->sortBy('sort_order') as $input)
                    <tr class="border-b border-gray-50">
                        <td class="px-5 py-2.5 text-gray-800 font-medium">{{ $input->name }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $input->type }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $input->is_required ? __('workflow.yes_label') : __('workflow.no_label') }}</td>
                        <td class="px-4 py-2.5 text-gray-600">{{ $input->options->pluck('name')->join(', ') ?: '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @else
        <div class="mb-4"></div>
        @endif

        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Workflow\TicketTemplate"
                :model-id="$ticketTemplate->id"
                :can-comment="auth()->user()->can('comment', $ticketTemplate)"
            />
        </div>
    </div>
</div>
@endsection
