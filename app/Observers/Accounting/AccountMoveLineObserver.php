<?php

namespace App\Observers\Accounting;

use App\Models\Accounting\AccountMoveLine;
use RuntimeException;

/**
 * D8 (Odoo parity): posted move lines are IMMUTABLE.
 *
 * Odoo enforces this at the model layer — once a line's parent move is posted,
 * `account.move.line` rejects any update or delete that touches financial
 * fields. AccountingService::updateMove/syncLines already block edits via the
 * draft-state check on the parent move, but this observer is the backstop: a
 * future controller, a queued job, an Artisan command, a migration's data
 * patch — anything that mutates `account_move_lines` directly bypasses the
 * service. Catching it here means the ledger can never silently desync from
 * what was actually posted.
 *
 * Whitelisted fields that may change after posting (Odoo's `_get_integrity_hash_fields`
 * exclusion list, restricted to what we currently store):
 *   - `tax_base_amount`     — recomputed by tax tools, doesn't shift D/C
 *   - `updated_by`/`updated_at` — observer/eloquent bookkeeping
 *   - `deleted_at`          — soft-delete is handled separately via cancelMove
 *
 * Reset-to-draft → edit → re-post is the supported flow for editing posted
 * entries; the AccountingService gates that path via the period-lock check.
 */
class AccountMoveLineObserver
{
    private const PROTECTED_FIELDS = [
        'company_id', 'move_id', 'account_id', 'journal_id', 'partner_id',
        'product_id', 'uom_id', 'tax_line_id',
        'name', 'date', 'state',
        'debit', 'credit', 'currency', 'amount_currency',
        'discount', 'sequence',
    ];

    public function updating(AccountMoveLine $line): void
    {
        // Only protect when the line is currently posted. We look at the
        // ORIGINAL state so a legitimate state-transition update (draft → posted
        // during postMove, or posted → cancelled during cancelMove) still goes
        // through. The `state` column being on the protected list still blocks
        // sneaky direct flips because here we'd be checking original state.
        $originalState = $line->getOriginal('state');
        if ($originalState !== 'posted') {
            return;
        }

        // Allow `state` itself to transition off `posted` (cancel/draft moves
        // own that — they're driven by AccountingService).
        $dirtyProtected = collect($line->getDirty())
            ->keys()
            ->intersect(self::PROTECTED_FIELDS)
            ->reject(fn ($field) => $field === 'state')
            ->values();

        if ($dirtyProtected->isNotEmpty()) {
            throw new RuntimeException(__('accounting.err_posted_line_immutable', [
                'id'     => $line->id,
                'fields' => $dirtyProtected->implode(', '),
            ]));
        }
    }

    public function deleting(AccountMoveLine $line): void
    {
        // Soft-deleting a posted line is the same problem as updating it: the
        // ledger lost an entry that financial reports already counted. Allow
        // it only when the parent move is non-posted (the service's syncLines
        // wipes lines during draft edits) — Eloquent will have the model's
        // current state, which matches the parent at this point.
        if ($line->state === 'posted') {
            throw new RuntimeException(__('accounting.err_posted_line_no_delete', [
                'id' => $line->id,
            ]));
        }
    }
}
