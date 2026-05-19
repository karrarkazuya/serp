<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\WorkflowUser;

class WorkflowUserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workflow.config.read');
    }

    public function view(User $user, WorkflowUser $_wu): bool
    {
        return $user->hasPermission('workflow.config.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.config.write');
    }

    public function update(User $user, WorkflowUser $_wu): bool
    {
        return $user->hasPermission('workflow.config.write');
    }

    public function delete(User $user, WorkflowUser $_wu): bool
    {
        return $user->hasPermission('workflow.config.unlink');
    }

    public function comment(User $user, WorkflowUser $_wu): bool
    {
        return $user->hasPermission('workflow.config.write');
    }
}
