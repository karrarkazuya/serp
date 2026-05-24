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

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.write');
    }

    /**
     * Confirming a payment posts the underlying account_move via
     * AccountingService::confirmPayment(). Cancelling / resetting tears down
     * that same posted move. All three are posting-class operations and must
     * require accounting.post — not accounting.write — to preserve the
     * permission separation that gates direct AccountMove posting.
     */
    public function post(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.post');
    }

    public function delete(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
