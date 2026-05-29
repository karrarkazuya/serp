<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountTax;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountTaxPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountTax $tax): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($tax);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountTax $tax): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($tax);
    }

    public function delete(User $user, AccountTax $tax): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($tax);
    }

    public function comment(User $user, AccountTax $tax): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($tax);
    }
}
