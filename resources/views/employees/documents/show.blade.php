@extends('layouts.app')
@section('title', $document->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.documents.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.documents_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $document->name }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
        <div class="flex items-center gap-2">
            @can('update', \App\Models\Employees\Employee::class)
            <a href="{{ route('employees.documents.edit', $document) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan

            @can('update', \App\Models\Employees\Employee::class)
            <form method="POST" action="{{ $document->active ? route('employees.documents.archive', $document) : route('employees.documents.unarchive', $document) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    {{ $document->active ? __('common.archive') : __('common.unarchive') }}
                </button>
            </form>
            @endcan

            @can('delete', \App\Models\Employees\Employee::class)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.documents.delete', $document) }}">
                        @csrf @method('DELETE')
                        <button type="submit" class="px-2 py-1 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                    </form>
                    <button type="button" @click="confirming = false" class="px-2 py-1 text-xs text-gray-500 border border-gray-300 rounded">{{ __('common.cancel') }}</button>
                </div>
            </div>
            @endcan
        </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto p-4 space-y-4">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            @if(!$document->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('common.archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_name') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $document->name }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_employee') }}</p>
                    @if($document->employee)
                        <a href="{{ route('employees.show', $document->employee) }}" class="text-sm text-purple-600 hover:underline mt-0.5 inline-block">
                            {{ $document->employee->name }}
                        </a>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_type') }}</p>
                    @php $typeLabels = ['contract'=>__('employees.doc_contract'),'id_card'=>__('employees.doc_id_card'),'passport'=>__('employees.doc_passport'),'certificate'=>__('employees.doc_certificate'),'resume'=>__('employees.doc_resume'),'medical'=>__('employees.doc_medical'),'other'=>__('employees.doc_other')]; @endphp
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold bg-gray-100 text-gray-600 uppercase mt-0.5">
                        {{ $typeLabels[$document->document_type] ?? $document->document_type }}
                    </span>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issued_by') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $document->issued_by ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.document_number') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $document->document_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.organizational_structure') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $document->organizational_structure ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issue_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $document->issue_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.expiry_date') }}</p>
                    <p class="text-sm mt-0.5 {{ $document->is_expired ? 'text-red-600 font-semibold' : ($document->is_expiring_soon ? 'text-amber-600' : 'text-gray-900') }}">
                        {{ $document->expiry_date?->format('d M Y') ?? '—' }}
                        @if($document->is_expired) ({{ __('employees.doc_expired') }})
                        @elseif($document->is_expiring_soon) ({{ __('employees.doc_soon') }})
                        @endif
                    </p>
                </div>
                @if($document->notify_before_days)
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.notify_before') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $document->notify_before_days }} {{ __('employees.days') }}</p>
                </div>
                @endif
                @if($document->notes)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('common.notes') }}</p>
                    <p class="text-sm text-gray-700 mt-0.5 whitespace-pre-line">{{ $document->notes }}</p>
                </div>
                @endif
            </div>

            @if($document->file_path)
            <div class="mt-6 pt-4 border-t border-gray-100">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">{{ __('employees.file') }}</p>
                <a href="{{ route('files.serve', $document->file_path) }}" target="_blank"
                   class="inline-flex items-center gap-2 px-3 py-1.5 text-sm text-purple-700 border border-purple-200 rounded hover:bg-purple-50">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                    </svg>
                    {{ __('employees.download_file') }}
                </a>
            </div>
            @endif
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm text-xs text-gray-400 px-5 py-3 flex gap-6">
            @if($document->creator)
            <span>{{ __('common.created_by') }}: <span class="text-gray-600">{{ $document->creator->name }}</span></span>
            @endif
            @if($document->created_at)
            <span>{{ __('common.created_at') }}: <span class="text-gray-600">{{ $document->created_at->format('d M Y') }}</span></span>
            @endif
            @if($document->updater)
            <span>{{ __('common.updated_by') }}: <span class="text-gray-600">{{ $document->updater->name }}</span></span>
            @endif
        </div>
    </div>
</div>
@endsection
