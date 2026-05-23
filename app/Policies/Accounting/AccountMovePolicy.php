<?php

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountMove;
use App\Models\User;

class AccountMovePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function view(User $user, AccountMove $_move): bool
    {
        return $user->hasPermission('accounting.read');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('accounting.create');
    }

    public function update(User $user, AccountMove $move): bool
    {
        // Only drafts can be edited; posted/cancelled need stronger gate.
        if (!$move->isDraft()) {
            return $user->hasPermission('accounting.post');
        }
        return $user->hasPermission('accounting.write');
    }

    public function delete(User $user, AccountMove $move): bool
    {
        if ($move->move_type !== 'entry' && !$move->isCancelled()) {
            return false;
        }

        if ($move->isPosted()) {
            return false;
        }

        return $user->hasPermission('accounting.unlink');
    }

    public function post(User $user, AccountMove $_move): bool
    {
        return $user->hasPermission('accounting.post');
    }

    public function comment(User $user, AccountMove $_move): bool
    {
        return $user->hasPermission('accounting.write');
    }

    public function export(User $user): bool
    {
        return $user->hasPermission('accounting.export');
    }
}
