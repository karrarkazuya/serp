<?php

namespace App\Policies\Employees;

use App\Models\Employees\EmploymentType;
use App\Models\User;

class EmploymentTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, EmploymentType $_type): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, EmploymentType $_type): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, EmploymentType $_type): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, EmploymentType $_type): bool
    {
        return $user->hasPermission('employees.write');
    }
}
