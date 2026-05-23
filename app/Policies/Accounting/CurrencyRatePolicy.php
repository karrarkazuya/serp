<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\CurrencyRate;
use App\Models\User;

class CurrencyRatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, CurrencyRate $_rate): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function update(User $user, CurrencyRate $_rate): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, CurrencyRate $_rate): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function comment(User $user, CurrencyRate $_rate): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
