@extends('layouts.app')
@section('title', __('workflow.reports_title'))

@section('content')
<div class="flex min-w-0 flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <span class="text-xl font-semibold text-gray-700">{{ __('workflow.reports_title') }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            @foreach([
                'activity' => __('workflow.activity_report'),
                'procedure-performance' => __('workflow.procedure_performance'),
                'ticket-performance' => __('workflow.ticket_performance'),
                'task-performance' => __('workflow.task_performance'),
            ] as $key => $label)
            <a href="{{ route('workflow.reports.show', $key) }}" class="flex items-center justify-between px-5 py-3 border-b border-gray-100 last:border-b-0 hover:bg-purple-50/30">
                <span class="text-sm font-medium text-gray-800">{{ $label }}</span>
                <span class="text-xl text-gray-300">›</span>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
