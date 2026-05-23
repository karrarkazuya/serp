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

    public function post(User $user, AccountPayment $_payment): bool
    {
        return $user->hasPermission('accounting.write');
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
