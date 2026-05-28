<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetPlannedDayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('planned_schedules.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // Working schedules can be shared (company_id null) or belong to one of
        // the actor's active companies — same rule as ResourceCalendar elsewhere.
        $scheduleRule = Rule::exists('hr_resource_calendars', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id');
            if (!empty($activeCompanyIds)) {
                $q->orWhereIn('company_id', $activeCompanyIds);
            }
        });

        return [
            // Planned schedules are a rolling 30-day buffer — anything beyond
            // ~3 months is the cron's job to spawn, not the user's. Cap at
            // +1 year so a typo / paste accident can't write a year-9999 row
            // that escapes the cron's 30-day prune window forever.
            'planned_date'         => ['required', 'date', 'after_or_equal:today', 'before_or_equal:+1 year'],
            'resource_calendar_id' => ['required', $scheduleRule],
        ];
    }
}
