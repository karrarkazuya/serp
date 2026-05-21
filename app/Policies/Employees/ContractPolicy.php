<?php

namespace App\Policies\Employees;

use App\Models\Employees\Contract;
use App\Models\User;

class ContractPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Contract $_contract): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Contract $_contract): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, Contract $_contract): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, Contract $_contract): bool
    {
        return $user->hasPermission('employees.write');
    }
}
