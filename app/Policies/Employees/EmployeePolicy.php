<?php

namespace App\Policies\Employees;

use App\Models\Employees\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, ?Employee $_employee = null): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.create');
    }

    public function update(User $user, ?Employee $_employee = null): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, ?Employee $_employee = null): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Employee $_employee): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('employees.export');
    }
}
