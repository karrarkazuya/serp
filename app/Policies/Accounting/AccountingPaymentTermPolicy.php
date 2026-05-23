<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingPaymentTerm;
use App\Models\User;

class AccountingPaymentTermPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingPaymentTerm $_term): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingPaymentTerm $_term): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountingPaymentTerm $_term): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, AccountingPaymentTerm $_term): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
