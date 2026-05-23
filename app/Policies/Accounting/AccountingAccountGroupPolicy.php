<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingAccountGroup;
use App\Models\User;

class AccountingAccountGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingAccountGroup $_group): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingAccountGroup $_group): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountingAccountGroup $_group): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, AccountingAccountGroup $_group): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
