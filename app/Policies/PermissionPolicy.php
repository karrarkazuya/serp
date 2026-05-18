<?php

namespace App\Policies;

use App\Models\Security\Permission;
use App\Models\User;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.read');
    }
}
