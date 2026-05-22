@extends('layouts.app')
@section('title', $reportTitle)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('workflow.reports.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('workflow.reports_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $reportTitle }}</span>
        </x-slot:breadcrumb>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-4">{{ $reportTitle }}</h1>
            <p class="text-sm text-gray-500">//to do port the Odoo {{ $reportKey }} report client action.</p>
        </div>
    </div>
</div>
@endsection
