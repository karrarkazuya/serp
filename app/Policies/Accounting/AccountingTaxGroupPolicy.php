<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingTaxGroup;
use App\Models\User;

class AccountingTaxGroupPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingTaxGroup $_group): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingTaxGroup $_group): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountingTaxGroup $_group): bool
    {
        return $user->hasPermission('accounting.unlink');
    }
}
