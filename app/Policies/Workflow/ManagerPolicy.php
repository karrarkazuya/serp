<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\Manager;

class ManagerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workflow.config.read');
    }

    public function view(User $user, Manager $_manager): bool
    {
        return $user->hasPermission('workflow.config.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.config.write');
    }

    public function update(User $user, Manager $_manager): bool
    {
        return $user->hasPermission('workflow.config.write');
    }

    public function delete(User $user, Manager $_manager): bool
    {
        return $user->hasPermission('workflow.config.unlink');
    }

    public function comment(User $user, Manager $_manager): bool
    {
        return $user->hasPermission('workflow.config.write');
    }
}
