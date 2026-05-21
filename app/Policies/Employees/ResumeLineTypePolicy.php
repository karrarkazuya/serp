<?php

namespace App\Policies\Employees;

use App\Models\Employees\ResumeLineType;
use App\Models\User;

class ResumeLineTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, ResumeLineType $_type): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, ResumeLineType $_type): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, ResumeLineType $_type): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, ResumeLineType $_type): bool
    {
        return $user->hasPermission('employees.write');
    }
}
