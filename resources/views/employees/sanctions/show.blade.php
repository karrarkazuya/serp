@extends('layouts.app')
@section('title', $sanction->name ?? __('employees.sanctions_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.sanctions.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.sanctions_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $sanction->name ?? __('employees.sanctions_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
        <div class="flex items-center gap-2">
            @can('update', \App\Models\Employees\Employee::class)
            <a href="{{ route('employees.sanctions.edit', $sanction) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan

            @can('update', \App\Models\Employees\Employee::class)
            <form method="POST" action="{{ $sanction->active ? route('employees.sanctions.archive', $sanction) : route('employees.sanctions.unarchive', $sanction) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    {{ $sanction->active ? __('common.archive') : __('common.unarchive') }}
                </button>
            </form>
            @endcan

            @can('delete', \App\Models\Employees\Employee::class)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.sanctions.delete', $sanction) }}">
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
            @if(!$sanction->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('common.archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_name') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $sanction->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->document_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issued_by') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->issued_by ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.document_number') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->document_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.organizational_structure') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->organizational_structure ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.assignment_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->assignment_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.data_status') }}</p>
                    @if($sanction->data_status)
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold mt-0.5 {{ $sanction->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $sanction->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                    </span>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.financial_specialization') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->financial_specialization ? number_format($sanction->financial_specialization, 2) : '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.affective_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->affective_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issue_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->issue_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.expiry_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->expiry_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.notify_before_days') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $sanction->notify_before_days ?? '—' }}</p>
                </div>
                @if($sanction->notes)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.notes') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5 whitespace-pre-wrap">{{ $sanction->notes }}</p>
                </div>
                @endif
                @if($sanction->attachedFile)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_file') }}</p>
                    <div class="mt-1 flex items-center gap-3">
                        <a href="{{ route('files.serve', $sanction->file_path) }}" target="_blank"
                           class="text-sm text-purple-600 hover:underline flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            {{ $sanction->attachedFile->original_name ?? __('employees.doc_file') }}
                        </a>
                        @can('update', \App\Models\Employees\Employee::class)
                        <form method="POST" action="{{ route('employees.sanctions.document.delete', $sanction) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">{{ __('common.delete') }}</button>
                        </form>
                        @endcan
                    </div>
                    @can('update', \App\Models\Employees\Employee::class)
                    <form method="POST" action="{{ route('employees.sanctions.document.replace', $sanction) }}" enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
                        @csrf
                        <input type="file" name="file" class="text-xs text-gray-700">
                        <button type="submit" class="px-2 py-1 text-xs bg-gray-100 border border-gray-300 rounded hover:bg-gray-200">{{ __('common.save_short') }}</button>
                    </form>
                    @endcan
                </div>
                @endif
            </div>
        </div>

        {{-- Employees card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
            <form method="POST" action="{{ route('employees.sanctions.employees.sync', $sanction) }}">
                @csrf @method('PUT')
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.doc_employee') }}</h3>
                    @can('update', \App\Models\Employees\Employee::class)
                    <button type="submit" class="px-3 py-1 text-xs font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">
                        {{ __('common.save_short') }}
                    </button>
                    @endcan
                </div>
                <x-relation-dropdown
                    name="employee_ids"
                    table="hr_employees"
                    field="name"
                    relation="many2many"
                    :selected="$sanction->employees->pluck('id')->all()"
                    placeholder="{{ __('employees.select_employee') }}"
                    compact
                />
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\EmployeeSanction"
                :model-id="$sanction->id"
                :can-comment="auth()->user()->can('update', \App\Models\Employees\Employee::class)"
                :comment-url="route('employees.sanctions.comment', $sanction)"
            />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm text-xs text-gray-400 px-5 py-3 flex gap-6">
            @if($sanction->creator)
            <span>{{ __('common.created_by') }}: <span class="text-gray-600">{{ $sanction->creator->name }}</span></span>
            @endif
            @if($sanction->created_at)
            <span>{{ __('common.created_at') }}: <span class="text-gray-600">{{ $sanction->created_at->format('d M Y') }}</span></span>
            @endif
            @if($sanction->updater)
            <span>{{ __('common.updated_by') }}: <span class="text-gray-600">{{ $sanction->updater->name }}</span></span>
            @endif
        </div>
    </div>
</div>
@endsection
