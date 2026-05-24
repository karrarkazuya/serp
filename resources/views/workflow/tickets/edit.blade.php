@extends('layouts.app')
@section('title', __('workflow.edit_ticket'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.tickets.show', $ticket) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $ticket->name }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('common.edit') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('workflow.tickets.show', $ticket) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.discard') }}</a>
        <button form="ticket-edit-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="ticket-edit-form" method="POST" action="{{ route('workflow.tickets.update', $ticket) }}">
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
                    <div class="mb-6">
                        <input type="text" name="name" value="{{ old('name', $ticket->name) }}" required placeholder="{{ __('workflow.ticket_title_placeholder') }}"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">{{ __('workflow.priority_label') }}</label>
                        <select name="priority" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                            <option value="1" {{ old('priority', $ticket->priority) === '1' ? 'selected' : '' }}>{{ __('workflow.priority_normal') }}</option>
                            <option value="2" {{ old('priority', $ticket->priority) === '2' ? 'selected' : '' }}>{{ __('workflow.priority_medium') }}</option>
                            <option value="3" {{ old('priority', $ticket->priority) === '3' ? 'selected' : '' }}>{{ __('workflow.priority_high') }}</option>
                        </select>
                    </div>

                    <x-relation-dropdown
                        table="hr_departments"
                        field="name"
                        name="assigned_to_department_id"
                        :label="__('workflow.department_label')"
                        :selected="old('assigned_to_department_id', $ticket->assigned_to_department_id)"
                        relation="many2one"
                    />

                    <x-relation-dropdown
                        table="workflow_users"
                        field="name"
                        name="assigned_to_user_id"
                        :label="__('workflow.assigned_to_label')"
                        :selected="old('assigned_to_user_id', $ticket->assigned_to_user_id)"
                        relation="many2one"
                    />

                    <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500 pt-1">{{ __('common.description') }}</label>
                        <textarea name="description" rows="4" placeholder="{{ __('workflow.describe_ticket') }}"
                                  class="flex-1 text-sm text-gray-800 placeholder-gray-400 border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-y">{{ old('description', $ticket->description) }}</textarea>
                    </div>

                    @if($ticket->inputs->isNotEmpty())
                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">{{ __('workflow.form_fields_label') }}</h3>
                        @foreach($ticket->inputs->filter(fn($i) => $i->template_input_id !== null)->sortBy(fn($i) => $i->templateInput?->sort_order ?? 0) as $idx => $inp)
                        @php $tplInput = $inp->templateInput; @endphp
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500">
                                {{ $inp->name }}@if($inp->is_required)<span class="text-red-400 ml-0.5">*</span>@endif
                            </label>
                            <input type="hidden" name="inputs[{{ $idx }}][template_input_id]" value="{{ $inp->template_input_id }}">
                            @if($inp->type === 'select')
                            <select name="inputs[{{ $idx }}][value]" class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                <option value="">{{ __('workflow.select_option') }}</option>
                                @foreach(($tplInput?->options ?? collect()) as $opt)
                                <option value="{{ $opt->id }}" {{ $inp->value_select_id == $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                @endforeach
                            </select>
                            @elseif($inp->type === 'boolean')
                            <select name="inputs[{{ $idx }}][value]" class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                <option value="0" {{ !$inp->value_boolean ? 'selected' : '' }}>{{ __('common.no') }}</option>
                                <option value="1" {{ $inp->value_boolean ? 'selected' : '' }}>{{ __('common.yes') }}</option>
                            </select>
                            @elseif($inp->type === 'date')
                            <input type="date" name="inputs[{{ $idx }}][value]" value="{{ $inp->value_date?->format('Y-m-d') }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                            @elseif($inp->type === 'datetime')
                            <input type="datetime-local" name="inputs[{{ $idx }}][value]" value="{{ $inp->value_datetime?->format('Y-m-d\TH:i') }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                            @elseif($inp->type === 'int')
                            <input type="number" name="inputs[{{ $idx }}][value]" value="{{ $inp->value_int }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400" placeholder="—">
                            @elseif($inp->type === 'float')
                            <input type="number" step="any" name="inputs[{{ $idx }}][value]" value="{{ $inp->value_float }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400" placeholder="—">
                            @elseif($inp->type === 'textarea')
                            <textarea name="inputs[{{ $idx }}][value]" rows="3"
                                      class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-y"
                                      placeholder="—">{{ $inp->value_text }}</textarea>
                            @elseif($inp->type === 'multiselect')
                            <span class="flex-1 text-sm text-gray-400 italic">
                                {{ $inp->getResultValue() ?: '—' }} <span class="text-xs">{{ __('workflow.multiselect_edit_on_ticket') }}</span>
                            </span>
                            @elseif($inp->type === 'file')
                            <span class="flex-1 text-sm text-gray-400 italic">
                                {{ $inp->value_file_name ?: '—' }} <span class="text-xs">{{ __('workflow.upload_on_ticket') }}</span>
                            </span>
                            @elseif($inp->type === 'label')
                            <span class="flex-1 text-sm text-gray-400 italic">{{ __('workflow.label_only_no_value') }}</span>
                            @else
                            <input type="text" name="inputs[{{ $idx }}][value]" value="{{ $inp->value_char }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400" placeholder="—">
                            @endif
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
