<?php

namespace App\Policies\Employees;

use App\Models\Employees\Contract;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class ContractPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Contract $contract): bool
    {
        return $user->hasPermission('employees.read') && $this->withinActiveCompany($contract);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Contract $contract): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($contract);
    }

    public function delete(User $user, Contract $contract): bool
    {
        return $user->hasPermission('employees.unlink') && $this->withinActiveCompany($contract);
    }

    public function comment(User $user, Contract $contract): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($contract);
    }
}
