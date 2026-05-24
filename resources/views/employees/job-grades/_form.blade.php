@php
    $val = fn($field, $default = '') => old($field, $jobGrade?->{$field} ?? $default);
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

    {{-- Financial Specialization --}}
    <div class="flex items-center gap-4 py-1.5 border-b border-gray-100">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.financial_specialization') }}</label>
        <input type="number" name="financial_specialization" value="{{ $val('financial_specialization') }}" min="0" step="0.01"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0" placeholder="0.00">
    </div>

    {{-- Affective Date --}}
    <div class="flex items-center gap-4 py-1.5">
        <label class="w-48 shrink-0 text-sm text-gray-500">{{ __('employees.affective_date') }}</label>
        <input type="date" name="affective_date" value="{{ $val('affective_date') }}"
               class="flex-1 text-sm text-gray-800 bg-transparent border-0 focus:outline-none px-0 py-0">
    </div>
</div>
