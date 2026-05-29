<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountJournal;
use App\Models\User;
use App\Policies\Concerns\ScopesByCompany;

class AccountJournalPolicy
{
    use ScopesByCompany;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountJournal $journal): bool
    {
        return $user->hasPermission('accounting.read')
            && $this->withinActiveCompany($journal);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountJournal $journal): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($journal);
    }

    public function delete(User $user, AccountJournal $journal): bool
    {
        return $user->hasPermission('accounting.unlink')
            && $this->withinActiveCompany($journal);
    }

    public function comment(User $user, AccountJournal $journal): bool
    {
        return $user->hasPermission('accounting.write')
            && $this->withinActiveCompany($journal);
    }
}
