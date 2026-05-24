@extends('layouts.app')
@section('title', $certificate->certificate_type ?? __('employees.certificates_title'))

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.certificates.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.certificates_title') }}</a>
            <span class="text-sm font-semibold text-gray-800">{{ $certificate->certificate_type ?? __('employees.certificates_title') }}</span>
        </x-slot:breadcrumb>
        <x-slot:actions>
        <div class="flex items-center gap-2">
            @can('update', \App\Models\Employees\Employee::class)
            <a href="{{ route('employees.certificates.edit', $certificate) }}"
               class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.edit') }}</a>
            @endcan

            @can('update', \App\Models\Employees\Employee::class)
            <form method="POST" action="{{ $certificate->active ? route('employees.certificates.archive', $certificate) : route('employees.certificates.unarchive', $certificate) }}">
                @csrf @method('PATCH')
                <button type="submit" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">
                    {{ $certificate->active ? __('common.archive') : __('common.unarchive') }}
                </button>
            </form>
            @endcan

            @can('delete', \App\Models\Employees\Employee::class)
            <div x-data="{ confirming: false }">
                <button type="button" x-show="!confirming" @click="confirming = true"
                        class="px-3 py-1.5 text-sm text-red-600 bg-white border border-red-200 rounded hover:bg-red-50">{{ __('common.delete') }}</button>
                <div x-show="confirming" style="display:none" class="flex items-center gap-1.5">
                    <span class="text-xs text-red-600">{{ __('common.are_you_sure') }}</span>
                    <form method="POST" action="{{ route('employees.certificates.delete', $certificate) }}">
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
            @if(!$certificate->active)
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-700 font-medium">{{ __('common.archived') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.doc_employee') }}</p>
                    @if($certificate->employee)
                        <a href="{{ route('employees.show', $certificate->employee) }}" class="text-sm text-purple-600 hover:underline mt-0.5 inline-block">
                            {{ $certificate->employee->name }}
                        </a>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.certificate_type') }}</p>
                    <p class="text-sm font-semibold text-gray-900 mt-0.5">{{ $certificate->certificate_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.study_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $certificate->study_type ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.issuing_institution') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $certificate->issuing_institution ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.citizenship') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $certificate->country ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.data_status') }}</p>
                    @if($certificate->data_status)
                    <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold mt-0.5 {{ $certificate->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                        {{ $certificate->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                    </span>
                    @else
                        <p class="text-sm text-gray-400 mt-0.5">—</p>
                    @endif
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.graduate_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $certificate->graduate_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.affective_date') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">{{ $certificate->affective_date?->format('d M Y') ?? '—' }}</p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.specialization_type') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">
                        @if($certificate->specialization_type === 'percentage') {{ __('employees.specialization_type_percentage') }}
                        @else {{ __('employees.specialization_type_amount') }}
                        @endif
                    </p>
                </div>
                <div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">{{ __('employees.financial_specialization') }}</p>
                    <p class="text-sm text-gray-900 mt-0.5">
                        @if($certificate->financial_specialization)
                            {{ number_format($certificate->financial_specialization, 2) }}{{ $certificate->specialization_type === 'percentage' ? '%' : '' }}
                        @else —
                        @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- Chatter --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <x-chatter
                model-type="App\Models\Employees\EmployeeCertificate"
                :model-id="$certificate->id"
                :can-comment="auth()->user()->can('update', \App\Models\Employees\Employee::class)"
                :comment-url="route('employees.certificates.comment', $certificate)"
            />
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm text-xs text-gray-400 px-5 py-3 flex gap-6">
            @if($certificate->creator)
            <span>{{ __('common.created_by') }}: <span class="text-gray-600">{{ $certificate->creator->name }}</span></span>
            @endif
            @if($certificate->created_at)
            <span>{{ __('common.created_at') }}: <span class="text-gray-600">{{ $certificate->created_at->format('d M Y') }}</span></span>
            @endif
            @if($certificate->updater)
            <span>{{ __('common.updated_by') }}: <span class="text-gray-600">{{ $certificate->updater->name }}</span></span>
            @endif
        </div>
    </div>
</div>
@endsection
