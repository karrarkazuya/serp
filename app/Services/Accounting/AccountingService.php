<?php

namespace App\Services\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Models\Accounting\AccountPartialReconcile;
use App\Models\Accounting\AccountPayment;
use App\Models\Accounting\AccountTax;
use App\Models\Accounting\CurrencyRate;
use App\Models\Settings\Company;
use App\Services\Chatter\ChatterService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Unified Accounting System
 *
 * Single entry point for every accounting write and calculation in Phase 1:
 *   - Chart of Accounts CRUD
 *   - Journal CRUD
 *   - Manual Journal Entry (account_moves + account_move_lines) lifecycle:
 *       create / update / post / reset to draft / cancel / reverse / delete
 *   - Balance validation (debit == credit)
 *   - Sequence reservation per journal
 *   - Account balance computation
 *
 * Per the project rules: this service contains business logic + chatter.
 * It does NOT open DB::transaction; controllers wrap calls in a transaction.
 * It does NOT set uuid / created_by / updated_by; AuditableObserver does.
 */
class AccountingService
{
    /** Rounding scale used for balance equality checks. */
    public const SCALE = 2;

    public function __construct(
        private readonly ChatterService $chatterService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Accounts
    // ─────────────────────────────────────────────────────────────────────────

    public function createAccount(array $data): Account
    {
        $data['internal_type'] = $this->resolveInternalType($data['account_type'] ?? 'other');
        $account = Account::create($data);
        $this->chatterService->logCreated($account, 'Account');
        return $account;
    }

    public function updateAccount(Account $account, array $data): Account
    {
        if (array_key_exists('account_type', $data)) {
            $data['internal_type'] = $this->resolveInternalType($data['account_type']);
        }
        $changes = $this->detectChanges($account, $data);
        $account->update($data);
        if (!empty($changes)) {
            $this->chatterService->logUpdated($account, $changes, 'Account');
        }
        return $account->fresh();
    }

    public function archiveAccount(Account $account): Account
    {
        $account->update(['active' => false]);
        $this->chatterService->logArchived($account, 'Account');
        return $account;
    }

    public function unarchiveAccount(Account $account): Account
    {
        $account->update(['active' => true]);
        $this->chatterService->logUnarchived($account, 'Account');
        return $account;
    }

    public function deleteAccount(Account $account): void
    {
        if ($account->moveLines()->exists()) {
            throw new RuntimeException('Cannot delete an account that has journal entries. Archive it instead.');
        }
        $this->chatterService->log($account, 'Account deleted.', 'system');
        $account->delete();
    }

    private function resolveInternalType(string $accountType): string
    {
        return Account::INTERNAL_TYPE_MAP[$accountType] ?? 'other';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Journals
    // ─────────────────────────────────────────────────────────────────────────

    public function createJournal(array $data): AccountJournal
    {
        $data['sequence_next_number'] = $data['sequence_next_number'] ?? 1;
        $data['sequence_padding']     = $data['sequence_padding'] ?? 4;
        $journal = AccountJournal::create($data);
        $this->chatterService->logCreated($journal, 'Journal');
        return $journal;
    }

    public function updateJournal(AccountJournal $journal, array $data): AccountJournal
    {
        $changes = $this->detectChanges($journal, $data);
        $journal->update($data);
        if (!empty($changes)) {
            $this->chatterService->logUpdated($journal, $changes, 'Journal');
        }
        return $journal->fresh();
    }

    public function archiveJournal(AccountJournal $journal): AccountJournal
    {
        $journal->update(['active' => false]);
        $this->chatterService->logArchived($journal, 'Journal');
        return $journal;
    }

    public function unarchiveJournal(AccountJournal $journal): AccountJournal
    {
        $journal->update(['active' => true]);
        $this->chatterService->logUnarchived($journal, 'Journal');
        return $journal;
    }

    public function deleteJournal(AccountJournal $journal): void
    {
        if ($journal->moves()->exists()) {
            throw new RuntimeException('Cannot delete a journal that has entries. Archive it instead.');
        }
        $this->chatterService->log($journal, 'Journal deleted.', 'system');
        $journal->delete();
    }

    /**
     * Reserve and consume the next sequence number for a journal.
     * Returns the formatted move name, e.g. "INV/2026/00001".
     *
     * The caller is responsible for the surrounding DB::transaction.
     */
    public function reserveSequenceForJournal(AccountJournal $journal, Carbon $date): string
    {
        // Lock the journal row to prevent concurrent sequence collisions.
        $locked = AccountJournal::whereKey($journal->id)->lockForUpdate()->first();
        if (!$locked) {
            throw new RuntimeException('Journal not found while reserving sequence.');
        }

        $year    = (int) $date->format('Y');
        $padding = max(1, (int) $locked->sequence_padding);

        // Odoo parity (O4): reset the sequence counter when the move date
        // enters a new year. Existing per-year scope is the standard ir.sequence
        // behaviour for accounting journals. Without the reset, INV/2025/00150
        // is followed by INV/2026/00151 (continues across the year boundary);
        // with the reset, the 2026 series starts cleanly at INV/2026/00001.
        $lastYear = (int) ($locked->sequence_last_year ?? 0);
        if ($lastYear !== 0 && $year !== $lastYear) {
            $next = 1;
        } else {
            $next = (int) $locked->sequence_next_number;
        }

        // Odoo format: `{prefix}/{year}/{padded}` (the slash between prefix and
        // year was missing before — output was `INV2025/00001` instead of
        // `INV/2025/00001`). Trim trailing slashes from the stored prefix so
        // legacy "INV/" values don't produce `INV//2026/...`; empty prefix
        // drops the leading separator entirely.
        $padded = str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
        $prefix = rtrim(trim((string) $locked->sequence_prefix), '/');
        $name   = $prefix === ''
            ? "{$year}/{$padded}"
            : "{$prefix}/{$year}/{$padded}";

        $locked->update([
            'sequence_next_number' => $next + 1,
            'sequence_last_year'   => $year,
        ]);

        return $name;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Moves (Journal Entries)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create a draft journal entry with lines.
     *
     * @param  array  $data   Move header fields (journal_id, date, ref, partner_id, narration, company_id, currency, move_type)
     * @param  array  $lines  Each: ['account_id', 'name', 'debit', 'credit', 'partner_id'?, 'currency'?, 'amount_currency'?, 'sequence'?]
     */
    public function createMove(array $data, array $lines): AccountMove
    {
        $journal = AccountJournal::findOrFail($data['journal_id']);
        $this->assertSameCompany($journal->company_id, (int) $data['company_id'], 'journal');

        // O9 (Odoo parity): when a journal pins a currency (e.g. a USD-only
        // bank journal), every move in it must use that currency. Falling
        // through to "default it from the journal" silently fixes the
        // mismatch instead of surfacing a user error.
        if (!empty($journal->currency)) {
            $requested = $data['currency'] ?? null;
            if ($requested !== null && $requested !== '' && $requested !== $journal->currency) {
                throw new RuntimeException(sprintf(
                    "Journal '%s' is pinned to currency %s; this entry cannot use %s.",
                    $journal->name,
                    $journal->currency,
                    $requested
                ));
            }
            $data['currency'] = $journal->currency;
        }

        $data['state']        = 'draft';
        $data['payment_state'] = 'not_paid';
        $data['move_type']    = $data['move_type'] ?? 'entry';
        $data['currency']     = $data['currency'] ?? $journal->currency;
        $data['amount_total'] = 0;
        // D9 (Odoo parity): drafts use '/' as the name placeholder. postMove
        // treats '/' as "no name yet" and reserves a real sequence; reset
        // moves keep their real name so the sequence isn't wasted.
        $data['name']         = !empty($data['name']) ? $data['name'] : '/';

        $move = AccountMove::create($data);
        $this->syncLines($move, $lines);
        $move->refresh();
        $move->update(['amount_total' => $this->computeTotal($move)]);

        $this->chatterService->logCreated($move, 'Journal Entry');
        return $move->fresh();
    }

    /**
     * Create an invoice or vendor bill from document item rows.
     *
     * Customer invoices credit income lines and debit the receivable control
     * account. Vendor bills debit expense lines and credit the payable control
     * account.
     */
    public function createDocument(array $data, array $items): AccountMove
    {
        $lines = $this->buildDocumentLines($data, $items);
        unset($data['control_account_id']);
        $data = $this->resolveInvoiceDueDate($data);

        return $this->createMove($data, $lines);
    }

    /**
     * Update a draft move and replace its lines.
     * Posted or cancelled moves cannot be edited.
     */
    public function updateMove(AccountMove $move, array $data, array $lines): AccountMove
    {
        if (!$move->isDraft()) {
            throw new RuntimeException('Only draft entries can be edited. Reset to draft first.');
        }

        if (isset($data['journal_id']) && (int) $data['journal_id'] !== (int) $move->journal_id) {
            $newJournal = AccountJournal::findOrFail($data['journal_id']);
            $this->assertSameCompany($newJournal->company_id, $move->company_id, 'journal');
        }

        $changes = $this->detectChanges($move, $data);
        $move->update($data);

        $this->syncLines($move, $lines);
        $move->refresh();
        $move->update(['amount_total' => $this->computeTotal($move)]);

        if (!empty($changes)) {
            $this->chatterService->logUpdated($move, $changes, 'Journal Entry');
        }
        return $move->fresh();
    }

    public function updateDocument(AccountMove $move, array $data, array $items): AccountMove
    {
        $lines = $this->buildDocumentLines($data, $items);
        unset($data['control_account_id']);
        $data = $this->resolveInvoiceDueDate($data);

        return $this->updateMove($move, $data, $lines);
    }

    /**
     * Post a draft move: validate balance, reserve sequence, mark posted.
     */
    public function postMove(AccountMove $move): AccountMove
    {
        // D11 (Odoo parity): row-level lock prevents two concurrent posts of
        // the same draft both passing the isDraft() check, both reserving
        // sequence numbers, and both stamping the move 'posted' (with one
        // sequence number wasted on a now-overwritten state). Re-read the
        // row inside the lock so we see the freshest version.
        $move = AccountMove::whereKey($move->id)->lockForUpdate()->first() ?? $move;

        if ($move->isPosted()) {
            return $move;
        }
        if ($move->isCancelled()) {
            throw new RuntimeException('Cancelled entries cannot be posted.');
        }

        $move->load(['journal', 'lines']);

        if ($move->lines->isEmpty()) {
            throw new RuntimeException('Cannot post an entry with no lines.');
        }

        $this->assertBalanced($move);
        $this->assertLinesValid($move);
        $this->assertDateNotLocked($move);

        // R4.11 (Odoo parity): an invoice/bill/credit-note/refund without ANY
        // receivable/payable line is malformed — payment_state would forever
        // read 'paid' because documentResidual sums an empty set. Guard at
        // post time so a manual JE classified as a document type can't slip
        // through. Pure journal entries (`move_type=entry`) are allowed to
        // have no AR/AP lines (and frequently don't).
        if (in_array($move->move_type, ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true)
            && $this->documentCounterpartLines($move)->isEmpty()
        ) {
            $expectedType = in_array($move->move_type, ['out_invoice', 'out_refund'], true) ? 'receivable' : 'payable';
            throw new RuntimeException(sprintf(
                'A %s must include at least one %s line. None found.',
                AccountMove::MOVE_TYPES[$move->move_type] ?? $move->move_type,
                $expectedType
            ));
        }

        // D9 (Odoo parity): drafts use name='/' as a placeholder; treat that
        // as "no name yet" so the sequence reservation still fires. A posted
        // move that was reset to draft (and kept its real name) reuses it.
        $existingName = $move->name && $move->name !== '/' ? $move->name : null;
        $name         = $existingName ?: $this->reserveSequenceForJournal($move->journal, Carbon::parse($move->date));

        $move->update([
            'name'         => $name,
            'state'        => 'posted',
            'posted_at'    => now(),
            'posted_by'    => Auth::id(),
            'amount_total' => $this->computeTotal($move),
        ]);

        $move->lines()->update(['state' => 'posted']);

        $this->chatterService->log($move, "Entry posted as {$name}.", 'system');

        // O5 (Odoo parity): when posting a credit note / reversal that points
        // back at an original move via `reversed_move_id`, auto-reconcile their
        // counterpart lines. Previously this happened inside createCreditNote
        // (auto-post), but with the draft-first flow the matching has to fire
        // at post time so manual edits to the draft still propagate to the
        // residual update.
        $this->autoReconcileWithReversedMove($move);

        return $move->fresh();
    }

    /**
     * If this move reverses another (set via `reversed_move_id` on the credit
     * note / reversal draft), match their receivable/payable counterpart lines
     * up to the smaller of the two residuals and refresh the payment state on
     * both. Only fires when:
     *   - Both moves are posted
     *   - Both have a single counterpart line on the same reconcilable account
     *   - The two residuals overlap (>= 0.005 in base currency)
     */
    private function autoReconcileWithReversedMove(AccountMove $move): void
    {
        if (!$move->reversed_move_id) {
            return;
        }

        $original = AccountMove::find($move->reversed_move_id);
        if (!$original || !$original->isPosted()) {
            return;
        }

        // Only invoice-class documents have receivable/payable counterpart lines.
        // Pure journal entries (move_type=entry) can have arbitrary structures;
        // skip auto-reconcile — the user can match manually if needed.
        $documentTypes = ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'];
        if (!in_array($move->move_type, $documentTypes, true) || !in_array($original->move_type, $documentTypes, true)) {
            return;
        }

        // D1: original may have multiple installment lines (multi-installment
        // invoice); the credit note typically has one counterpart line (it
        // doesn't inherit the original's payment term). Walk the original's
        // installments oldest-first and consume the credit note's residual
        // against each.
        $originalLines       = $this->documentCounterpartLines($original);
        $newCounterpartLines = $this->documentCounterpartLines($move);
        if ($originalLines->isEmpty() || $newCounterpartLines->isEmpty()) {
            return;
        }

        $creditResidual = round($newCounterpartLines->sum(fn (AccountMoveLine $l) => $this->getLineResidual($l)), self::SCALE);
        if ($creditResidual <= 0.005) {
            return;
        }

        $reconciled = false;
        foreach ($originalLines as $origLine) {
            if ($creditResidual <= 0.005) break;
            $origResidual = $this->getLineResidual($origLine);
            if ($origResidual <= 0.005) continue;

            // Pull from the credit note's installments in order too, so a CN
            // with its own multi-installment term (unusual but possible)
            // still reconciles cleanly.
            foreach ($newCounterpartLines as $newLine) {
                if ($creditResidual <= 0.005 || $origResidual <= 0.005) break;
                $newRes = $this->getLineResidual($newLine);
                if ($newRes <= 0.005) continue;

                $matchAmount = min($origResidual, $newRes);
                $this->reconcileLines($origLine, $newLine, $matchAmount, Carbon::parse($move->date));
                $origResidual   = round($origResidual - $matchAmount, self::SCALE);
                $creditResidual = round($creditResidual - $matchAmount, self::SCALE);
                $reconciled = true;
            }
        }

        if ($reconciled) {
            $this->refreshPaymentState($original);
            $this->refreshPaymentState($move);
        }
    }

    /**
     * Reset a posted move back to draft.
     * The sequence number is NOT released; the move keeps its name so audit-trail
     * integrity is preserved (no gaps, no number re-use).
     */
    public function resetMoveToDraft(AccountMove $move): AccountMove
    {
        if ($move->isDraft()) {
            return $move;
        }

        // Reset → edit → re-post is otherwise a fully-supported flow, but if
        // the entry's date sits inside a locked period, reset alone removes it
        // from financial reports (state != 'posted'). The period lock is meant
        // to freeze the historical ledger in place; enforce it here too, with
        // the same bypass rule postMove uses (accounting.lock permission).
        $this->assertDateNotLocked($move);

        $lineIds = $move->lines()->pluck('id');
        AccountPartialReconcile::where(function ($q) use ($lineIds) {
            $q->whereIn('debit_move_line_id', $lineIds)
              ->orWhereIn('credit_move_line_id', $lineIds);
        })->delete();

        $move->update([
            'state'         => 'draft',
            'payment_state' => 'not_paid',
            'posted_at'     => null,
            'posted_by'     => null,
        ]);
        $move->lines()->update(['state' => 'draft']);

        $this->chatterService->log($move, 'Entry reset to draft.', 'system');
        return $move->fresh();
    }

    public function cancelMove(AccountMove $move): AccountMove
    {
        if ($move->isCancelled()) {
            return $move;
        }

        // Cancelling a posted entry that sits inside a locked period would
        // remove it from financial reports — same period-lock bypass concern
        // as resetMoveToDraft. Skip the check for drafts (nothing posted to
        // protect from disappearing).
        $wasPosted = $move->isPosted();
        if ($wasPosted) {
            $this->assertDateNotLocked($move);
        }

        $lineIds = $move->lines()->pluck('id');
        AccountPartialReconcile::where(function ($q) use ($lineIds) {
            $q->whereIn('debit_move_line_id', $lineIds)
              ->orWhereIn('credit_move_line_id', $lineIds);
        })->delete();

        // O8 (Odoo parity): mark a cancelled posted move's name with a
        // [CANCELLED] prefix so the sequence stays visible in journal listings
        // (and no future move can reuse the same number cosmetically). Draft
        // cancels have no name yet, so nothing to mark.
        $updates = ['state' => 'cancelled', 'payment_state' => 'not_paid'];
        if ($wasPosted && $move->name && !str_starts_with($move->name, '[CANCELLED]')) {
            $updates['name'] = '[CANCELLED] ' . $move->name;
        }

        $move->update($updates);
        $move->lines()->update(['state' => 'cancelled']);
        $this->chatterService->log($move, 'Entry cancelled.', 'system');
        return $move->fresh();
    }

    public function registerDocumentPayment(AccountMove $move, array $data = []): AccountPayment
    {
        if (!$move->isPosted()) {
            throw new RuntimeException('Only posted documents can be paid.');
        }

        if (!in_array($move->move_type, ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true)) {
            throw new RuntimeException('Only invoices, bills, and refunds can be paid.');
        }

        $move->loadMissing(['lines.account', 'journal']);

        // D1 (Odoo parity): multi-installment invoices have N receivable
        // lines, one per payment-term schedule line. Payments consume them
        // oldest-first (by date_maturity) — matching Odoo's default
        // reconciliation policy. We sum residuals across all installments to
        // decide if the doc is fully paid.
        $counterpartLines = $this->documentCounterpartLines($move);
        $residual         = round($counterpartLines->sum(fn (AccountMoveLine $l) => $this->getLineResidual($l)), self::SCALE);

        if ($residual <= 0) {
            throw new RuntimeException('This document is already fully paid.');
        }

        $amount = round((float) ($data['amount'] ?? $residual), self::SCALE);
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }
        // Allow overpayments: reconcile only up to the outstanding residual.
        $reconcileAmount = min($amount, $residual);

        $journal = isset($data['journal_id'])
            ? AccountJournal::findOrFail($data['journal_id'])
            : $this->defaultPaymentJournal($move);
        $this->assertSameCompany($journal->company_id, $move->company_id, 'payment journal');

        if (!$journal->default_account_id) {
            throw new RuntimeException('The selected payment journal needs a default liquidity account.');
        }

        $date = Carbon::parse($data['date'] ?? now()->toDateString());
        $paymentType = in_array($move->move_type, ['out_invoice', 'in_refund'], true) ? 'inbound' : 'outbound';
        $memo = $data['memo'] ?? 'Payment for ' . ($move->name ?: "#{$move->id}");

        // O11 (Odoo parity): if the journal has an outstanding receipts/payments
        // account configured, route the liquidity leg through it. The payment
        // sits in the outstanding account until bank reconciliation moves it
        // to the actual bank GL — meanwhile the invoice flips to `in_payment`
        // (see refreshPaymentState). When no outstanding account is set, fall
        // back to the journal's default_account_id (legacy "direct" behaviour).
        $liquidityAccountId = $this->resolvePaymentLiquidityAccount($journal, $paymentType);

        // The payment's counterpart line uses the SAME account as the invoice's
        // receivable/payable lines (every installment shares the control account
        // — only date_maturity differs). Picking the first counterpart line is
        // safe because all installments live on the same account.
        $counterpartAccountId = $counterpartLines->first()->account_id;

        $lines = $paymentType === 'inbound'
            ? [
                ['account_id' => $liquidityAccountId,    'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => $amount, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $counterpartAccountId,  'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => 0, 'credit' => $amount, 'sequence' => 20],
            ]
            : [
                ['account_id' => $counterpartAccountId,  'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => $amount, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $liquidityAccountId,    'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => 0, 'credit' => $amount, 'sequence' => 20],
            ];

        $paymentMove = $this->createMove([
            'company_id' => $move->company_id,
            'journal_id' => $journal->id,
            'partner_id' => $move->partner_id,
            'date' => $date->toDateString(),
            'move_type' => 'entry',
            'currency' => $data['currency'] ?? $move->currency,
            'ref' => $memo,
        ], $lines);

        $paymentMove = $this->postMove($paymentMove);
        $paymentLine = $paymentMove->lines()
            ->where('account_id', $counterpartAccountId)
            ->where('partner_id', $move->partner_id)
            ->firstOrFail();

        // D1: distribute the payment across installments oldest-first.
        // Each partial-reconcile entry pairs ONE invoice installment line
        // against the payment's counterpart line, until $reconcileAmount is
        // fully consumed. Excess landing on the last installment is fine —
        // overpayments leave residual on the payment side.
        $remaining = $reconcileAmount;
        foreach ($counterpartLines as $invLine) {
            if ($remaining <= 0.005) break;

            $lineResidual = $this->getLineResidual($invLine);
            if ($lineResidual <= 0.005) continue;

            $matchAmount = min($remaining, $lineResidual);
            $this->reconcileLines($invLine, $paymentLine, $matchAmount, $date);
            $remaining = round($remaining - $matchAmount, self::SCALE);
        }

        $this->refreshPaymentState($move);

        $payment = AccountPayment::create([
            'company_id' => $move->company_id,
            'journal_id' => $journal->id,
            'move_id' => $paymentMove->id,
            'partner_id' => $move->partner_id,
            'paired_document_id' => $move->id,
            'payment_type' => $paymentType,
            'date' => $date->toDateString(),
            'amount' => $amount,
            'currency' => $move->currency,
            'memo' => $memo,
            // O7 (Odoo parity): the underlying account_move is already posted
            // + reconciled before we get here, so the AccountPayment itself
            // must be 'posted'. Falling through to the DB default ('draft')
            // would leave the payment looking unconfirmed even though the
            // ledger is already moved.
            'state' => 'posted',
        ]);

        $this->chatterService->log($move, sprintf('Payment registered: %.2f %s.', $amount, $move->currency ?: ''), 'system');

        return $payment;
    }

    public function createStandalonePayment(array $data): AccountPayment
    {
        $journal = AccountJournal::findOrFail($data['journal_id']);

        if (!$journal->default_account_id) {
            throw new RuntimeException('The selected payment journal needs a default liquidity account.');
        }

        $amount = round((float) ($data['amount'] ?? 0), self::SCALE);
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }

        $paymentType = $data['payment_type'];
        $date        = $data['date'] ?? now()->toDateString();
        $memo        = $data['memo'] ?? '';
        $partnerId   = $data['partner_id'] ?? null;
        $currency    = $data['currency'] ?? null;

        // O11 (Odoo parity): the liquidity leg goes through the journal's
        // outstanding receipts/payments account when configured, fallback to
        // default_account_id (legacy direct posting). The OTHER leg (the
        // counterpart that records who the payment is to/from) defaults to
        // the journal's suspense account unless the user picks something
        // explicit via destination_account_id.
        $liquidityAccountId = $this->resolvePaymentLiquidityAccount($journal, $paymentType);

        $counterpartAccountId = $data['destination_account_id']
            ?? $journal->suspense_account_id
            ?? $journal->default_account_id;

        $lines = $paymentType === 'inbound'
            ? [
                ['account_id' => $liquidityAccountId,    'partner_id' => $partnerId, 'name' => $memo ?: 'Payment', 'debit' => $amount, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $counterpartAccountId,  'partner_id' => $partnerId, 'name' => $memo ?: 'Payment', 'debit' => 0, 'credit' => $amount, 'sequence' => 20],
            ]
            : [
                ['account_id' => $counterpartAccountId,  'partner_id' => $partnerId, 'name' => $memo ?: 'Payment', 'debit' => $amount, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $liquidityAccountId,    'partner_id' => $partnerId, 'name' => $memo ?: 'Payment', 'debit' => 0, 'credit' => $amount, 'sequence' => 20],
            ];

        $paymentMove = $this->createMove([
            'company_id' => $journal->company_id,
            'journal_id' => $journal->id,
            'partner_id' => $partnerId,
            'date'       => $date,
            'move_type'  => 'entry',
            'currency'   => $currency,
            'ref'        => $memo,
        ], $lines);

        return AccountPayment::create([
            'company_id'             => $journal->company_id,
            'journal_id'             => $journal->id,
            'move_id'                => $paymentMove->id,
            'partner_id'             => $partnerId,
            'payment_type'           => $paymentType,
            'date'                   => $date,
            'amount'                 => $amount,
            'currency'               => $currency,
            'memo'                   => $memo,
            'state'                  => 'draft',
            'payment_method'         => $data['payment_method'] ?? 'manual',
            'bank_reference'         => $data['bank_reference'] ?? null,
            'cheque_number'          => $data['cheque_number'] ?? null,
            'destination_account_id' => $data['destination_account_id'] ?? null,
        ]);
    }

    public function confirmPayment(AccountPayment $payment): AccountPayment
    {
        if (!$payment->isDraft()) {
            throw new RuntimeException('Only draft payments can be confirmed.');
        }

        $this->postMove($payment->move);

        $payment->update(['state' => 'posted']);

        $this->chatterService->log($payment, 'Payment confirmed and journal entry posted.', 'system');

        return $payment;
    }

    public function cancelPayment(AccountPayment $payment): AccountPayment
    {
        if ($payment->state === 'cancelled') {
            return $payment;
        }

        if ($payment->move && $payment->move->isPosted()) {
            $this->cancelMove($payment->move);
        }

        $payment->update(['state' => 'cancelled']);

        $this->chatterService->log($payment, 'Payment cancelled.', 'system');

        return $payment;
    }

    public function resetPaymentToDraft(AccountPayment $payment): AccountPayment
    {
        if ($payment->isDraft()) {
            return $payment;
        }

        if ($payment->move && $payment->move->state === 'posted') {
            $this->cancelMove($payment->move);
            $this->resetMoveToDraft($payment->move);
        }

        $payment->update(['state' => 'draft']);

        $this->chatterService->log($payment, 'Payment reset to draft.', 'system');

        return $payment;
    }

    public function createCreditNote(AccountMove $move): AccountMove
    {
        if (!$move->isPosted()) {
            throw new RuntimeException('Only posted documents can be credited.');
        }

        if (!in_array($move->move_type, ['out_invoice', 'in_invoice'], true)) {
            throw new RuntimeException('Only invoices and bills can create credit notes.');
        }

        $move->loadMissing(['lines.taxes']);
        $refundType = $move->move_type === 'out_invoice' ? 'out_refund' : 'in_refund';

        $header = [
            'company_id'       => $move->company_id,
            'journal_id'       => $move->journal_id,
            'partner_id'       => $move->partner_id,
            'reversed_move_id' => $move->id,
            'ref'              => 'Credit note for ' . ($move->name ?: "#{$move->id}"),
            'date'             => now()->toDateString(),
            'invoice_date'     => now()->toDateString(),
            'move_type'        => $refundType,
            'currency'         => $move->currency,
            'narration'        => $move->narration,
        ];

        $lines = $move->lines->map(fn (AccountMoveLine $line) => [
            'account_id'      => $line->account_id,
            'partner_id'      => $line->partner_id,
            'name'            => 'Credit: ' . $line->name,
            'debit'           => (float) $line->credit,
            'credit'          => (float) $line->debit,
            'currency'        => $line->currency,
            'amount_currency' => -1 * (float) $line->amount_currency,
            'sequence'        => $line->sequence,
            'tax_line_id'     => $line->tax_line_id,
            'tax_base_amount' => $line->tax_base_amount,
            'tax_ids'         => $line->taxes->pluck('id')->all(),
        ])->all();

        // O5 (Odoo parity): the credit note is created in DRAFT, NOT posted and
        // NOT auto-reconciled. Odoo's "Reverse"/"Add Credit Note" button leaves
        // the new document open so the user can amend lines (partial refund,
        // change line text) before posting. Reconciliation with the original
        // happens automatically the moment the user posts it — see `postMove`'s
        // post-commit hook (`reconcileWithReversed`).
        $creditNote = $this->createMove($header, $lines);

        $this->chatterService->log($move, "Credit note drafted (#{$creditNote->id}). Review and post to apply.", 'system');

        return $creditNote;
    }

    /**
     * Create a reversal entry: same accounts, flipped debit/credit, optional new date.
     * The original move must be posted. Odoo parity (O5): the reversal is
     * created in DRAFT for the user to review and post; previously it was
     * auto-posted, which removed the chance for a partial reversal.
     */
    public function reverseMove(AccountMove $move, ?Carbon $reversalDate = null): AccountMove
    {
        if (!$move->isPosted()) {
            throw new RuntimeException('Only posted entries can be reversed.');
        }

        $date = $reversalDate ?: Carbon::today();

        $reverseHeader = [
            'company_id'       => $move->company_id,
            'journal_id'       => $move->journal_id,
            'partner_id'       => $move->partner_id,
            'reversed_move_id' => $move->id,
            'ref'              => 'Reversal of ' . ($move->name ?: "#{$move->id}"),
            'date'             => $date->toDateString(),
            'state'            => 'draft',
            'move_type'        => 'entry',
            'currency'         => $move->currency,
            'narration'        => $move->narration,
        ];

        $reverseLines = $move->lines->map(fn (AccountMoveLine $line) => [
            'account_id'      => $line->account_id,
            'partner_id'      => $line->partner_id,
            'name'            => 'Reversal: ' . $line->name,
            'debit'           => (float) $line->credit,
            'credit'          => (float) $line->debit,
            'currency'        => $line->currency,
            'amount_currency' => -1 * (float) $line->amount_currency,
            'sequence'        => $line->sequence,
        ])->all();

        // O5 (Odoo parity): stays in DRAFT for review. Reconciliation with the
        // original move's counterpart happens at post time via the post-commit
        // hook in postMove().
        $reversal = $this->createMove($reverseHeader, $reverseLines);
        $this->chatterService->log($move, "Reversal entry drafted (#{$reversal->id}). Review and post to apply.", 'system');
        return $reversal;
    }

    public function deleteMove(AccountMove $move): void
    {
        if ($move->move_type !== 'entry' && !$move->isCancelled()) {
            throw new RuntimeException('Invoices and bills can only be deleted after they are cancelled.');
        }

        if ($move->isPosted()) {
            throw new RuntimeException('Posted entries cannot be deleted. Reverse or cancel instead.');
        }
        $this->chatterService->log($move, 'Entry deleted.', 'system');
        $move->delete();
    }

    public function documentResidual(AccountMove $move): float
    {
        if (!in_array($move->move_type, ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true)) {
            return 0.0;
        }

        // D1: sum residuals across every installment line. Single-shot invoices
        // collapse to one line, multi-installment invoices contribute one per
        // payment-term line.
        return round(
            $this->documentCounterpartLines($move)
                ->sum(fn (AccountMoveLine $line) => $this->getLineResidual($line)),
            self::SCALE
        );
    }

    public function refreshPaymentState(AccountMove $move): AccountMove
    {
        if (!in_array($move->move_type, ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true)) {
            return $move;
        }

        $total    = round((float) $move->amount_total, self::SCALE);
        $residual = $this->documentResidual($move);

        // O6 (Odoo parity): when an invoice is fully matched by a credit note
        // (a posted move that points back at it via `reversed_move_id`),
        // payment_state goes to `reversed`, not `paid`. The user-visible
        // distinction matters: a `paid` invoice means the customer actually
        // paid us; `reversed` means we cancelled the receivable with a credit.
        $fullyMatchedByReversal = $residual <= 0.005 && $this->isFullyMatchedByReversal($move);

        // `in_payment` (Odoo): an outstanding payment has been posted but the
        // bank statement hasn't reconciled it yet. We can only detect this when
        // outstanding accounts are configured on the payment journal — the
        // counterpart line would sit on `outstanding_receipts_account_id` /
        // `outstanding_payments_account_id` rather than the bank GL itself.
        $inPayment = $residual <= 0.005 && !$fullyMatchedByReversal && $this->isWaitingOnBankClearance($move);

        $state = match (true) {
            $fullyMatchedByReversal => 'reversed',
            $inPayment              => 'in_payment',
            $residual <= 0.005      => 'paid',
            $residual < $total      => 'partial',
            default                 => 'not_paid',
        };

        $move->update(['payment_state' => $state]);

        return $move->fresh();
    }

    /**
     * True if the entire residual was wiped by reconciliation with one or more
     * posted moves that name this document as their `reversed_move_id`. Used
     * to surface payment_state = 'reversed' (vs 'paid' from an actual payment).
     */
    private function isFullyMatchedByReversal(AccountMove $move): bool
    {
        if (!in_array($move->move_type, ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true)) {
            return false;
        }

        try {
            $counterpart = $this->documentCounterpartLine($move);
        } catch (\RuntimeException $e) {
            return false;
        }

        $matchedLineIds = AccountPartialReconcile::where('debit_move_line_id', $counterpart->id)
            ->orWhere('credit_move_line_id', $counterpart->id)
            ->get()
            ->flatMap(fn ($r) => [$r->debit_move_line_id, $r->credit_move_line_id])
            ->unique()
            ->filter(fn ($id) => (int) $id !== (int) $counterpart->id)
            ->values();

        if ($matchedLineIds->isEmpty()) {
            return false;
        }

        // Any matching move whose `reversed_move_id` is this document → reversal match.
        return AccountMoveLine::query()
            ->whereIn('id', $matchedLineIds)
            ->whereHas('move', fn ($q) => $q->where('reversed_move_id', $move->id))
            ->exists();
    }

    /**
     * O11/O6: true if the counterpart was reconciled against a payment that
     * landed on an outstanding receipts/payments account (i.e. payment is
     * recorded in the books but bank statement hasn't cleared it yet).
     */
    private function isWaitingOnBankClearance(AccountMove $move): bool
    {
        try {
            $counterpart = $this->documentCounterpartLine($move);
        } catch (\RuntimeException $e) {
            return false;
        }

        $matchedLineIds = AccountPartialReconcile::where('debit_move_line_id', $counterpart->id)
            ->orWhere('credit_move_line_id', $counterpart->id)
            ->get()
            ->flatMap(fn ($r) => [$r->debit_move_line_id, $r->credit_move_line_id])
            ->unique()
            ->filter(fn ($id) => (int) $id !== (int) $counterpart->id)
            ->values();

        if ($matchedLineIds->isEmpty()) {
            return false;
        }

        // The opposite-side line on each matching payment move lives on the
        // payment journal's outstanding account. If any matched line's
        // sibling sits on outstanding_receipts/payments_account, we're
        // in_payment until bank-rec clears it.
        $matchedMoves = AccountMoveLine::whereIn('id', $matchedLineIds)->pluck('move_id')->unique();
        foreach ($matchedMoves as $moveId) {
            $paymentMove = AccountMove::with(['journal', 'lines.account'])->find($moveId);
            if (!$paymentMove?->journal) continue;

            $outstandingIds = array_filter([
                $paymentMove->journal->outstanding_receipts_account_id,
                $paymentMove->journal->outstanding_payments_account_id,
            ]);
            if (empty($outstandingIds)) continue;

            $usesOutstanding = $paymentMove->lines
                ->contains(fn ($l) => in_array((int) $l->account_id, array_map('intval', $outstandingIds), true));
            if ($usesOutstanding) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calculations & validations
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Returns ['debit' => float, 'credit' => float, 'difference' => float].
     */
    public function computeMoveBalance(AccountMove $move): array
    {
        $lines = $move->lines();
        $totalDebit  = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');
        return [
            'debit'      => round($totalDebit, self::SCALE),
            'credit'     => round($totalCredit, self::SCALE),
            'difference' => round($totalDebit - $totalCredit, self::SCALE),
        ];
    }

    public function isBalanced(AccountMove $move): bool
    {
        $lines = $move->lines();
        $totalDebit  = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');

        return round($totalDebit, self::SCALE) === round($totalCredit, self::SCALE);
    }

    /**
     * Core balance rule.
     * A journal entry is valid only when total debits equal total credits
     * (compared after rounding to 2 decimal places).
     */
    public function assertBalanced(AccountMove $move): void
    {
        $lines = $move->lines();
        $totalDebit  = (float) $lines->sum('debit');
        $totalCredit = (float) $lines->sum('credit');

        if (round($totalDebit, self::SCALE) !== round($totalCredit, self::SCALE)) {
            throw new RuntimeException(sprintf(
                'Journal entry is not balanced. Debit %.2f, Credit %.2f, Difference %.2f.',
                $totalDebit,
                $totalCredit,
                $totalDebit - $totalCredit
            ));
        }

        // MC5 (Odoo parity): also enforce per-currency balance on
        // `amount_currency`. A multi-currency move that balances in base via
        // FX conversion can still be imbalanced in its foreign currency
        // (e.g. EUR 100 debit + USD 110 credit, both converting to 110 base
        // — base balances but per-currency does not). Odoo rejects this.
        $move->loadMissing('lines');
        $byCurrency = $move->lines
            ->filter(fn ($l) => $l->currency && abs((float) $l->amount_currency) > 0.005)
            ->groupBy('currency');

        $baseCurrency = Company::find($move->company_id)?->currency ?: 'IQD';

        foreach ($byCurrency as $currencyCode => $currencyLines) {
            if ($currencyCode === $baseCurrency) continue;
            $signedSum = $currencyLines->sum(function ($line) {
                // amount_currency is stored unsigned; recover the sign from
                // debit/credit direction (debit-side positive, credit-side negative).
                return (float) $line->debit > 0
                    ? +abs((float) $line->amount_currency)
                    : -abs((float) $line->amount_currency);
            });

            $rounding = $this->currencyRounding($currencyCode);
            if (abs(round($signedSum, max(2, $rounding))) > 0.005) {
                throw new RuntimeException(sprintf(
                    'Journal entry is not balanced in %s. Net %s amount_currency = %.4f (must be 0). ' .
                    'Either the FX rate is wrong for one of the %s lines or the foreign-currency amount needs adjustment.',
                    $currencyCode,
                    $currencyCode,
                    $signedSum,
                    $currencyCode
                ));
            }
        }
    }

    /**
     * MC5 helper: look up the per-currency decimal precision from the Currency
     * lookup so per-currency rounding tolerates the right number of decimals.
     * Falls back to 2 when the code isn't seeded.
     */
    private function currencyRounding(string $code): int
    {
        return \App\Models\Accounting\Currency::byCode($code)?->decimal_places ?? 2;
    }

    /**
     * Account balance = sum(debit) - sum(credit) across posted lines on/before $asOf.
     */
    public function getAccountBalance(Account $account, ?Carbon $asOf = null): float
    {
        $q = $account->moveLines()->where('state', 'posted');
        if ($asOf) {
            $q->whereDate('date', '<=', $asOf->toDateString());
        }
        $debit  = (float) $q->sum('debit');
        $credit = (float) (clone $q)->sum('credit');
        return round($debit - $credit, self::SCALE);
    }

    private function computeTotal(AccountMove $move): float
    {
        return round((float) $move->lines()->sum('debit'), self::SCALE);
    }

    /**
     * O11 (Odoo parity): pick the liquidity account for a payment leg.
     *
     * Odoo's payment flow uses TWO accounts per journal:
     *   - `outstanding_receipts_account_id` / `outstanding_payments_account_id`
     *     — temporary holding while the payment is recorded but the bank
     *     statement hasn't cleared it (invoice payment_state = `in_payment`)
     *   - `default_account_id` — the actual bank/cash GL account; the bank
     *     reconciliation flow moves outstanding → default
     *
     * When outstanding accounts aren't configured (common in single-bank
     * setups, or before any bank reconciliation infrastructure is built),
     * fall back to default_account_id so payments still post — invoices will
     * just skip the `in_payment` intermediate state and go straight to `paid`.
     */
    private function resolvePaymentLiquidityAccount(AccountJournal $journal, string $paymentType): int
    {
        $outstandingId = $paymentType === 'inbound'
            ? $journal->outstanding_receipts_account_id
            : $journal->outstanding_payments_account_id;

        if ($outstandingId) {
            return (int) $outstandingId;
        }

        if (!$journal->default_account_id) {
            throw new RuntimeException(sprintf(
                "Journal '%s' has no default liquidity account configured.",
                $journal->name
            ));
        }

        return (int) $journal->default_account_id;
    }

    private function defaultPaymentJournal(AccountMove $move): AccountJournal
    {
        $journal = AccountJournal::where('company_id', $move->company_id)
            ->whereIn('type', ['bank', 'cash'])
            ->where('active', true)
            ->whereNotNull('default_account_id')
            ->orderByRaw("case when type = 'bank' then 0 else 1 end")
            ->orderBy('code')
            ->first();

        if (!$journal) {
            throw new RuntimeException('No active bank or cash journal is configured for this company.');
        }

        return $journal;
    }

    /**
     * D1: returns the "primary" counterpart line — the highest-balance
     * receivable/payable line. For multi-installment invoices this is the
     * largest installment. Callers that need ALL installments (payment
     * matching, residual sums) should use documentCounterpartLines() instead.
     *
     * Kept for back-compat with single-line flows (credit-note auto-reconcile
     * picks ONE line to match).
     */
    private function documentCounterpartLine(AccountMove $move): AccountMoveLine
    {
        $lines = $this->documentCounterpartLines($move);
        if ($lines->isEmpty()) {
            throw new RuntimeException('The document has no receivable or payable line to reconcile.');
        }
        return $lines->sortByDesc(fn (AccountMoveLine $line) => abs($line->balance))->first();
    }

    /**
     * D1 (Odoo parity): returns ALL receivable/payable lines on a document,
     * ordered by `date_maturity` ASC (oldest installment first). Multi-
     * installment invoices have one such line per payment-term line; single-
     * shot invoices have exactly one. Order matters for "oldest first"
     * payment matching, which is Odoo's default reconciliation policy.
     *
     * @return \Illuminate\Support\Collection<int, AccountMoveLine>
     */
    private function documentCounterpartLines(AccountMove $move): \Illuminate\Support\Collection
    {
        $move->loadMissing('lines.account');

        $expectedInternalType = in_array($move->move_type, ['out_invoice', 'out_refund'], true)
            ? 'receivable'
            : 'payable';

        return $move->lines
            ->filter(fn (AccountMoveLine $line) => $line->account?->internal_type === $expectedInternalType)
            ->sortBy([
                fn (AccountMoveLine $a, AccountMoveLine $b) => ($a->date_maturity?->timestamp ?? 0) <=> ($b->date_maturity?->timestamp ?? 0),
                fn (AccountMoveLine $a, AccountMoveLine $b) => $a->sequence <=> $b->sequence,
            ])
            ->values();
    }

    private function getLineResidual(AccountMoveLine $line): float
    {
        $matched = (float) AccountPartialReconcile::where('debit_move_line_id', $line->id)
            ->orWhere('credit_move_line_id', $line->id)
            ->sum('amount');

        return round(max(0, abs($line->balance) - $matched), self::SCALE);
    }

    private function reconcileLines(AccountMoveLine $lineA, AccountMoveLine $lineB, float $amount, Carbon $date): AccountPartialReconcile
    {
        if ((int) $lineA->company_id !== (int) $lineB->company_id) {
            throw new RuntimeException('Cannot reconcile lines from different companies.');
        }

        if ((int) $lineA->account_id !== (int) $lineB->account_id) {
            throw new RuntimeException('Cannot reconcile lines from different accounts.');
        }

        // O3 (Odoo parity): the account itself must be flagged as reconcilable.
        // Odoo blocks reconciliation on non-`reconcile` accounts because the
        // residual on e.g. an income account is not a true outstanding balance
        // — matching two unrelated entries there silently corrupts P&L drilldowns.
        $lineA->loadMissing('account');
        if (!$lineA->account?->reconcile) {
            throw new RuntimeException(sprintf(
                'Account %s is not flagged as reconcilable. Only receivable, payable, and liquidity accounts can be reconciled.',
                $lineA->account?->display_name ?? "#{$lineA->account_id}"
            ));
        }

        if (abs($lineA->balance) < 0.005 || abs($lineB->balance) < 0.005 || ($lineA->balance > 0) === ($lineB->balance > 0)) {
            throw new RuntimeException('Reconciliation requires one debit line and one credit line.');
        }

        $debitLine = $lineA->balance > 0 ? $lineA : $lineB;
        $creditLine = $lineA->balance < 0 ? $lineA : $lineB;

        // MC4 (Odoo parity): record the per-side foreign-currency amount
        // consumed by THIS partial reconcile, independent of `amount` (base).
        // Computed proportionally from each line's `amount_currency` vs its
        // base balance — so a 105-base reconcile against a 110-base / 100-EUR
        // AR line consumes (105/110)*100 = 95.4545 EUR of the foreign side.
        // The remaining 4.5455 EUR foreign residual is what drives FX-drift
        // detection when the OTHER side's foreign happens to close exactly.
        $debitForeign  = $this->computeForeignPortion($debitLine, $amount);
        $creditForeign = $this->computeForeignPortion($creditLine, $amount);

        $reconcile = AccountPartialReconcile::create([
            'company_id'             => $lineA->company_id,
            'debit_move_line_id'     => $debitLine->id,
            'credit_move_line_id'    => $creditLine->id,
            'amount'                 => round($amount, self::SCALE),
            'debit_amount_currency'  => $debitForeign,
            'credit_amount_currency' => $creditForeign,
            'date'                   => $date->toDateString(),
        ]);

        // MC4 (Odoo parity): cross-currency reconcile may close the
        // foreign-currency residual on both sides while leaving a small
        // base-currency residual (FX rate moved between invoice and payment).
        // Post a small adjustment move to the company's FX gain/loss accounts
        // so the AR/AP closes cleanly in base too.
        $this->maybePostFxAdjustment($debitLine, $creditLine, $date);

        return $reconcile;
    }

    /**
     * MC4 (Odoo parity): detect cross-currency reconciliation that left a
     * base-currency drift and post an adjustment to the company's FX gain or
     * loss account.
     *
     * Triggers when:
     *   - both lines have a non-base `currency` set (or differ from each
     *     other), AND
     *   - one line's `amount_currency` residual is now fully consumed
     *     (foreign side closed), AND
     *   - the OTHER line still has a base-currency residual remaining
     *
     * The adjustment is a one-line debit + one-line credit move:
     *   - residual side line on the AR/AP account (closes the residual)
     *   - offsetting line on income_currency_exchange_account_id (gain) or
     *     expense_currency_exchange_account_id (loss)
     *
     * Skips silently when both lines share the same currency (classical
     * same-currency reconcile produces no FX drift). Throws if cross-currency
     * but the company hasn't configured FX accounts.
     */
    private function maybePostFxAdjustment(AccountMoveLine $debitLine, AccountMoveLine $creditLine, Carbon $date): void
    {
        $debitLine->loadMissing(['account', 'move']);
        $creditLine->loadMissing(['account', 'move']);

        $company = Company::find($debitLine->company_id);
        if (!$company) return;
        $baseCurrency = $company->currency ?: 'IQD';

        $debitCurrency  = $debitLine->currency  ?: $baseCurrency;
        $creditCurrency = $creditLine->currency ?: $baseCurrency;

        // Same-currency reconcile → nothing to do.
        if ($debitCurrency === $creditCurrency && $debitCurrency === $baseCurrency) {
            return;
        }

        $debitResidualBase  = $this->getLineResidual($debitLine);
        $creditResidualBase = $this->getLineResidual($creditLine);

        // If either side still has base residual, there's no drift to write
        // off yet — more reconciliation activity will keep matching against
        // these lines. We only post adjustment when one side has closed
        // completely on its native currency but a small base gap remains.
        $debitForeignResidual  = $this->getLineForeignResidual($debitLine);
        $creditForeignResidual = $this->getLineForeignResidual($creditLine);

        // Pick the side that still has base drift (the "open" side). The
        // adjustment will close ITS residual. Three trigger cases:
        //   1. Debit foreign closed, debit base still open → drift on debit
        //   2. Credit foreign closed, credit base still open → drift on credit
        //   3. One side fully closed in BOTH currencies AND the other still
        //      has both — the other's leftover is proportional FX drift
        //      (typical scenario: invoice EUR 100 @ 1.10 = 110 base, payment
        //      EUR 100 @ 1.05 = 105 base. After matching 105 base, payment
        //      closes fully in both; AR has 4.55 EUR / 5 base residual — all FX.)
        $openSide = null;
        if ($debitForeignResidual <= 0.005 && $debitResidualBase > 0.005) {
            $openSide = $debitLine;
        } elseif ($creditForeignResidual <= 0.005 && $creditResidualBase > 0.005) {
            $openSide = $creditLine;
        } elseif ($debitResidualBase <= 0.005 && $debitForeignResidual <= 0.005
            && $creditResidualBase > 0.005 && $creditForeignResidual > 0.005
        ) {
            // Debit closed in both; credit's leftover is FX drift only.
            $openSide = $creditLine;
        } elseif ($creditResidualBase <= 0.005 && $creditForeignResidual <= 0.005
            && $debitResidualBase > 0.005 && $debitForeignResidual > 0.005
        ) {
            // Credit closed in both; debit's leftover is FX drift only.
            $openSide = $debitLine;
        }

        if (!$openSide) {
            return;
        }

        $drift = round($this->getLineResidual($openSide), self::SCALE);
        if ($drift <= 0.005) return;

        // openSide is the AR/AP line whose base residual remains. We post a
        // tiny adjustment move on the SAME control account to close it, with
        // the offsetting line on the company's FX gain or loss account.
        //
        // Gain vs loss heuristic:
        //   - AR (out_invoice) with debit-side residual = we recorded MORE
        //     base than we collected → LOSS (FX rate dropped between
        //     invoice and payment).
        //   - AR with credit-side residual = we collected MORE than recorded
        //     → GAIN (FX rate rose).
        //   - AP (in_invoice) flips the direction.
        $openIsDebit  = (float) $openSide->balance > 0;
        $isReceivable = in_array($openSide->move?->move_type, ['out_invoice', 'out_refund'], true);

        // Open-debit + AR  → loss  (sales rate dropped → AR worth less base)
        // Open-credit + AR → gain  (sales rate rose → collected more base than booked)
        // Open-debit + AP  → gain  (purchases rate dropped → owe less base than booked)
        // Open-credit + AP → loss  (purchases rate rose → paid more base than booked)
        $isGain = $isReceivable ? !$openIsDebit : $openIsDebit;

        $fxAccountId = $isGain
            ? $company->income_currency_exchange_account_id
            : $company->expense_currency_exchange_account_id;

        if (!$fxAccountId) {
            throw new RuntimeException(sprintf(
                "Cross-currency reconciliation left a %.2f %s residual but company '%s' has no %s account configured. " .
                "Set the FX gain/loss accounts under Accounting > Settings.",
                $drift,
                $baseCurrency,
                $company->name,
                $isGain ? 'income (gain)' : 'expense (loss)'
            ));
        }

        // The openSide has leftover base residual. If it's on the debit side,
        // we credit the same account to close it (and debit FX loss/gain). If
        // on the credit side, mirror it.
        $adjustmentLines = $openIsDebit
            ? [
                ['account_id' => $fxAccountId,          'partner_id' => $openSide->partner_id, 'name' => 'FX adjustment', 'debit' => $drift, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $openSide->account_id, 'partner_id' => $openSide->partner_id, 'name' => 'FX adjustment', 'debit' => 0, 'credit' => $drift, 'sequence' => 20],
            ]
            : [
                ['account_id' => $openSide->account_id, 'partner_id' => $openSide->partner_id, 'name' => 'FX adjustment', 'debit' => $drift, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $fxAccountId,          'partner_id' => $openSide->partner_id, 'name' => 'FX adjustment', 'debit' => 0, 'credit' => $drift, 'sequence' => 20],
            ];

        $adjustmentMove = $this->createMove([
            'company_id' => $openSide->company_id,
            'journal_id' => $openSide->journal_id,
            'partner_id' => $openSide->partner_id,
            'date'       => $date->toDateString(),
            'move_type'  => 'entry',
            'ref'        => 'FX adjustment on ' . ($openSide->move?->name ?: "#{$openSide->move_id}"),
        ], $adjustmentLines);

        $adjustmentMove = $this->postMove($adjustmentMove);

        // Reconcile the adjustment's AR/AP line against the openSide's
        // residual so it reaches zero in base.
        $adjustmentControlLine = $adjustmentMove->lines()
            ->where('account_id', $openSide->account_id)
            ->first();

        if ($adjustmentControlLine) {
            AccountPartialReconcile::create([
                'company_id' => $openSide->company_id,
                'debit_move_line_id'  => $openIsDebit ? $openSide->id : $adjustmentControlLine->id,
                'credit_move_line_id' => $openIsDebit ? $adjustmentControlLine->id : $openSide->id,
                'amount' => $drift,
                'date'   => $date->toDateString(),
            ]);
        }
    }

    /**
     * MC4 helper: how much of this line's foreign-currency `amount_currency`
     * is still unmatched, computed from the partial_reconcile rows' per-side
     * foreign columns (debit_amount_currency / credit_amount_currency).
     *
     * For each reconcile this line is the debit side of, subtract
     * debit_amount_currency; for credit-side reconciles, subtract
     * credit_amount_currency. Legacy rows where the foreign columns are NULL
     * fall back to the base `amount` (correct for same-currency reconciles
     * where foreign equals base anyway).
     */
    private function getLineForeignResidual(AccountMoveLine $line): float
    {
        $amountCurrency = abs((float) $line->amount_currency);
        if ($amountCurrency <= 0.005) {
            return 0.0;
        }

        $debitMatched  = (float) AccountPartialReconcile::where('debit_move_line_id', $line->id)
            ->sum(DB::raw('COALESCE(debit_amount_currency, amount)'));
        $creditMatched = (float) AccountPartialReconcile::where('credit_move_line_id', $line->id)
            ->sum(DB::raw('COALESCE(credit_amount_currency, amount)'));

        $foreignMatched = $debitMatched + $creditMatched;

        return round(max(0, $amountCurrency - $foreignMatched), self::SCALE);
    }

    /**
     * MC4 helper: for a partial reconcile of $baseAmount against $line, how
     * much of the line's foreign currency is consumed. We use the per-line
     * proportion `amount_currency / abs(balance)` so the foreign math scales
     * with the base math at the LINE's recorded rate, not at the reconcile
     * date. (Odoo does the same — the foreign-currency portion attributed to
     * a reconcile entry reflects the source line's booked FX rate.)
     */
    private function computeForeignPortion(AccountMoveLine $line, float $baseAmount): ?float
    {
        $amountCurrency = abs((float) $line->amount_currency);
        $baseTotal      = abs((float) $line->balance);

        if ($amountCurrency <= 0.005 || $baseTotal <= 0.005) {
            // Line has no foreign-currency tracking — store NULL so legacy
            // queries treating it as "same-as-base" still work.
            return null;
        }

        return round($baseAmount * ($amountCurrency / $baseTotal), 4);
    }

    private function assertLinesValid(AccountMove $move): void
    {
        $move->loadMissing('lines.account');

        foreach ($move->lines as $line) {
            if ((float) $line->debit < 0 || (float) $line->credit < 0) {
                throw new RuntimeException("Line '{$line->name}' has a negative debit or credit.");
            }
            if ((float) $line->debit > 0 && (float) $line->credit > 0) {
                throw new RuntimeException("Line '{$line->name}' cannot have both debit and credit.");
            }
            if ((float) $line->debit === 0.0 && (float) $line->credit === 0.0) {
                throw new RuntimeException("Line '{$line->name}' has no amount.");
            }
            if ($line->account && (int) $line->account->company_id !== (int) $move->company_id) {
                throw new RuntimeException("Line '{$line->name}' uses an account from a different company than the entry.");
            }

            // O2 (Odoo parity): receivable / payable lines REQUIRE a partner_id.
            // Without it, AR/AP aging reports cannot bucket the balance by partner
            // and the residual stays orphaned in the chart of accounts. Odoo
            // enforces this at the model layer (`_check_partner_id_required`).
            if ($line->account
                && in_array($line->account->internal_type, ['receivable', 'payable'], true)
                && !$line->partner_id
            ) {
                throw new RuntimeException(sprintf(
                    "Line '%s' posts to a %s account (%s) and requires a partner.",
                    $line->name,
                    $line->account->internal_type,
                    $line->account->display_name ?? $line->account->code
                ));
            }

            // MC6 (Odoo parity): if the account is pinned to a single currency
            // (e.g. a USD-only bank GL), every line on it must use that
            // currency. Posting a EUR line to a USD-pinned account would
            // silently mix bases inside the same account.
            if ($line->account?->currency
                && $line->currency
                && $line->account->currency !== $line->currency
            ) {
                throw new RuntimeException(sprintf(
                    "Line '%s' uses %s but its account (%s) is pinned to %s.",
                    $line->name,
                    $line->currency,
                    $line->account->display_name ?? $line->account->code,
                    $line->account->currency
                ));
            }
        }
    }

    private function assertSameCompany(int $a, int $b, string $label): void
    {
        if ($a !== $b) {
            throw new RuntimeException("Selected {$label} belongs to a different company.");
        }
    }

    /**
     * Odoo parity (O1): documents have two dates — `invoice_date` (commercial,
     * shown on the customer PDF) and `date` (accounting/posting). Default
     * `invoice_date := date` when the user didn't supply one, then anchor
     * payment-term due-date math on `invoice_date` (matching Odoo: the
     * customer sees their statement counted from the invoice date, not the
     * accounting period close).
     */
    private function resolveInvoiceDueDate(array $data): array
    {
        // Default invoice_date := date when the form didn't carry one (back-compat
        // for the legacy single-date flow).
        if (empty($data['invoice_date'])) {
            $data['invoice_date'] = $data['date'] ?? null;
        }

        $anchorDate = $data['invoice_date'] ?? $data['date'] ?? null;

        if (!empty($data['payment_term_id'])) {
            $term = \App\Models\Accounting\AccountingPaymentTerm::with('lines')->find($data['payment_term_id']);
            if ($term && $anchorDate) {
                $balanceLine = $term->lines->firstWhere('value_type', 'balance') ?? $term->lines->sortByDesc('days')->first();
                $days = $balanceLine ? (int) $balanceLine->days : 0;
                $data['invoice_date_due'] = Carbon::parse($anchorDate)->copy()->addDays($days)->toDateString();
            }
        } elseif (empty($data['invoice_date_due'])) {
            $data['invoice_date_due'] = $anchorDate;
        }

        return $data;
    }

    private function buildDocumentLines(array $data, array $items): array
    {
        $moveType = $data['move_type'] ?? 'entry';
        $supported = ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'];
        if (!in_array($moveType, $supported, true)) {
            throw new RuntimeException('Unsupported accounting document type.');
        }

        $partnerId        = $data['partner_id'] ?? null;
        $controlAccountId = $data['control_account_id'] ?? null;
        $companyId        = (int) ($data['company_id'] ?? 0);
        $currency         = $data['currency'] ?? null;

        if (!$controlAccountId) {
            throw new RuntimeException('A receivable or payable account is required.');
        }

        // Debit/credit direction per type:
        // out_invoice: lines → credit, control → debit
        // in_invoice:  lines → debit,  control → credit
        // out_refund:  lines → debit,  control → credit  (reverse of out_invoice)
        // in_refund:   lines → credit, control → debit   (reverse of in_invoice)
        $lineOnCredit   = in_array($moveType, ['out_invoice', 'in_refund'], true);
        $controlOnDebit = in_array($moveType, ['out_invoice', 'in_refund'], true);

        // Tax scope: sale for customer-side, purchase for vendor-side
        $taxScope = in_array($moveType, ['out_invoice', 'out_refund'], true) ? 'sale' : 'purchase';

        $lines     = [];
        $subtotal  = 0.0;
        $taxTotals = [];
        $sequence  = 10;

        foreach ($items as $item) {
            $quantity  = round((float) ($item['quantity']   ?? 0), self::SCALE);
            $priceUnit = round((float) ($item['price_unit'] ?? 0), self::SCALE);
            $discount  = min(100.0, max(0.0, (float) ($item['discount'] ?? 0)));
            $taxIds    = array_filter((array) ($item['tax_ids'] ?? []));
            $productId = $item['product_id'] ?? null;
            $uomId     = $item['uom_id']     ?? null;

            $taxes = $taxIds
                ? AccountTax::whereIn('id', $taxIds)
                    ->where('company_id', $companyId)
                    ->whereIn('type_tax_use', [$taxScope, 'none'])
                    ->where('active', true)
                    ->get()
                : collect();

            $grossAmount = round($quantity * $priceUnit * (1 - $discount / 100), self::SCALE);
            if ($grossAmount <= 0) {
                continue;
            }

            // O10 (Odoo parity): the unwrap from "gross price → net base" is
            // driven by `price_include`, not `include_base_amount`. The latter
            // is the cascading flag used further down when computing each tax's
            // base.
            $netAmount = $grossAmount;
            foreach ($taxes as $tax) {
                if ($tax->price_include) {
                    $netAmount = $tax->extractBase($netAmount);
                }
            }
            $netAmount = round($netAmount, self::SCALE);

            $subtotal = round($subtotal + $netAmount, self::SCALE);

            $label = (string) ($item['name'] ?? '');
            if ($label === '' && $productId) {
                $product = \App\Models\Inventory\Product::find($productId);
                $label = $product?->name ?? '';
            }

            $lines[] = [
                'account_id'      => $item['account_id'],
                'partner_id'      => $partnerId,
                'product_id'      => $productId ?: null,
                'uom_id'          => $uomId ?: null,
                'name'            => $label,
                'debit'           => $lineOnCredit ? 0 : $netAmount,
                'credit'          => $lineOnCredit ? $netAmount : 0,
                'currency'        => $currency,
                'amount_currency' => $netAmount,
                'discount'        => $discount,
                'sequence'        => $sequence,
                'tax_ids'         => $taxes->pluck('id')->all(),
                'tax_base_amount' => $netAmount,
            ];
            $sequence += 10;

            // O10 (Odoo parity): compute each tax against a running base.
            //   - price-inclusive taxes use the gross to extract the embedded tax
            //   - other taxes use the cumulative base; if a previous tax had
            //     `include_base_amount = true`, its computed amount is added to
            //     the base before the next tax is computed (cascading: VAT on
            //     top of an environmental fee, QST on top of GST, etc.)
            // `tax_base_amount` recorded on each tax line is the actual base
            // that tax used, not the line's net — matters for tax reports.
            $cumulativeBase = $netAmount;
            foreach ($taxes as $tax) {
                $baseForCompute = $tax->price_include ? $grossAmount : $cumulativeBase;
                $taxAmount = $tax->computeAmount($baseForCompute);

                if ($tax->include_base_amount && !$tax->price_include) {
                    $cumulativeBase = round($cumulativeBase + $taxAmount, self::SCALE);
                }

                if ($taxAmount <= 0) {
                    continue;
                }
                $taxAccountId = $tax->account_id;
                if (!$taxAccountId) {
                    continue;
                }
                $key = $taxAccountId . ':' . $tax->id;
                if (!isset($taxTotals[$key])) {
                    $taxTotals[$key] = [
                        'account_id'      => $taxAccountId,
                        'partner_id'      => $partnerId,
                        'name'            => $tax->name,
                        'tax_line_id'     => $tax->id,
                        'tax_base_amount' => 0.0,
                        'amount'          => 0.0,
                        'currency'        => $currency,
                    ];
                }
                $taxTotals[$key]['amount']          = round($taxTotals[$key]['amount']          + $taxAmount, self::SCALE);
                $taxTotals[$key]['tax_base_amount'] = round($taxTotals[$key]['tax_base_amount'] + $baseForCompute, self::SCALE);
            }
        }

        if ($subtotal <= 0) {
            throw new RuntimeException('Invoice total must be greater than zero.');
        }

        $totalTaxes = 0.0;
        foreach ($taxTotals as $taxLine) {
            $amount = $taxLine['amount'];
            $totalTaxes = round($totalTaxes + $amount, self::SCALE);
            $lines[] = [
                'account_id'      => $taxLine['account_id'],
                'partner_id'      => $taxLine['partner_id'],
                'name'            => $taxLine['name'],
                'debit'           => $lineOnCredit ? 0 : $amount,
                'credit'          => $lineOnCredit ? $amount : 0,
                'currency'        => $taxLine['currency'],
                'amount_currency' => $amount,
                'sequence'        => $sequence,
                'tax_line_id'     => $taxLine['tax_line_id'],
                'tax_base_amount' => $taxLine['tax_base_amount'],
            ];
            $sequence += 10;
        }

        $grandTotal = round($subtotal + $totalTaxes, self::SCALE);

        $controlLabels = [
            'out_invoice' => 'Customer balance',
            'in_invoice'  => 'Vendor balance',
            'out_refund'  => 'Customer credit',
            'in_refund'   => 'Vendor refund',
        ];

        // D1 (Odoo parity): when a payment term has multiple installment lines
        // (e.g. "30% in 0 days, balance in 30 days"), split the AR/AP control
        // into ONE LINE PER INSTALLMENT, each with its own `date_maturity`.
        // AR aging then buckets per installment; reconciliation can match
        // payments against specific installments oldest-first.
        //
        // Falls back to a single control line when no term is set (single-shot
        // invoice) or when the term has no schedule (immediate-payment term).
        $installments = $this->splitGrandTotalAcrossPaymentTerm(
            $grandTotal,
            $data['payment_term_id'] ?? null,
            $data['invoice_date'] ?? $data['date'] ?? null,
            $data['invoice_date_due'] ?? null
        );

        foreach ($installments as $i => $installment) {
            $label = count($installments) > 1
                ? sprintf('%s (%d/%d)', $controlLabels[$moveType], $i + 1, count($installments))
                : $controlLabels[$moveType];

            $lines[] = [
                'account_id'      => $controlAccountId,
                'partner_id'      => $partnerId,
                'name'            => $label,
                'debit'           => $controlOnDebit ? $installment['amount'] : 0,
                'credit'          => $controlOnDebit ? 0 : $installment['amount'],
                'currency'        => $currency,
                'amount_currency' => $installment['amount'],
                'sequence'        => 9000 + $i,
                'date_maturity'   => $installment['date_maturity'],
            ];
        }

        return $lines;
    }

    /**
     * D1 (Odoo parity): turn a grand total + payment term into a list of
     * installment amounts and due dates. Mirrors Odoo's
     * `account.payment.term._compute_terms()` output. Each installment becomes
     * its own AR/AP control line on the invoice/bill.
     *
     * When no payment term is set, returns a single installment for the full
     * total with `date_maturity` = $explicitDueDate (legacy single-shot flow).
     *
     * @return array<int, array{amount: float, date_maturity: ?string}>
     */
    private function splitGrandTotalAcrossPaymentTerm(
        float $grandTotal,
        ?int $paymentTermId,
        ?string $anchorDate,
        ?string $explicitDueDate
    ): array {
        if (!$paymentTermId) {
            return [['amount' => round($grandTotal, self::SCALE), 'date_maturity' => $explicitDueDate ?: $anchorDate]];
        }

        $term = \App\Models\Accounting\AccountingPaymentTerm::with('lines')->find($paymentTermId);
        $termLines = $term?->lines ?? collect();
        if ($termLines->isEmpty()) {
            return [['amount' => round($grandTotal, self::SCALE), 'date_maturity' => $explicitDueDate ?: $anchorDate]];
        }

        $anchor = $anchorDate ? Carbon::parse($anchorDate) : Carbon::today();

        // Walk non-balance lines first to compute their amounts, leave the
        // residual for the single balance line. Order matters for stable
        // rounding distribution.
        $installments = [];
        $allocated    = 0.0;
        $balanceLine  = $termLines->firstWhere('value_type', 'balance');

        foreach ($termLines as $tl) {
            if ($tl->value_type === 'balance') {
                continue; // handled at the end
            }
            $amount = $tl->value_type === 'percent'
                ? round($grandTotal * ((float) $tl->value) / 100, self::SCALE)
                : round((float) $tl->value, self::SCALE);

            // Cap so cumulative non-balance allocation never exceeds the total
            // (defensive — payment-term validation already enforces sum<=100%).
            if ($allocated + $amount > $grandTotal) {
                $amount = round($grandTotal - $allocated, self::SCALE);
            }
            if ($amount <= 0) continue;

            $installments[] = [
                'amount'        => $amount,
                'date_maturity' => $anchor->copy()->addDays((int) $tl->days)->toDateString(),
                'sequence'      => (int) $tl->sequence,
            ];
            $allocated = round($allocated + $amount, self::SCALE);
        }

        // The balance line absorbs whatever's left after rounding so the sum
        // of installments matches the grand total exactly. If no balance line
        // exists (term is malformed but slipped past validation), append a
        // synthetic balance with date_maturity = explicit due date.
        $residual = round($grandTotal - $allocated, self::SCALE);
        if ($residual > 0.005) {
            $days = $balanceLine ? (int) $balanceLine->days : 0;
            $installments[] = [
                'amount'        => $residual,
                'date_maturity' => $anchor->copy()->addDays($days)->toDateString(),
                'sequence'      => $balanceLine ? (int) $balanceLine->sequence : 9999,
            ];
        }

        // Sort installments by their term-line sequence so the AR appears in
        // the order the user defined the schedule.
        usort($installments, fn ($a, $b) => $a['sequence'] <=> $b['sequence']);

        return array_map(fn ($i) => ['amount' => $i['amount'], 'date_maturity' => $i['date_maturity']], $installments);
    }

    /**
     * Wipe existing lines and recreate from input.
     * Each line copies the parent move's company_id, journal_id, date, and state.
     * Handles FX conversion when the line currency differs from the company base currency.
     */
    private function syncLines(AccountMove $move, array $lines): void
    {
        $move->lines()->delete();

        $company         = Company::find($move->company_id);
        $baseCurrency    = $company?->currency ?: 'IQD';
        $moveDate        = Carbon::parse($move->date);

        $sequence = 10;
        foreach ($lines as $line) {
            $lineCurrency = $line['currency'] ?? $move->currency;
            $debit        = round((float) ($line['debit']  ?? 0), self::SCALE);
            $credit       = round((float) ($line['credit'] ?? 0), self::SCALE);
            $amountCurrency = round((float) ($line['amount_currency'] ?? 0), self::SCALE);

            // Skip totally blank rows to allow the UI to send an extra empty row.
            if ($debit === 0.0 && $credit === 0.0 && empty($line['account_id']) && empty($line['name'])) {
                continue;
            }

            // FX conversion: if line currency differs from company base and debit/credit are zero,
            // compute base-currency amounts from amount_currency using the exchange rate.
            if (
                $lineCurrency && $lineCurrency !== $baseCurrency
                && $debit === 0.0 && $credit === 0.0
                && $amountCurrency !== 0.0
            ) {
                $rate = $this->getExchangeRate((int) $move->company_id, $lineCurrency, $moveDate);
                $converted = round(abs($amountCurrency) * $rate, self::SCALE);
                if ($amountCurrency > 0) {
                    $debit  = $converted;
                } else {
                    $credit = $converted;
                }
            }

            $taxIds         = $line['tax_ids']         ?? [];
            $taxLineId      = $line['tax_line_id']     ?? null;
            $taxBaseAmount  = isset($line['tax_base_amount']) ? round((float) $line['tax_base_amount'], self::SCALE) : null;

            $moveLine = $move->lines()->create([
                'company_id'      => $move->company_id,
                'journal_id'      => $move->journal_id,
                'account_id'      => $line['account_id'],
                'partner_id'      => $line['partner_id'] ?? $move->partner_id,
                'product_id'      => $line['product_id'] ?? null,
                'uom_id'          => $line['uom_id'] ?? null,
                'tax_line_id'     => $taxLineId ?: null,
                'tax_base_amount' => $taxBaseAmount,
                'name'            => $line['name'] ?? '',
                'date'            => $move->date,
                // D1: per-installment due date for AR/AP control lines. null
                // for non-installment lines (income, expense, tax).
                'date_maturity'   => $line['date_maturity'] ?? null,
                'state'           => $move->state,
                'debit'           => $debit,
                'credit'          => $credit,
                'currency'        => $lineCurrency,
                'amount_currency' => $amountCurrency,
                'sequence'        => $line['sequence'] ?? $sequence,
            ]);

            if (!empty($taxIds)) {
                $moveLine->taxes()->sync($taxIds);
            }

            $sequence += 10;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Chatter change detection
    // ─────────────────────────────────────────────────────────────────────────

    private function detectChanges(object $model, array $data): array
    {
        $changes = [];
        foreach ($model->chatterTracked ?? [] as $field => $label) {
            if (!array_key_exists($field, $data)) continue;

            $old = (string) ($model->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;

            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveChangeValue($field, $model->{$field}),
                'to'    => $this->resolveChangeValue($field, $data[$field]),
            ];
        }
        return $changes;
    }

    private function resolveChangeValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';

        return match ($field) {
            'company_id'           => Company::find($value)?->name ?? "#{$value}",
            'journal_id'           => AccountJournal::find($value)?->name ?? "#{$value}",
            'parent_id'            => Account::find($value)?->display_name ?? "#{$value}",
            'default_account_id',
            'suspense_account_id'  => Account::find($value)?->display_name ?? "#{$value}",
            'partner_id'           => \App\Models\Contacts\Contact::find($value)?->name ?? "#{$value}",
            'account_type'         => Account::TYPES[$value] ?? (string) $value,
            'type'                 => AccountJournal::TYPES[$value] ?? (string) $value,
            'state'                => AccountMove::STATES[$value] ?? (string) $value,
            'reconcile'            => $value ? 'Yes' : 'No',
            default                => (string) $value,
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Lock date enforcement
    // ─────────────────────────────────────────────────────────────────────────

    private function assertDateNotLocked(AccountMove $move): void
    {
        $company = Company::find($move->company_id);
        if (!$company) {
            return;
        }

        $date = Carbon::parse($move->date);

        if ($company->accounting_fiscal_year_lock_date) {
            if ($date->lte(Carbon::parse($company->accounting_fiscal_year_lock_date))) {
                throw new RuntimeException(sprintf(
                    'The entry date %s falls within a locked fiscal year (locked through %s). No changes are allowed in this period.',
                    $date->format('Y-m-d'),
                    $company->accounting_fiscal_year_lock_date->format('Y-m-d')
                ));
            }
        }

        if ($company->accounting_period_lock_date) {
            if ($date->lte(Carbon::parse($company->accounting_period_lock_date))) {
                $authUser = Auth::user();
                $canBypass = $authUser instanceof \App\Models\User && $authUser->hasPermission('accounting.lock');
                if (!$canBypass) {
                    throw new RuntimeException(sprintf(
                        'The entry date %s falls within a locked period (locked through %s). Only users with lock-bypass permission can post in this period.',
                        $date->format('Y-m-d'),
                        $company->accounting_period_lock_date->format('Y-m-d')
                    ));
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Multi-currency
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Return the exchange rate: units of company base currency per 1 unit of $currency.
     * Looks for the most recent active rate on or before $date.
     * Returns 1.0 if no rate is found (treats as same-currency).
     */
    public function getExchangeRate(int $companyId, string $currency, Carbon $date): float
    {
        $company      = Company::find($companyId);
        $baseCurrency = $company?->currency ?: 'IQD';

        if ($currency === $baseCurrency) {
            return 1.0;
        }

        $rate = CurrencyRate::where('company_id', $companyId)
            ->where('currency', $currency)
            ->where('active', true)
            ->whereDate('date', '<=', $date->toDateString())
            ->orderByDesc('date')
            ->value('rate');

        return $rate ? (float) $rate : 1.0;
    }
}
