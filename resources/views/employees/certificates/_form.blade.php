@php
    $val = fn($field, $default = '') => old($field, $certificate?->{$field} ?? $default);
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
            :selected="old('employee_id', $certificate?->employee_id ?? ($preselectedEmployee?->id ?? null))"
            class="flex-1"
            compact
        />
    </div>

    {{-- Certificate Type --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.certificate_type') }}</label>
        <input type="text" name="certificate_type" value="{{ $val('certificate_type') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Study Type --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.study_type') }}</label>
        <input type="text" name="study_type" value="{{ $val('study_type') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Issuing Institution --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.issuing_institution') }}</label>
        <input type="text" name="issuing_institution" value="{{ $val('issuing_institution') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="-">
    </div>

    {{-- Country --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.citizenship') }}</label>
        <input type="text" name="country" value="{{ $val('country') }}"
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

    {{-- Graduate Date --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.graduate_date') }}</label>
        <input type="date" name="graduate_date" value="{{ $val('graduate_date') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
    </div>

    {{-- Affective Date --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.affective_date') }}</label>
        <input type="date" name="affective_date" value="{{ $val('affective_date') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
    </div>

    {{-- Specialization Type + conditional value --}}
    <div x-data="{ specType: '{{ old('specialization_type', $certificate->specialization_type ?? 'amount') }}' }">
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
            </div>
        </div>
        <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
            <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.financial_specialization') }}</label>
            <div class="flex items-center gap-2 flex-1">
                <input type="number" name="financial_specialization" value="{{ $val('financial_specialization') }}" min="0" step="0.01"
                       class="w-32 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0.00">
                <span x-show="specType === 'percentage'" class="text-sm text-gray-400">%</span>
            </div>
        </div>
    </div>
</div>
