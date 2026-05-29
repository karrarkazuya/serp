<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountPayment;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountPaymentPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountPayment $payment): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($payment);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountPayment $payment): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($payment);
    }

    /**
     * Confirming a payment posts the underlying account_move via
     * AccountingService::confirmPayment(). Cancelling / resetting tears down
     * that same posted move. All three are posting-class operations and must
     * require accounting.post — not accounting.write — to preserve the
     * permission separation that gates direct AccountMove posting.
     */
    public function post(User $user, AccountPayment $payment): bool
    {
        return $user->hasPermission('accounting.post')
            && $this->withinActiveCompany($payment);
    }

    public function delete(User $user, AccountPayment $payment): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($payment);
    }

    public function comment(User $user, AccountPayment $payment): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($payment);
    }
}
