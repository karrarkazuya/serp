<?php

namespace App\Policies\Employees;

use App\Models\Employees\Job;
use App\Models\User;

class JobPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Job $_job): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Job $_job): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, Job $_job): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Job $_job): bool
    {
        return $user->hasPermission('employees.write');
    }
}
