<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingIncoterm;
use App\Models\User;

class AccountingIncotermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingIncoterm $_incoterm): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingIncoterm $_incoterm): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountingIncoterm $_incoterm): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, AccountingIncoterm $_incoterm): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
