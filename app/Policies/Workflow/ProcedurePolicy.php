<?php

namespace App\Policies\Workflow;

use App\Models\User;
use App\Models\Workflow\Procedure;
use Illuminate\Support\Facades\DB;

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

        return $this->isActiveViewer($user, $procedure);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('workflow.procedures.create');
    }

    public function update(User $user, Procedure $procedure): bool
    {
        if (!$user->hasPermission('workflow.procedures.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;

        return $this->isActiveViewer($user, $procedure);
    }

    public function delete(User $user, Procedure $procedure): bool
    {
        return false;
    }

    public function comment(User $user, Procedure $procedure): bool
    {
        if (!$user->hasPermission('workflow.procedures.write')) return false;
        if ($user->hasPermission('workflow.admin')) return true;

        return $this->isActiveViewer($user, $procedure);
    }

    private function isActiveViewer(User $user, Procedure $procedure): bool
    {
        return DB::table('workflow_procedure_viewers')
            ->where('user_id', $user->id)
            ->where('procedure_id', $procedure->id)
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('workflow_users')
                ->where('user_id', $user->id)->where('active', true))
            ->exists();
    }
}
