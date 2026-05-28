<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('attendance.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        // Employees are company-scoped.
        $employeeRule = Rule::exists('hr_employees', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });

        // Mirrors the DB UNIQUE(employee_id, attendance_date) so a duplicate
        // surfaces as a clean validation error instead of a 500. whereDate is
        // required because SQLite stores `date` columns as `Y-m-d H:i:s` and
        // Rule::unique's string equality would silently miss the collision.
        $employeeId = $this->input('employee_id');
        $uniqueRule = function ($attribute, $value, $fail) use ($employeeId) {
            if (!$employeeId || !$value) return;
            $exists = DB::table('hr_attendances')
                ->where('employee_id', $employeeId)
                ->whereDate('attendance_date', $value)
                ->whereNull('deleted_at')
                ->exists();
            if ($exists) {
                $fail(__('employees.attendance_duplicate'));
            }
        };

        return [
            'employee_id'     => ['required', $employeeRule],
            'company_id'      => ['nullable', $companyRule],
            // Attendance is a record of what HAPPENED — future dates make no
            // sense. Bound to today (in the actor's timezone) and 5 years past
            // to allow historical backfill but block year-9999 / 1900-01-01
            // mistakes that skew period summaries.
            'attendance_date' => ['required', 'date', 'after_or_equal:-5 years', 'before_or_equal:today', $uniqueRule],
            // Bounded to ±5 years (same window as attendance_date) so a typo
            // doesn't store a year-9999 punch that breaks worked_hours math
            // (Carbon would compute a quarter-billion-hour interval).
            'check_in'        => 'nullable|date|after_or_equal:-5 years|before_or_equal:+1 day',
            'check_out'       => 'nullable|date|after:check_in|after_or_equal:-5 years|before_or_equal:+1 day',
            'notes'           => 'nullable|string|max:5000',
        ];
    }
}
