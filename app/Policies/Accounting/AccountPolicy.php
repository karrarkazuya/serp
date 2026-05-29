<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\Account;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, Account $account): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($account);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, Account $account): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($account);
    }

    public function delete(User $user, Account $account): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($account);
    }

    public function comment(User $user, Account $account): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($account);
    }
}
