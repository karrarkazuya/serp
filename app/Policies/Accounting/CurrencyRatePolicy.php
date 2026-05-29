<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\CurrencyRate;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class CurrencyRatePolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, CurrencyRate $rate): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($rate);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function update(User $user, CurrencyRate $rate): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($rate);
    }

    public function delete(User $user, CurrencyRate $rate): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($rate);
    }

    public function comment(User $user, CurrencyRate $rate): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($rate);
    }
}
