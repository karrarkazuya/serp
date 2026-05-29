<?php

namespace App\Policies\Employees;

use App\Models\Employees\Department;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class DepartmentPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Department $dept): bool
    {
        return $user->hasPermission('employees.read') && $this->withinActiveCompany($dept);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Department $dept): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($dept);
    }

    public function delete(User $user, Department $dept): bool
    {
        return $user->hasPermission('employees.unlink') && $this->withinActiveCompany($dept);
    }

    public function comment(User $user, Department $dept): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($dept);
    }
}
