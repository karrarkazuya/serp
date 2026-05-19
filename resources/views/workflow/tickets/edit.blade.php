@extends('layouts.app')
@section('title', 'Edit Ticket')

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.tickets.show', $ticket) }}" class="text-xs text-purple-600 hover:text-purple-700">{{ $ticket->name }}</a>
            <span class="text-sm font-semibold text-gray-800">Edit</span>
        </div>
        <div class="ml-auto flex items-center gap-2">
            <a href="{{ route('workflow.tickets.show', $ticket) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">Discard</a>
            <button form="ticket-edit-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">Save</button>
        </div>
    </div>

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
                        <input type="text" name="name" value="{{ old('name', $ticket->name) }}" required placeholder="Ticket Title"
                               class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-1 bg-transparent">
                    </div>

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500">Priority</label>
                        <select name="priority" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                            @foreach(['1' => 'Normal', '2' => 'Medium', '3' => 'High'] as $v => $l)
                            <option value="{{ $v }}" {{ old('priority', $ticket->priority) === $v ? 'selected' : '' }}>{{ $l }}</option>
                            @endforeach
                        </select>
                    </div>

                    <x-relation-dropdown
                        table="workflow_departments"
                        field="name"
                        name="assigned_to_department_id"
                        label="Department"
                        :selected="old('assigned_to_department_id', $ticket->assigned_to_department_id)"
                        relation="many2one"
                    />

                    <x-relation-dropdown
                        table="workflow_users"
                        field="name"
                        name="assigned_to_user_id"
                        label="Assigned To"
                        :selected="old('assigned_to_user_id', $ticket->assigned_to_user_id)"
                        relation="many2one"
                    />

                    <div class="flex items-start gap-4 py-2 border-b border-gray-100">
                        <label class="w-36 shrink-0 text-sm text-gray-500 pt-1">Description</label>
                        <textarea name="description" rows="4" placeholder="Describe the ticket..."
                                  class="flex-1 text-sm text-gray-800 placeholder-gray-400 border border-gray-200 rounded px-3 py-2 focus:outline-none focus:ring-1 focus:ring-purple-400 resize-y">{{ old('description', $ticket->description) }}</textarea>
                    </div>

                    @if($ticket->template && $ticket->template->inputs->isNotEmpty())
                    <div class="mt-6 border-t border-gray-200 pt-4">
                        <h3 class="text-sm font-semibold text-gray-700 mb-3">Form Fields</h3>
                        @foreach($ticket->template->inputs->sortBy('sort_order') as $i => $input)
                        @php $existing = $ticket->inputs->firstWhere('template_input_id', $input->id); @endphp
                        <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                            <label class="w-36 shrink-0 text-sm text-gray-500">
                                {{ $input->name }}@if($input->is_required)<span class="text-red-400 ml-0.5">*</span>@endif
                            </label>
                            <input type="hidden" name="inputs[{{ $i }}][template_input_id]" value="{{ $input->id }}">
                            @if($input->type === 'select')
                            <select name="inputs[{{ $i }}][value]" class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                <option value="">— Select —</option>
                                @foreach($input->options as $opt)
                                <option value="{{ $opt->id }}" {{ $existing?->value_select_id == $opt->id ? 'selected' : '' }}>{{ $opt->name }}</option>
                                @endforeach
                            </select>
                            @elseif($input->type === 'boolean')
                            <select name="inputs[{{ $i }}][value]" class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                                <option value="0" {{ !$existing?->value_boolean ? 'selected' : '' }}>No</option>
                                <option value="1" {{ $existing?->value_boolean ? 'selected' : '' }}>Yes</option>
                            </select>
                            @elseif($input->type === 'date')
                            <input type="date" name="inputs[{{ $i }}][value]" value="{{ $existing?->value_date?->format('Y-m-d') }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                            @elseif($input->type === 'datetime')
                            <input type="datetime-local" name="inputs[{{ $i }}][value]" value="{{ $existing?->value_datetime?->format('Y-m-d\TH:i') }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400">
                            @elseif($input->type === 'int')
                            <input type="number" name="inputs[{{ $i }}][value]" value="{{ $existing?->value_int }}"
                                   class="flex-1 text-sm text-gray-800 border border-gray-200 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-purple-400" placeholder="—">
                            @elseif($input->type === 'label')
                            <span class="flex-1 text-sm text-gray-400 italic">Label only — no value</span>
                            @else
                            <input type="text" name="inputs[{{ $i }}][value]" value="{{ $existing?->value_char }}"
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
