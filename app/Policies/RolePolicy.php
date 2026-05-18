<?php

namespace App\Policies;

use App\Models\Security\Role;
use App\Models\User;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('roles.read');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.write');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->hasPermission('roles.unlink');
    }
}
