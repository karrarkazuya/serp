<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountTax;
use App\Models\User;

class AccountTaxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountTax $_tax): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountTax $_tax): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountTax $_tax): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, AccountTax $_tax): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
