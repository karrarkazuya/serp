@extends('layouts.app')
@section('title', 'Edit - ' . $employee->name)

@section('content')
<div class="flex flex-col h-full bg-gray-50">
    <x-toolbar>
        <x-slot:breadcrumb>
            <a href="{{ route('employees.index') }}" class="text-xs text-purple-600 hover:text-purple-700">{{ __('employees.title') }}</a>
            <div class="flex items-center gap-1">
                <a href="{{ route('employees.show', $employee) }}" class="text-sm font-semibold text-gray-800 hover:text-purple-700">{{ $employee->name }}</a>
                <span class="text-xs text-gray-400">/</span>
                <span class="text-sm text-gray-500">{{ __('common.edit') }}</span>
            </div>
        </x-slot:breadcrumb>
        <x-slot:actions>
    <div class="flex items-center gap-2">
        <a href="{{ route('employees.show', $employee) }}" class="px-3 py-1.5 text-sm text-gray-600 bg-white border border-gray-300 rounded hover:bg-gray-50">{{ __('common.cancel') }}</a>
        <button form="employee-form" type="submit" class="px-4 py-1.5 text-sm font-medium text-white bg-[#714B67] hover:bg-[#5c3d55] rounded shadow-sm">{{ __('common.save_short') }}</button>
    </div>
        </x-slot:actions>
    </x-toolbar>

    <div class="flex-1 overflow-y-auto">
        <div class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm">
            <form id="employee-form" method="POST" action="{{ route('employees.update', $employee) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                @include('employees._form', ['employee' => $employee, 'skillTypes' => $skillTypes])
            </form>
        </div>

        {{-- Documents management (separate from main form to allow file-based add form) --}}
        @php $docTypes = ['contract' => 'Contract', 'id_card' => 'ID Card', 'passport' => 'Passport', 'certificate' => 'Certificate', 'resume' => 'Resume', 'medical' => 'Medical', 'other' => 'Other']; @endphp
        <div id="documents-section" class="bg-white mx-4 mt-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">{{ __('employees.documents_tab') }}</h3>
                <span class="text-xs text-gray-400">{{ $employee->documents->count() }} document(s)</span>
            </div>

            @if($employee->documents->isNotEmpty())
            <div class="space-y-2 mb-4">
                @foreach($employee->documents as $doc)
                <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg border border-gray-200">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ $doc->name }}</p>
                        <div class="flex flex-wrap items-center gap-2 mt-1">
                            <span class="inline-block px-1.5 py-0.5 text-[10px] font-semibold rounded bg-gray-200 text-gray-600 uppercase">{{ $docTypes[$doc->document_type] ?? $doc->document_type }}</span>
                            @if($doc->issue_date)<span class="text-xs text-gray-500">{{ __('employees.doc_issued') }} {{ $doc->issue_date->format('d M Y') }}</span>@endif
                            @if($doc->expiry_date)
                            <span class="text-xs {{ $doc->is_expired ? 'text-red-600 font-semibold' : ($doc->is_expiring_soon ? 'text-amber-600' : 'text-gray-500') }}">
                                {{ __('employees.doc_expires') }} {{ $doc->expiry_date->format('d M Y') }}
                                @if($doc->is_expired) {{ __('employees.doc_expired') }}@elseif($doc->is_expiring_soon) {{ __('employees.doc_soon') }}@endif
                            </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($doc->file_path)
                        <a href="{{ route('files.serve', $doc->file_path) }}" target="_blank"
                           class="text-xs text-purple-600 border border-purple-200 rounded px-2 py-1 hover:bg-purple-50">{{ __('common.download') }}</a>
                        @endif
                        <div x-data="{ confirming: false }">
                            <button type="button" x-show="!confirming" @click="confirming = true"
                                    class="text-xs text-red-500 border border-red-200 rounded px-2 py-1 hover:bg-red-50">{{ __('common.delete') }}</button>
                            <div x-show="confirming" style="display:none" class="flex items-center gap-1">
                                <span class="text-xs text-red-600">{{ __('common.sure') }}</span>
                                <form method="POST" action="{{ route('employees.employee-docs.delete', [$employee, $doc]) }}">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="px-1.5 py-0.5 text-xs bg-red-600 text-white rounded">{{ __('common.yes') }}</button>
                                </form>
                                <button type="button" @click="confirming = false" class="px-1.5 py-0.5 text-xs text-gray-500 border border-gray-200 rounded">{{ __('common.no') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Add document form --}}
            <div x-data="{ open: false }">
                <button type="button" @click="open = !open"
                        class="inline-flex items-center gap-1 text-xs text-purple-700 border border-purple-200 rounded px-3 py-1.5 hover:bg-purple-50">
                    {{ __('employees.add_document') }}
                </button>
                <div x-show="open" style="display:none" class="mt-3 p-4 border border-gray-200 rounded-lg bg-gray-50">
                    <form method="POST" action="{{ route('employees.employee-docs.store', $employee) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200 sm:col-span-2">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.doc_name') }}</label>
                                <input type="text" name="name" required class="flex-1 text-sm bg-transparent border-0 focus:outline-none" placeholder="e.g. National ID">
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.doc_type') }}</label>
                                <select name="document_type" class="flex-1 text-sm bg-transparent border-0 focus:outline-none">
                                    <option value="">{{ __('employees.select_option') }}</option>
                                    @foreach($docTypes as $k => $v)
                                    <option value="{{ $k }}">{{ $v }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.issued_by') }}</label>
                                <input type="text" name="issued_by" class="flex-1 text-sm bg-transparent border-0 focus:outline-none">
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.document_number') }}</label>
                                <input type="text" name="document_number" class="flex-1 text-sm bg-transparent border-0 focus:outline-none">
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.organizational_structure') }}</label>
                                <input type="text" name="organizational_structure" class="flex-1 text-sm bg-transparent border-0 focus:outline-none">
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.issue_date') }}</label>
                                <input type="date" name="issue_date" class="flex-1 text-sm bg-transparent border-0 focus:outline-none">
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.expiry_date') }}</label>
                                <input type="date" name="expiry_date" class="flex-1 text-sm bg-transparent border-0 focus:outline-none">
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.notify_before') }}</label>
                                <div class="flex items-center gap-1 flex-1">
                                    <input type="number" name="notify_before_days" value="30" min="0" max="365" class="w-16 text-sm bg-transparent border-0 focus:outline-none">
                                    <span class="text-sm text-gray-400">{{ __('employees.days') }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-3 py-1.5 border-b border-gray-200 sm:col-span-2">
                                <label class="w-40 shrink-0 text-sm text-gray-500">{{ __('employees.file') }}</label>
                                <input type="file" name="file" class="flex-1 text-sm text-gray-600">
                            </div>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <button type="submit" class="px-3 py-1.5 bg-[#714B67] text-white text-sm rounded hover:bg-[#5c3d55]">{{ __('employees.save_document') }}</button>
                            <button type="button" @click="open = false" class="px-3 py-1.5 text-sm text-gray-600 border border-gray-200 rounded hover:bg-gray-100">{{ __('common.cancel') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Planned Schedule preview --}}
        @if($employee->resourceCalendar && $employee->resourceCalendar->attendances->isNotEmpty())
        @php
            $decToTime = fn($dec) => sprintf('%02d:%02d', (int)$dec, round(($dec - (int)$dec) * 60));
            $calStart  = now()->startOfMonth();
            $daysInMonth = $calStart->daysInMonth;
            $firstCol  = $calStart->dayOfWeek; // Carbon 0=Sun
            $attByDow  = $employee->resourceCalendar->attendances->groupBy('day_of_week');
        @endphp
        <div class="bg-white mx-4 mt-4 mb-4 rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-gray-700">Planned Schedule — {{ now()->format('F Y') }}</h3>
                <span class="text-xs text-gray-400">{{ $employee->resourceCalendar->name }}</span>
            </div>
            <div class="grid grid-cols-7 gap-px bg-gray-100 rounded-lg overflow-hidden text-xs">
                @foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $h)
                <div class="bg-gray-50 py-1.5 text-center font-semibold text-gray-500 uppercase">{{ $h }}</div>
                @endforeach
                @for($b = 0; $b < $firstCol; $b++)
                <div class="bg-white min-h-14"></div>
                @endfor
                @for($d = 1; $d <= $daysInMonth; $d++)
                @php
                    $date    = $calStart->copy()->day($d);
                    $ourDow  = ($date->dayOfWeek + 1) % 7;
                    $lines   = $attByDow->get($ourDow, collect());
                    $isToday = $date->isToday();
                @endphp
                <div class="bg-white min-h-14 p-1 {{ $isToday ? 'ring-2 ring-inset ring-purple-400' : '' }}">
                    <span class="block text-right {{ $isToday ? 'font-bold text-purple-700' : 'text-gray-400' }}">{{ $d }}</span>
                    @foreach($lines as $line)
                    <div class="mt-0.5 text-[10px] text-purple-700 bg-purple-50 rounded px-1 truncate">
                        {{ $decToTime($line->hour_from) }}–{{ $decToTime($line->hour_to) }}
                    </div>
                    @endforeach
                </div>
                @endfor
            </div>
        </div>
        @else
        <div class="mx-4 mt-4 mb-4 bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-sm text-gray-400 text-center">
            {{ __('employees.no_schedule') }}
        </div>
        @endif
    </div>
</div>
@endsection
