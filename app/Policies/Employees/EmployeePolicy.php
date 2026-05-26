<?php

namespace App\Policies\Employees;

use App\Models\Employees\Employee;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class EmployeePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, ?Employee $employee = null): bool
    {
        if (!$user->hasPermission('employees.read')) {
            return false;
        }
        // Defense-in-depth: also reject cross-tenant records at the policy
        // layer so @can checks and bare Gate calls fail-closed even when
        // not gated by the controller's own company-scope abort_unless.
        return $employee === null || $this->withinActiveCompany($employee);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.create');
    }

    public function update(User $user, ?Employee $employee = null): bool
    {
        if (!$user->hasPermission('employees.write')) {
            return false;
        }
        return $employee === null || $this->withinActiveCompany($employee);
    }

    public function delete(User $user, ?Employee $employee = null): bool
    {
        if (!$user->hasPermission('employees.unlink')) {
            return false;
        }
        return $employee === null || $this->withinActiveCompany($employee);
    }

    public function comment(User $user, Employee $employee): bool
    {
        return $user->hasPermission('employees.write')
            && $this->withinActiveCompany($employee);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('employees.export');
    }
}
