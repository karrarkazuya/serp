@extends('layouts.app')
@section('title', __('workflow.new_ticket'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <div class="flex flex-col leading-tight">
            <a href="{{ route('workflow.tickets.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.tickets_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('workflow.new_ticket') }}</span>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('workflow.tickets.index') }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('workflow.discard') }}</a>
            <button form="ticket-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save') }}</button>
        </div>
    </div>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 my-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="ticket-form" method="POST" action="{{ route('workflow.tickets.store') }}">
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

                <div class="px-6 pt-6 pb-2">
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="{{ __('workflow.ticket_title_placeholder') }}"
                           class="w-full text-3xl font-bold text-gray-900 placeholder-gray-300 border-0 border-b-2 border-gray-200 focus:outline-none focus:border-purple-500 pb-2 bg-transparent">
                </div>

                <div class="px-6 py-2">
                    <x-relation-dropdown
                        table="workflow_ticket_templates"
                        field="name"
                        name="ticket_template_id"
                        :label="__('workflow.template_required') . ' *'"
                        :selected="old('ticket_template_id', $selectedTemplate?->id)"
                        relation="many2one"
                    />

                    <div class="flex items-center gap-4 py-2 border-b border-gray-100">
                        <label class="w-32 shrink-0 text-sm text-gray-500">{{ __('workflow.priority_label') }}</label>
                        <select name="priority" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none focus:ring-0 px-0 py-0.5">
                            <option value="1" {{ old('priority', '1') === '1' ? 'selected' : '' }}>{{ __('workflow.priority_normal') }}</option>
                            <option value="2" {{ old('priority', '1') === '2' ? 'selected' : '' }}>{{ __('workflow.priority_medium') }}</option>
                            <option value="3" {{ old('priority', '1') === '3' ? 'selected' : '' }}>{{ __('workflow.priority_high') }}</option>
                        </select>
                    </div>

                    <div class="flex items-start gap-4 py-3 border-b border-gray-100">
                        <label class="w-32 shrink-0 text-sm text-gray-500 pt-1.5">{{ __('common.description') }}</label>
                        <textarea name="description" rows="5" placeholder="{{ __('workflow.describe_ticket') }}"
                                  class="flex-1 text-sm text-gray-800 placeholder-gray-400 bg-transparent border-0 focus:outline-none focus:ring-0 resize-none py-0">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="px-6 py-3 bg-gray-50 rounded-b-xl border-t border-gray-100 flex items-center gap-2">
                    <p class="text-xs text-gray-400">{{ __('workflow.set_fields_after_save') }}</p>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
