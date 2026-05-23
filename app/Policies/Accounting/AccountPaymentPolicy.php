<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountPayment;
use App\Models\User;

class AccountPaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function comment(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
