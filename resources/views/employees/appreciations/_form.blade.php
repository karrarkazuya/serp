@php
    $val = fn($field, $default = '') => old($field, $appreciation?->{$field} ?? $default);
    $docTypes = [
        'contract'    => __('employees.doc_contract'),
        'id_card'     => __('employees.doc_id_card'),
        'passport'    => __('employees.doc_passport'),
        'certificate' => __('employees.doc_certificate'),
        'resume'      => __('employees.doc_resume'),
        'medical'     => __('employees.doc_medical'),
        'other'       => __('employees.doc_other'),
    ];
@endphp

@if($errors->any())
<div class="px-6 pt-4">
    <div class="bg-red-50 border border-red-200 rounded-lg px-4 py-3">
        <ul class="list-disc list-inside text-sm text-red-600 space-y-0.5">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
</div>
@endif

<div class="p-6 space-y-0">
    {{-- Name --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.doc_name') }}</label>
        <input type="text" name="name" value="{{ $val('name') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Document Type --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.doc_type') }}</label>
        <select name="document_type" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
            <option value="">—</option>
            @foreach($docTypes as $k => $v)
                <option value="{{ $k }}" {{ $val('document_type') === $k ? 'selected' : '' }}>{{ $v }}</option>
            @endforeach
        </select>
    </div>

    {{-- Issued By --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.issued_by') }}</label>
        <input type="text" name="issued_by" value="{{ $val('issued_by') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Document Number --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.document_number') }}</label>
        <input type="text" name="document_number" value="{{ $val('document_number') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Organizational Structure --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.organizational_structure') }}</label>
        <input type="text" name="organizational_structure" value="{{ $val('organizational_structure') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Assignment Type --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.assignment_type') }}</label>
        <input type="text" name="assignment_type" value="{{ $val('assignment_type') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Data Status --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.data_status') }}</label>
        <select name="data_status" class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
            <option value="">—</option>
            <option value="current"  {{ $val('data_status') === 'current'  ? 'selected' : '' }}>{{ __('employees.data_status_current') }}</option>
            <option value="previous" {{ $val('data_status') === 'previous' ? 'selected' : '' }}>{{ __('employees.data_status_previous') }}</option>
        </select>
    </div>

    {{-- Specialization Type + conditional value --}}
    <div x-data="{ specType: @js(old('specialization_type', $appreciation->specialization_type ?? 'amount')) }">
        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
            <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.specialization_type') }}</label>
            <div class="flex items-center gap-5">
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="radio" name="specialization_type" value="amount" x-model="specType" class="text-purple-600">
                    {{ __('employees.specialization_type_amount') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="radio" name="specialization_type" value="percentage" x-model="specType" class="text-purple-600">
                    {{ __('employees.specialization_type_percentage') }}
                </label>
                <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                    <input type="radio" name="specialization_type" value="seniority" x-model="specType" class="text-purple-600">
                    {{ __('employees.specialization_type_seniority') }}
                </label>
            </div>
        </div>
        <div x-show="specType === 'amount' || specType === 'percentage'" class="flex items-center gap-4 py-1.5 border-b border-gray-100">
            <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.financial_specialization') }}</label>
            <div class="flex items-center gap-2 flex-1">
                <input type="number" name="financial_specialization" value="{{ $val('financial_specialization') }}" min="0" step="0.01"
                       class="w-32 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0.00">
                <span x-show="specType === 'percentage'" class="text-sm text-gray-400">%</span>
            </div>
        </div>
        <div x-show="specType === 'seniority'" class="flex items-center gap-4 py-1.5 border-b border-gray-100">
            <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.employee_seniority') }}</label>
            <div class="flex items-center gap-2 flex-1">
                <input type="number" name="employee_seniority" value="{{ $val('employee_seniority') }}" min="0" step="1"
                       class="w-32 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0">
                <span class="text-sm text-gray-400">{{ __('employees.employee_seniority_months') }}</span>
            </div>
        </div>
    </div>

    {{-- Affective Date --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.affective_date') }}</label>
        <input type="date" name="affective_date" value="{{ $val('affective_date') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
    </div>

    {{-- Issue Date --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.issue_date') }}</label>
        <input type="date" name="issue_date" value="{{ $val('issue_date') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
    </div>

    {{-- Expiry Date --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.expiry_date') }}</label>
        <input type="date" name="expiry_date" value="{{ $val('expiry_date') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
    </div>

    {{-- Notify Before Days --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.notify_before_days') }}</label>
        <input type="number" name="notify_before_days" value="{{ $val('notify_before_days') }}" min="0" max="365"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0">
    </div>

    {{-- Notes --}}
    <div class="flex items-start gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500 pt-1">{{ __('employees.notes') }}</label>
        <textarea name="notes" rows="3"
                  class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0 resize-none" placeholder="-">{{ $val('notes') }}</textarea>
    </div>

    {{-- File Upload --}}
    <div class="flex items-center gap-4 py-1.5">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.doc_file') }}</label>
        <div class="flex-1">
            @if(isset($appreciation) && $appreciation->file_path && $appreciation->attachedFile)
                <div class="flex items-center gap-3 mb-2">
                    <a href="{{ route('files.serve', $appreciation->file_path) }}" target="_blank"
                       class="text-sm text-purple-600 hover:underline flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
                        </svg>
                        {{ $appreciation->attachedFile->original_name ?? __('employees.doc_file') }}
                    </a>
                    <span class="text-xs text-gray-400">({{ __('employees.doc_replace_hint') }})</span>
                </div>
            @endif
            <input type="file" name="file" class="text-sm text-gray-700">
        </div>
    </div>
</div>
