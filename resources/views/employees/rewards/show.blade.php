@extends('layouts.app')
@section('title', $reward->name ?? __('employees.rewards_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.rewards.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.rewards_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $reward->name ?? __('employees.rewards_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
        <div class="flex items-center gap-2">
            @can('update', \App\Models\Employees\Employee::class)
            <a href="{{ route('employees.rewards.edit', $reward) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan

            @can('update', \App\Models\Employees\Employee::class)
            <form method="POST" action="{{ $reward->active ? route('employees.rewards.archive', $reward) : route('employees.rewards.unarchive', $reward) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    {{ $reward->active ? __('common.archive') : __('common.unarchive') }}
                </button>
            </form>
            @endcan

            @can('delete', \App\Models\Employees\Employee::class)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.rewards.delete', $reward) }}">
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
            @if(!$reward->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('common.archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_name') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $reward->name ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->document_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issued_by') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->issued_by ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.document_number') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->document_number ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.organizational_structure') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->organizational_structure ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.assignment_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->assignment_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.data_status') }}</p>
                    @if($reward->data_status)
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold mt-0.5 {{ $reward->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $reward->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                    </span>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.specialization_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">
                        @if($reward->specialization_type === 'seniority') {{ __('employees.specialization_type_seniority') }}
                        @elseif($reward->specialization_type === 'percentage') {{ __('employees.specialization_type_percentage') }}
                        @else {{ __('employees.specialization_type_amount') }}
                        @endif
                    </p>
                </div>
                <div>
                    @if($reward->specialization_type === 'seniority')
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.employee_seniority') }}</p>
                        <p class="text-sm text-gray-900 mt-0.5">
                            {{ $reward->employee_seniority !== null ? $reward->employee_seniority . ' ' . __('employees.employee_seniority_months') : '—' }}
                        </p>
                    @else
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.financial_specialization') }}</p>
                        <p class="text-sm text-gray-900 mt-0.5">
                            @if($reward->financial_specialization)
                                {{ number_format($reward->financial_specialization, 2) }}{{ $reward->specialization_type === 'percentage' ? '%' : '' }}
                            @else —
                            @endif
                        </p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.affective_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->affective_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issue_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->issue_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.expiry_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->expiry_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.notify_before_days') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $reward->notify_before_days ?? '—' }}</p>
                </div>
                @if($reward->notes)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.notes') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5 whitespace-pre-wrap">{{ $reward->notes }}</p>
                </div>
                @endif
                @if($reward->attachedFile)
                <div class="sm:col-span-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_file') }}</p>
                    <div class="mt-1 flex items-center gap-3">
                        <a href="{{ route('files.serve', $reward->file_path) }}" target="_blank"
                           class="text-sm text-purple-600 hover:underline flex items-center gap-1">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                            </svg>
                            {{ $reward->attachedFile->original_name ?? __('employees.doc_file') }}
                        </a>
                        @can('update', \App\Models\Employees\Employee::class)
                        <form method="POST" action="{{ route('employees.rewards.document.delete', $reward) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="text-xs text-red-500 hover:underline">{{ __('common.delete') }}</button>
                        </form>
                        @endcan
                    </div>
                    @can('update', \App\Models\Employees\Employee::class)
                    <form method="POST" action="{{ route('employees.rewards.document.replace', $reward) }}" enctype="multipart/form-data" class="mt-2 flex items-center gap-2">
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
            <form method="POST" action="{{ route('employees.rewards.employees.sync', $reward) }}">
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
                    :selected="$reward->employees->pluck('id')->all()"
                    placeholder="{{ __('employees.select_employee') }}"
                    compact
                />
            </form>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\EmployeeReward"
                :model-id="$reward->id"
                :can-comment="auth()->user()->can('update', \App\Models\Employees\Employee::class)"
                :comment-url="route('employees.rewards.comment', $reward)"
            />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm text-xs text-gray-400 px-5 py-3 flex gap-6">
            @if($reward->creator)
            <span>{{ __('common.created_by') }}: <span class="text-gray-600">{{ $reward->creator->name }}</span></span>
            @endif
            @if($reward->created_at)
            <span>{{ __('common.created_at') }}: <span class="text-gray-600">{{ $reward->created_at->format('d M Y') }}</span></span>
            @endif
            @if($reward->updater)
            <span>{{ __('common.updated_by') }}: <span class="text-gray-600">{{ $reward->updater->name }}</span></span>
            @endif
        </div>
    </div>
</div>
@endsection
