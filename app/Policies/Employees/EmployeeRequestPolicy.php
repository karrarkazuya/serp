<?php

namespace App\Policies\Employees;

use App\Models\Employees\EmployeeRequest;
use App\Models\User;
use App\Services\Company\CompanyContextService;

class EmployeeRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.requests.read')
            || $user->hasPermission('attendance.self.request');
    }

    /**
     * HR or the submitter or the assigned attendance manager may view it.
     * HR access additionally gated by the request's company being in the
     * actor's active companies (Rule 5 — multi-tenant isolation).
     */
    public function view(User $user, EmployeeRequest $request): bool
    {
        if ($user->hasPermission('attendance.requests.read')
            && $this->isWithinActiveCompanies($request)) {
            return true;
        }

        $employeeUserId = $request->employee?->user_id;
        if ($employeeUserId && $user->id === $employeeUserId) return true;

        $managerUserId = $request->employee?->attendanceManager?->user_id;
        return $managerUserId !== null && $user->id === $managerUserId;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.requests.write')
            || $user->hasPermission('attendance.self.request');
    }

    // No update() ability on purpose — requests are immutable after submit.
    // Approve/reject is a separate action (approveAsManager / approveAsHr).

    public function approveAsManager(User $user, EmployeeRequest $request): bool
    {
        if ($request->isLocked()) return false;
        $managerUserId = $request->employee?->attendanceManager?->user_id;
        return $managerUserId !== null && $user->id === $managerUserId;
    }

    public function approveAsHr(User $user, EmployeeRequest $request): bool
    {
        if ($request->isLocked()) return false;
        return $user->hasPermission('attendance.hr_approve')
            && $this->isWithinActiveCompanies($request);
    }

    /**
     * The request's company must be in the actor's currently-active company
     * scope. Empty active list is treated as "see all" (matches the rest of
     * the app's company-context behavior).
     */
    private function isWithinActiveCompanies(EmployeeRequest $request): bool
    {
        $activeIds = app(CompanyContextService::class)->getActiveCompanyIds();
        return empty($activeIds) || in_array((int) $request->company_id, $activeIds, true);
    }

    public function comment(User $user, EmployeeRequest $request): bool
    {
        return $this->view($user, $request);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('attendance.requests.export');
    }

    // No delete ability on purpose — requests are immutable records.
}
