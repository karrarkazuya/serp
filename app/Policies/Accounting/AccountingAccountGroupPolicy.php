<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingAccountGroup;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountingAccountGroupPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingAccountGroup $group): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($group);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingAccountGroup $group): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($group);
    }

    public function delete(User $user, AccountingAccountGroup $group): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($group);
    }

    public function comment(User $user, AccountingAccountGroup $group): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($group);
    }
}
