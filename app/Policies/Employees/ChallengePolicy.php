<?php

namespace App\Policies\Employees;

use App\Models\Employees\Challenge;
use App\Models\User;

class ChallengePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Challenge $_challenge): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Challenge $_challenge): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, Challenge $_challenge): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Challenge $_challenge): bool
    {
        return $user->hasPermission('employees.write');
    }
}
