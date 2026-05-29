@php
    /** @var \App\Models\Employees\Attendance|null $attendance */
    $attendance ??= null;
    $employeeId       = old('employee_id',     $attendance?->employee_id);
    $companyId        = old('company_id',      $attendance?->company_id ?? ($defaultCompanyId ?? null));
    $attendanceDate   = old('attendance_date', $attendance?->attendance_date?->format('Y-m-d') ?? now()->format('Y-m-d'));
    $checkIn          = old('check_in',        $attendance?->check_in?->format('Y-m-d\TH:i'));
    $checkOut         = old('check_out',       $attendance?->check_out?->format('Y-m-d\TH:i'));
    $notes            = old('notes',           $attendance?->notes);
@endphp

<div class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
            {{ __('employees.employee_name') }} <span class="text-red-500">*</span>
        </label>
        <x-relation-dropdown name="employee_id" table="hr_employees" field="name" :selected="$employeeId" placeholder="{{ __('employees.select_employee') }}" compact />
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('common.company') }}</label>
        <x-relation-dropdown name="company_id" table="companies" field="name" :selected="$companyId" placeholder="{{ __('common.select_company') }}" compact />
    </div>

    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">
            {{ __('employees.attendance_date') }} <span class="text-red-500">*</span>
        </label>
        <input type="date" name="attendance_date" value="{{ $attendanceDate }}"
               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent" required>
    </div>
    <div></div>

    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.check_in') }}</label>
        <input type="datetime-local" name="check_in" value="{{ $checkIn }}"
               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
    </div>
    <div>
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.check_out') }}</label>
        <input type="datetime-local" name="check_out" value="{{ $checkOut }}"
               class="w-full border-b border-gray-300 focus:border-purple-500 focus:outline-none py-1.5 text-sm bg-transparent">
    </div>

    <div class="sm:col-span-2 -mt-2">
        <p class="text-xs text-gray-400 italic">
            {{ __('employees.absence_auto_hint') }}
        </p>
    </div>

    <div class="sm:col-span-2">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">{{ __('employees.attendance_notes') }}</label>
        <textarea name="notes" rows="3"
                  class="w-full border border-gray-200 focus:border-purple-500 focus:outline-none p-2 text-sm rounded">{{ $notes }}</textarea>
    </div>
</div>
