<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('attendance.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $employeeRule = Rule::exists('hr_employees', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });

        // Mirrors the DB UNIQUE(employee_id, attendance_date). Falls back to the
        // current record's employee_id when the request doesn't carry one
        // (partial PATCH), and ignores the current row's id. whereDate is
        // required because SQLite stores `date` columns as `Y-m-d H:i:s`.
        $attendance = $this->route('attendance');
        $employeeId = $this->input('employee_id', $attendance?->employee_id);
        $date       = $this->input('attendance_date', $attendance?->attendance_date?->toDateString());
        $ignoreId   = $attendance?->id;

        $uniqueRule = function ($attribute, $value, $fail) use ($employeeId, $ignoreId) {
            if (!$employeeId || !$value) return;
            $exists = DB::table('hr_attendances')
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $value)
                ->whereNull('deleted_at')
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists();
            if ($exists) {
                $fail(__('employees.attendance_duplicate'));
            }
        };

        return [
            'employee_id'     => ['sometimes', 'required', $employeeRule],
            'company_id'      => ['nullable', $companyRule],
            // See StoreAttendanceRequest for the rationale.
            'attendance_date' => ['sometimes', 'required', 'date', 'after_or_equal:-5 years', 'before_or_equal:today', $uniqueRule],
            // See StoreAttendanceRequest for the rationale.
            'check_in'        => 'nullable|date|after_or_equal:-5 years|before_or_equal:+1 day',
            'check_out'       => 'nullable|date|after:check_in|after_or_equal:-5 years|before_or_equal:+1 day',
            'notes'           => 'nullable|string|max:5000',
        ];
    }
}
