<?php

namespace App\Policies\Employees;

use App\Models\Employees\RequestSubtype;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class RequestSubtypePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.requests.config')
            || $user->hasPermission('attendance.requests.read')
            || $user->hasPermission('attendance.self.request');
    }

    public function view(User $user, ?RequestSubtype $subtype = null): bool
    {
        if (!$this->viewAny($user)) return false;
        return $subtype === null || $this->withinActiveCompany($subtype);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.requests.config');
    }

    public function update(User $user, ?RequestSubtype $subtype = null): bool
    {
        if (!$user->hasPermission('attendance.requests.config')) return false;
        return $subtype === null || $this->withinActiveCompany($subtype);
    }

    public function delete(User $user, ?RequestSubtype $subtype = null): bool
    {
        if (!$user->hasPermission('attendance.requests.config')) return false;
        return $subtype === null || $this->withinActiveCompany($subtype);
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('attendance.subtypes.export');
    }
}
