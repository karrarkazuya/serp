@extends('layouts.app')
@section('title', __('accounting.section_audit'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('accounting.dashboard') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('accounting.accounting') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ __('accounting.section_audit') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    {{-- Filters --}}
    <div class="shrink-0 bg-white border-b border-gray-200 px-4 py-3">
        <form method="GET" class="flex flex-wrap items-end gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.col_record') }}</label>
                <select name="model_type" class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-0 focus:border-purple-500">
                    <option value="">{{ __('accounting.all_models') }}</option>
                    @foreach($modelLabels as $class => $label)
                    <option value="{{ $class }}" @selected(request('model_type') === $class)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.col_event') }}</label>
                <select name="message_type" class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-0 focus:border-purple-500">
                    <option value="">{{ __('common.all') }}</option>
                    <option value="system" @selected(request('message_type') === 'system')>{{ __('accounting.audit_type_system') }}</option>
                    <option value="log" @selected(request('message_type') === 'log')>{{ __('accounting.audit_type_log') }}</option>
                    <option value="comment" @selected(request('message_type') === 'comment')>{{ __('accounting.audit_type_comment') }}</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.field_date_from') }}</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-0 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('accounting.field_date_to') }}</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-0 focus:border-purple-500">
            </div>
            <div class="flex-1 min-w-50">
                <label class="block text-xs font-semibold text-gray-500 mb-1">{{ __('common.search') }}</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('accounting.ph_search_model') }}"
                       class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 focus:outline-none focus:ring-0 focus:border-purple-500">
            </div>
            <button type="submit" class="px-3 py-1.5 text-sm font-medium text-white bg-[#71639e] hover:bg-[#5c527f] rounded">{{ __('accounting.btn_filter') }}</button>
            <a href="{{ route('accounting.audit') }}" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-300 rounded hover:bg-gray-50">{{ __('common.reset') }}</a>
        </form>
    </div>

    <x-list :paginator="$entries" :empty-text="__('accounting.no_audit')">
        <x-slot:columns>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_date_time') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_record') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_record') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_type') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_user') }}</th>
            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">{{ __('accounting.col_event') }}</th>
        </x-slot:columns>

        @foreach($entries as $entry)
        @php
            $typeColor = match($entry->message_type) {
                'system'  => 'bg-blue-50 text-blue-700',
                'comment' => 'bg-purple-50 text-purple-700',
                default   => 'bg-gray-100 text-gray-600',
            };
        @endphp
        <tr class="hover:bg-gray-50">
            <td class="px-4 py-2.5 text-xs tabular-nums text-gray-500 whitespace-nowrap">{{ $entry->created_at->format('Y-m-d H:i') }}</td>
            <td class="px-4 py-2.5 text-xs text-gray-600">{{ $modelLabels[$entry->model_type] ?? class_basename($entry->model_type) }}</td>
            <td class="px-4 py-2.5 text-xs text-gray-500">#{{ $entry->model_id }}</td>
            <td class="px-4 py-2.5">
                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold {{ $typeColor }}">{{ ucfirst($entry->message_type) }}</span>
            </td>
            <td class="px-4 py-2.5 text-sm text-gray-700">{{ $entry->user?->name ?? '—' }}</td>
            <td class="px-4 py-2.5 text-sm text-gray-700 max-w-md"><span class="line-clamp-2">{{ $entry->body }}</span></td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
