<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountJournal;
use App\Models\User;

class AccountJournalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountJournal $_journal): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountJournal $_journal): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountJournal $_journal): bool
    {
        return $user->hasPermission('accounting.unlink');
    }

    public function comment(User $user, AccountJournal $_journal): bool
    {
        return $user->hasPermission('accounting.write');
    }
}
