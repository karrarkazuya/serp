@extends('layouts.app')
@section('title', __('employees.certificates_title'))

@php
    $quickFilters = [
        ['label' => __('common.active'),   'url' => route('employees.certificates.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'url' => route('employees.certificates.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'url' => route('employees.certificates.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.certificates.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.certificates_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\EmployeeCertificate::class"
            :action="route('employees.certificates.index')"
            :quick-filters="$quickFilters"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if($certificates->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $certificates->firstItem() }}-{{ $certificates->lastItem() }} / {{ $certificates->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            <div class="flex items-center gap-1">
                @if($certificates->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @else
                    <a href="{{ $certificates->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if($certificates->hasMorePages())
                    <a href="{{ $certificates->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @else
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
        </div>
    </div>

    <x-list :paginator="$certificates" :empty-text="__('employees.no_certificates')">
        <x-slot:columns>
            <x-sortable-th column="certificate_type" :label="__('employees.certificate_type')" class="px-4 py-2" :default="true" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.study_type') }}</th>
            <x-sortable-th column="employee"         :label="__('employees.doc_employee')"     class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.issuing_institution') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.citizenship') }}</th>
            <x-sortable-th column="graduate_date"    :label="__('employees.graduate_date')"    class="px-3 py-2" />
            <x-sortable-th column="affective_date"   :label="__('employees.affective_date')"   class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.data_status') }}</th>
        </x-slot:columns>

        @foreach($certificates as $cert)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.certificates.show', $cert) }}'">
            <td class="px-4 py-2.5">
                <p class="text-sm font-semibold text-gray-900">{{ $cert->certificate_type ?? '—' }}</p>
                @if(!$cert->active)
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $cert->study_type ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">
                @if($cert->employee)
                    <a href="{{ route('employees.show', $cert->employee) }}" class="text-purple-600 hover:underline" @click.stop>{{ $cert->employee->name }}</a>
                @else —
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $cert->issuing_institution ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $cert->country ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $cert->graduate_date?->format('d M Y') ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $cert->affective_date?->format('d M Y') ?? '—' }}</td>
            <td class="px-3 py-2.5">
                @if($cert->data_status)
                <span class="inline-block px-2 py-0.5 rounded text-xs font-semibold {{ $cert->data_status === 'current' ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">
                    {{ $cert->data_status === 'current' ? __('employees.data_status_current') : __('employees.data_status_previous') }}
                </span>
                @else
                    —
                @endif
            </td>
        </tr>
        @endforeach
    </x-list>
</div>
@endsection
