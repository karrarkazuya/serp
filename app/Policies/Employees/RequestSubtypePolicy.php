<?php

namespace App\Policies\Employees;

use App\Models\Employees\RequestSubtype;
use App\Models\User;

class RequestSubtypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.requests.config')
            || $user->hasPermission('attendance.requests.read')
            || $user->hasPermission('attendance.self.request');
    }

    public function view(User $user, ?RequestSubtype $_subtype = null): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('attendance.requests.config');
    }

    public function update(User $user, ?RequestSubtype $_subtype = null): bool
    {
        return $user->hasPermission('attendance.requests.config');
    }

    public function delete(User $user, ?RequestSubtype $_subtype = null): bool
    {
        return $user->hasPermission('attendance.requests.config');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('attendance.subtypes.export');
    }
}
