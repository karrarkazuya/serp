<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.read');
    }

    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('users.read') || $user->id === $model->id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.create');
    }

    public function update(User $user, User $model): bool
    {
        // Self-edit via the Settings/Users CRUD requires users.write — the controller
        // routes are gated by `permission:users.write` middleware, so there is no
        // "always allow self" branch here. If a profile-self-edit endpoint is
        // added later, wire it through a dedicated policy ability.
        return $user->hasPermission('users.write');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->hasPermission('users.unlink') && $user->id !== $model->id;
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('users.export');
    }
}
