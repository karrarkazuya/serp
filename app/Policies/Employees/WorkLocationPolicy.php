<?php

namespace App\Policies\Employees;

use App\Models\Employees\WorkLocation;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class WorkLocationPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, WorkLocation $loc): bool
    {
        return $user->hasPermission('employees.read') && $this->withinActiveCompany($loc);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, WorkLocation $loc): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($loc);
    }

    public function delete(User $user, WorkLocation $loc): bool
    {
        return $user->hasPermission('employees.unlink') && $this->withinActiveCompany($loc);
    }

    public function comment(User $user, WorkLocation $loc): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($loc);
    }
}
