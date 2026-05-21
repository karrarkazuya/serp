<?php

namespace App\Policies\Employees;

use App\Models\Employees\Badge;
use App\Models\User;

class BadgePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Badge $_badge): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Badge $_badge): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, Badge $_badge): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Badge $_badge): bool
    {
        return $user->hasPermission('employees.write');
    }
}
