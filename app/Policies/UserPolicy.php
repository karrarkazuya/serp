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
        return $user->hasPermission('users.write') || $user->id === $model->id;
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
