<?php

namespace App\Policies\Employees;

use App\Models\Employees\SkillType;
use App\Models\User;

class SkillTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function view(User $user, SkillType $_skillType): bool
    {
        return $user->hasPermission('employees.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function update(User $user, SkillType $_skillType): bool
    {
        return $user->hasPermission('employees.write');
    }

    public function delete(User $user, SkillType $_skillType): bool
    {
        return $user->hasPermission('employees.unlink');
    }

    public function comment(User $user, SkillType $_skillType): bool
    {
        return $user->hasPermission('employees.write');
    }
}
