@extends('layouts.app')
@section('title', __('workflow.settings_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <div class="bg-white border-b border-gray-200 px-4 py-2 flex items-center gap-3 shrink-0">
        <span class="text-xl font-semibold text-gray-700">{{ __('workflow.settings_title') }}</span>
    </div>

    <div class="flex-1 overflow-y-auto p-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ __('workflow.settings_title') }}</h1>
            <p class="text-sm text-gray-500 mb-4">//to do port workflow-specific res.config.settings fields from the Odoo module.</p>
            <a href="{{ route('settings.index') }}" class="inline-flex px-3 py-1.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-50">
                {{ __('workflow.open_general_settings') }}
            </a>
        </div>
    </div>
</div>
@endsection
