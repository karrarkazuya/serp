<?php

namespace App\Policies\Employees;

use App\Models\Employees\Job;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class JobPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, Job $job): bool
    {
        return $user->hasPermission('employees.read') && $this->withinActiveCompany($job);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, Job $job): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($job);
    }

    public function delete(User $user, Job $job): bool
    {
        return $user->hasPermission('employees.unlink') && $this->withinActiveCompany($job);
    }

    public function comment(User $user, Job $job): bool
    {
        return $user->hasPermission('employees.write') && $this->withinActiveCompany($job);
    }
}
