<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\ApplyPlannedPatternRequest;
use App\Http\Requests\Employees\SetPlannedDayRequest;
use App\Models\Employees\Employee;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\PlannedScheduleService;
use Illuminate\Support\Facades\DB;

class PlannedScheduleController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly PlannedScheduleService $plannedSchedule,
    ) {}

    public function setDay(SetPlannedDayRequest $request, Employee $employee)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        $this->assertWithinActiveCompanies($employee);

        $data = $request->validated();

        DB::transaction(fn () => $this->plannedSchedule->setDay(
            $employee,
            $data['planned_date'],
            (int) $data['resource_calendar_id'],
        ));

        return back()->with('success', __('employees.planned_day_updated'));
    }

    public function applyPattern(ApplyPlannedPatternRequest $request, Employee $employee)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        $this->assertWithinActiveCompanies($employee);

        $data = $request->validated();

        DB::transaction(fn () => $this->plannedSchedule->applyPattern(
            $employee,
            array_map('intval', $data['pattern']),
        ));

        return back()->with('success', __('employees.planned_pattern_applied'));
    }

    private function assertWithinActiveCompanies(Employee $employee): void
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds, true),
            403,
        );
    }
}
