<?php

namespace App\Policies\Employees;

use App\Models\Employees\Goal;
use App\Models\User;

class GoalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Goal $_goal): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Goal $_goal): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, Goal $_goal): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Goal $_goal): bool
    {
        return $user->hasPermission('employees.write');
    }
}
