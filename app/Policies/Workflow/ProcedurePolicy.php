<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\Procedure;

class ProcedurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('workflow.procedures.read');
    }

    public function view(User $user, Procedure $procedure): bool
    {
        if (!$user->hasPermission('workflow.procedures.read')) return false;
        if ($user->hasPermission('workflow.admin')) return true;

        return $procedure->viewers()->where('users.id', $user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.procedures.create');
    }

    public function update(User $user, Procedure $procedure): bool
    {
        if (!$user->hasPermission('workflow.procedures.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;

        return $procedure->viewers()->where('users.id', $user->id)->exists();
    }

    public function delete(User $user, Procedure $procedure): bool
    {
        return false;
    }

    public function comment(User $user, Procedure $procedure): bool
    {
        if (!$user->hasPermission('workflow.procedures.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;

        return $procedure->viewers()->where('users.id', $user->id)->exists();
    }
}
