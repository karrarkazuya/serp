<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\Account;
use App\Models\User;

class AccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, Account $_account): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, Account $_account): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, Account $_account): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, Account $_account): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
