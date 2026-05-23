@php
    $val = fn($field, $default = '') => old($field, $document?->{$field} ?? $default);
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
    {{-- Employee --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.doc_employee') }} *</label>
        <x-relation-dropdown
            table="hr_employees"
            field="name"
            name="employee_id"
            relation="many2one"
            :selected="old('employee_id', $document?->employee_id ?? ($preselectedEmployee?->id ?? null))"
            class="flex-1"
            compact
        />
    </div>

    {{-- Name --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.doc_name') }}</label>
        <input type="text" name="name" value="{{ $val('name') }}" required
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Type --}}
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

    {{-- Notify Before --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.notify_before') }}</label>
        <div class="flex items-center gap-1 flex-1">
            <input type="number" name="notify_before_days" value="{{ $val('notify_before_days', '30') }}" min="0" max="365"
                   class="w-20 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
            <span class="text-sm text-gray-400">{{ __('employees.days') }}</span>
        </div>
    </div>

    {{-- Notes --}}
    <div class="flex items-start gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500 pt-0.5">{{ __('common.notes') }}</label>
        <textarea name="notes" rows="3"
                  class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0 resize-none"
                  placeholder="-">{{ $val('notes') }}</textarea>
    </div>

    {{-- File --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.file') }}</label>
        <div class="flex-1">
            <input type="file" name="file" class="text-sm text-gray-600">
            @if(isset($document) && $document->file_path)
            <p class="mt-1 text-xs text-gray-400">
                {{ __('employees.click_to_view') }}:
                <a href="{{ route('files.serve', $document->file_path) }}" target="_blank" class="text-purple-600 hover:underline">current file</a>
            </p>
            @endif
        </div>
    </div>
</div>
