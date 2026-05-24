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

    /**
     * D3 (Odoo parity): cancelling a posted INVOICE / BILL / CREDIT-NOTE /
     * REFUND removes it from financial reports — Odoo restricts this path to
     * accounting managers (we use the `accounting.lock` bypass permission as
     * the closest equivalent of "accounting manager"). Pure journal entries
     * (`move_type=entry`) remain cancellable by any `accounting.post` holder
     * since they're a recoverable bookkeeping action, not a customer-visible
     * commercial document. Drafts of any type bypass the gate because no
     * report is yet affected.
     */
    public function cancel(User $user, AccountMove $move): bool
    {
        if (!$user->hasPermission('accounting.post')) {
            return false;
        }
        if (!$move->isPosted()) {
            return true; // drafts: anyone with post permission can cancel
        }
        if ($move->move_type === 'entry') {
            return true; // pure JE: post permission is sufficient
        }
        // posted invoice / bill / credit-note / refund → manager-only
        return $user->hasPermission('accounting.lock');
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
