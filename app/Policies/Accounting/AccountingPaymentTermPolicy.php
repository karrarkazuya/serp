<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingPaymentTerm;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountingPaymentTermPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountingPaymentTerm $term): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($term);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountingPaymentTerm $term): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($term);
    }

    public function delete(User $user, AccountingPaymentTerm $term): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($term);
    }

    public function comment(User $user, AccountingPaymentTerm $term): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($term);
    }
}
