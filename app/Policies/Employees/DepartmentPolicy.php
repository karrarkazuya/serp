<?php

namespace App\Policies\Employees;

use App\Models\Employees\Department;
use App\Models\User;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Department $_dept): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Department $_dept): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, Department $_dept): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Department $_dept): bool
    {
        return $user->hasPermission('employees.write');
    }
}
