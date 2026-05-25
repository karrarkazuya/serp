<?php

namespace App\Observers\Employees;

use App\Models\Employees\Employee;
use App\Services\Employees\PlannedScheduleService;

/**
 * When an employee's default `resource_calendar_id` changes (e.g. via the
 * employee form), wipe their repeat pattern + planned days and regenerate
 * the 30-day buffer with the new calendar starting tomorrow.
 *
 * Today's active schedule is updated by the form itself; the buffer reset
 * only affects tomorrow onward — so an in-flight pattern doesn't outlive
 * the calendar change.
 */
class EmployeeScheduleObserver
{
    public function __construct(private readonly PlannedScheduleService $service) {}

    public function updated(Employee $employee): void
    {
        if ($employee->wasChanged('resource_calendar_id')) {
            $this->service->resetForEmployee($employee);
        }
    }
}
