<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingTaxGroup;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountingTaxGroupPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingTaxGroup $group): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($group);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingTaxGroup $group): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($group);
    }

    public function delete(User $user, AccountingTaxGroup $group): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($group);
    }

    public function comment(User $user, AccountingTaxGroup $group): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($group);
    }
}
