@extends('layouts.app')
@section('title', __('employees.documents_title'))

@php
    $quickFilters = [
        ['label' => __('common.active'),   'url' => route('employees.documents.index', array_merge(request()->except('page'), ['filter' => '']))],
        ['label' => __('common.archived'), 'url' => route('employees.documents.index', array_merge(request()->except('page'), ['filter' => 'archived']))],
        ['label' => __('common.all'),      'url' => route('employees.documents.index', array_merge(request()->except('page'), ['filter' => 'all']))],
    ];
    $documentGroups = [
        ['label' => __('employees.doc_type'),    'url' => route('employees.documents.index', array_merge(request()->except('page'), ['group_by' => 'document_type']))],
        ['label' => __('employees.issued_by'),   'url' => route('employees.documents.index', array_merge(request()->except('page'), ['group_by' => 'issued_by']))],
    ];
@endphp

@section('content')
<div class="flex min-w-0 flex-col h-full bg-white">
    <div class="flex flex-wrap items-center gap-2 sm:gap-3 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white shrink-0">
        @can('create', \App\Models\Employees\Employee::class)
        <a href="{{ route('employees.documents.create') }}" class="px-3 sm:px-4 py-2 bg-[#714B67] hover:bg-[#5c3d55] text-white text-sm font-semibold rounded shadow-sm shrink-0">
            {{ __('common.new') }}
        </a>
        @endcan

        <div class="flex items-center gap-1.5 min-w-0 shrink-0">
            <span class="text-xl font-semibold text-gray-700">{{ __('employees.documents_title') }}</span>
        </div>

        <x-search
            :model="\App\Models\Employees\EmployeeDocument::class"
            :action="route('employees.documents.index')"
            :quick-filters="$quickFilters"
            :group-by="$documentGroups"
        />

        <div class="ms-auto flex items-center gap-2 sm:gap-3 text-sm text-gray-500 shrink-0">
            @if(isset($groups))
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">{{ collect($groups)->sum('count') }} records</span>
            @elseif(isset($documents) && $documents->total() > 0)
                <span class="text-sm font-semibold text-gray-600 whitespace-nowrap">
                    {{ $documents->firstItem() }}-{{ $documents->lastItem() }} / {{ $documents->total() }}
                </span>
            @else
                <span class="text-sm font-semibold text-gray-400">0</span>
            @endif
            @if(!isset($groups))
            <div class="flex items-center gap-1">
                @if(isset($documents) && $documents->onFirstPage())
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">‹</span>
                @elseif(isset($documents))
                    <a href="{{ $documents->previousPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">‹</a>
                @endif
                @if(isset($documents) && $documents->hasMorePages())
                    <a href="{{ $documents->nextPageUrl() }}" class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-600 hover:text-gray-900">›</a>
                @elseif(isset($documents))
                    <span class="w-10 h-10 inline-flex items-center justify-center rounded bg-gray-100 text-gray-300">›</span>
                @endif
            </div>
            @endif
        </div>
    </div>

    @if(isset($groups))
    <x-list :grouped="true" :empty-text="__('employees.no_documents')">
        <x-slot:columns>
            <x-sortable-th column="name"       :label="__('employees.doc_name')"       class="px-4 py-2" :default="true" />
            <x-sortable-th column="type"       :label="__('employees.doc_type')"       class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_employee') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.issued_by') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.document_number') }}</th>
            <x-sortable-th column="issue_date" :label="__('employees.issue_date')"     class="px-3 py-2" />
            <x-sortable-th column="expiry_date":label="__('employees.expiry_date')"    class="px-3 py-2" />
        </x-slot:columns>

        @forelse($groups as $group)
        <tbody x-data="{ open: {{ $loop->first ? 'true' : 'false' }} }" class="divide-y divide-gray-100">
            <tr class="bg-gray-50 border-y border-gray-200 cursor-pointer select-none" @click="open = !open">
                <td colspan="99" class="px-4 py-2.5">
                    <div class="flex items-center gap-2 text-sm font-semibold text-gray-800">
                        <svg class="w-3.5 h-3.5 transition-transform shrink-0 text-gray-400" :class="open ? 'rotate-90' : ''" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/>
                        </svg>
                        {{ $group['label'] }}
                        <span class="ms-1 text-xs text-gray-400 font-normal">({{ $group['count'] }})</span>
                    </div>
                </td>
            </tr>
            @foreach($group['items'] as $doc)
            <tr x-show="open" class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.documents.show', $doc) }}'">
                <td class="px-4 py-2.5">
                    <p class="text-sm font-semibold text-gray-900">{{ $doc->name }}</p>
                    @if(!$doc->active)
                        <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                    @endif
                </td>
                <td class="px-3 py-2.5">
                    @php $typeLabels = ['contract'=>'Contract','id_card'=>'ID Card','passport'=>'Passport','certificate'=>'Certificate','resume'=>'Resume','medical'=>'Medical','other'=>'Other']; @endphp
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600 uppercase">
                        {{ $typeLabels[$doc->document_type] ?? $doc->document_type }}
                    </span>
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">
                    @if($doc->employee)
                        <a href="{{ route('employees.show', $doc->employee) }}" class="text-purple-600 hover:underline" @click.stop>{{ $doc->employee->name }}</a>
                    @else
                        —
                    @endif
                </td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $doc->issued_by ?? '—' }}</td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $doc->document_number ?? '—' }}</td>
                <td class="px-3 py-2.5 text-sm text-gray-600">{{ $doc->issue_date?->format('d M Y') ?? '—' }}</td>
                <td class="px-3 py-2.5 text-sm {{ $doc->is_expired ? 'text-red-600 font-semibold' : ($doc->is_expiring_soon ? 'text-amber-600' : 'text-gray-600') }}">
                    {{ $doc->expiry_date?->format('d M Y') ?? '—' }}
                    @if($doc->is_expired) <span class="text-xs">({{ __('employees.doc_expired') }})</span>
                    @elseif($doc->is_expiring_soon) <span class="text-xs">({{ __('employees.doc_soon') }})</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
        @empty
        <tbody>
            <tr><td colspan="99" class="px-4 py-20 text-center text-sm text-gray-400">{{ __('employees.no_documents') }}</td></tr>
        </tbody>
        @endforelse
    </x-list>

    @else
    <x-list :paginator="$documents" :empty-text="__('employees.no_documents')">
        <x-slot:columns>
            <x-sortable-th column="name"       :label="__('employees.doc_name')"       class="px-4 py-2" :default="true" />
            <x-sortable-th column="type"       :label="__('employees.doc_type')"       class="px-3 py-2" />
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.doc_employee') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.issued_by') }}</th>
            <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">{{ __('employees.document_number') }}</th>
            <x-sortable-th column="issue_date" :label="__('employees.issue_date')"     class="px-3 py-2" />
            <x-sortable-th column="expiry_date":label="__('employees.expiry_date')"    class="px-3 py-2" />
        </x-slot:columns>

        @foreach($documents as $doc)
        <tr class="hover:bg-purple-50/30 cursor-pointer" onclick="window.location='{{ route('employees.documents.show', $doc) }}'">
            <td class="px-4 py-2.5">
                <p class="text-sm font-semibold text-gray-900">{{ $doc->name }}</p>
                @if(!$doc->active)
                    <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold text-amber-700 bg-amber-50">{{ __('common.archived') }}</span>
                @endif
            </td>
            <td class="px-3 py-2.5">
                @php $typeLabels = ['contract'=>'Contract','id_card'=>'ID Card','passport'=>'Passport','certificate'=>'Certificate','resume'=>'Resume','medical'=>'Medical','other'=>'Other']; @endphp
                <span class="inline-block px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-100 text-gray-600 uppercase">
                    {{ $typeLabels[$doc->document_type] ?? $doc->document_type }}
                </span>
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">
                @if($doc->employee)
                    <a href="{{ route('employees.show', $doc->employee) }}" class="text-purple-600 hover:underline" @click.stop>{{ $doc->employee->name }}</a>
                @else
                    —
                @endif
            </td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $doc->issued_by ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $doc->document_number ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm text-gray-600">{{ $doc->issue_date?->format('d M Y') ?? '—' }}</td>
            <td class="px-3 py-2.5 text-sm {{ $doc->is_expired ? 'text-red-600 font-semibold' : ($doc->is_expiring_soon ? 'text-amber-600' : 'text-gray-600') }}">
                {{ $doc->expiry_date?->format('d M Y') ?? '—' }}
                @if($doc->is_expired) <span class="text-xs">({{ __('employees.doc_expired') }})</span>
                @elseif($doc->is_expiring_soon) <span class="text-xs">({{ __('employees.doc_soon') }})</span>
                @endif
            </td>
        </tr>
        @endforeach
    </x-list>
    @endif
</div>
@endsection
