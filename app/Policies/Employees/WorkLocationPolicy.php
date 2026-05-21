<?php

namespace App\Policies\Employees;

use App\Models\Employees\WorkLocation;
use App\Models\User;

class WorkLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, WorkLocation $_loc): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, WorkLocation $_loc): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, WorkLocation $_loc): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, WorkLocation $_loc): bool
    {
        return $user->hasPermission('employees.write');
    }
}
