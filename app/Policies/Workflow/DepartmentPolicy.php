<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\Department;

class DepartmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workflow.config.read');
    }

    public function view(User $user, Department $_dept): bool
    {
        return $user->hasPermission('workflow.config.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.config.write');
    }

    public function update(User $user, Department $_dept): bool
    {
        return $user->hasPermission('workflow.config.write');
    }

    public function delete(User $user, Department $_dept): bool
    {
        return $user->hasPermission('workflow.config.unlink');
    }

    public function comment(User $user, Department $_dept): bool
    {
        return $user->hasPermission('workflow.config.write');
    }
}
