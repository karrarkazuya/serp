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

        $next    = (int) $locked->sequence_next_number;
        $padding = max(1, (int) $locked->sequence_padding);
        $year    = $date->format('Y');
        $prefix  = (string) $locked->sequence_prefix;

        $padded = str_pad((string) $next, $padding, '0', STR_PAD_LEFT);
        $name   = "{$prefix}{$year}/{$padded}";

        $locked->update(['sequence_next_number' => $next + 1]);

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

        $data['state']        = 'draft';
        $data['payment_state'] = 'not_paid';
        $data['move_type']    = $data['move_type'] ?? 'entry';
        $data['currency']     = $data['currency'] ?? $journal->currency;
        $data['amount_total'] = 0;
        $data['name']         = null;

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

        $name = $move->name ?: $this->reserveSequenceForJournal($move->journal, Carbon::parse($move->date));

        $move->update([
            'name'         => $name,
            'state'        => 'posted',
            'posted_at'    => now(),
            'posted_by'    => Auth::id(),
            'amount_total' => $this->computeTotal($move),
        ]);

        $move->lines()->update(['state' => 'posted']);

        $this->chatterService->log($move, "Entry posted as {$name}.", 'system');
        return $move->fresh();
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

        $lineIds = $move->lines()->pluck('id');
        AccountPartialReconcile::where(function ($q) use ($lineIds) {
            $q->whereIn('debit_move_line_id', $lineIds)
              ->orWhereIn('credit_move_line_id', $lineIds);
        })->delete();

        $move->update(['state' => 'cancelled', 'payment_state' => 'not_paid']);
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
        $counterpartLine = $this->documentCounterpartLine($move);
        $residual = $this->getLineResidual($counterpartLine);

        if ($residual <= 0) {
            throw new RuntimeException('This document is already fully paid.');
        }

        $amount = round((float) ($data['amount'] ?? $residual), self::SCALE);
        if ($amount <= 0) {
            throw new RuntimeException('Payment amount must be greater than zero.');
        }
        if ($amount - $residual > 0.005) {
            throw new RuntimeException('Payment amount cannot exceed the open amount.');
        }

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

        $lines = $paymentType === 'inbound'
            ? [
                ['account_id' => $journal->default_account_id, 'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => $amount, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $counterpartLine->account_id, 'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => 0, 'credit' => $amount, 'sequence' => 20],
            ]
            : [
                ['account_id' => $counterpartLine->account_id, 'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => $amount, 'credit' => 0, 'sequence' => 10],
                ['account_id' => $journal->default_account_id, 'partner_id' => $move->partner_id, 'name' => $memo, 'debit' => 0, 'credit' => $amount, 'sequence' => 20],
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
            ->where('account_id', $counterpartLine->account_id)
            ->where('partner_id', $move->partner_id)
            ->firstOrFail();

        $this->reconcileLines($counterpartLine, $paymentLine, $amount, $date);
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
        ]);

        $this->chatterService->log($move, sprintf('Payment registered: %.2f %s.', $amount, $move->currency ?: ''), 'system');

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

        $move->loadMissing('lines');
        $refundType = $move->move_type === 'out_invoice' ? 'out_refund' : 'in_refund';

        $header = [
            'company_id' => $move->company_id,
            'journal_id' => $move->journal_id,
            'partner_id' => $move->partner_id,
            'reversed_move_id' => $move->id,
            'ref' => 'Credit note for ' . ($move->name ?: "#{$move->id}"),
            'date' => now()->toDateString(),
            'move_type' => $refundType,
            'currency' => $move->currency,
            'narration' => $move->narration,
        ];

        $lines = $move->lines->map(fn (AccountMoveLine $line) => [
            'account_id' => $line->account_id,
            'partner_id' => $line->partner_id,
            'name' => 'Credit: ' . $line->name,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'currency' => $line->currency,
            'amount_currency' => -1 * (float) $line->amount_currency,
            'sequence' => $line->sequence,
        ])->all();

        $creditNote = $this->createMove($header, $lines);
        $creditNote = $this->postMove($creditNote);

        $originalCounterpart = $this->documentCounterpartLine($move);
        $creditCounterpart = $this->documentCounterpartLine($creditNote);
        $amountToReconcile = min($this->getLineResidual($originalCounterpart), $this->getLineResidual($creditCounterpart));

        if ($amountToReconcile > 0.005) {
            $this->reconcileLines($originalCounterpart, $creditCounterpart, $amountToReconcile, Carbon::parse($creditNote->date));
            $this->refreshPaymentState($move);
            $creditNote = $this->refreshPaymentState($creditNote);
        }

        $this->chatterService->log($move, "Credit note created (#{$creditNote->id}).", 'system');

        return $creditNote;
    }

    /**
     * Create a reversal entry: same accounts, flipped debit/credit, optional new date.
     * The original move must be posted. The reversal is itself created in draft.
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

        $reversal = $this->createMove($reverseHeader, $reverseLines);
        $this->chatterService->log($move, "Reversal entry created (#{$reversal->id}).", 'system');
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

        return $this->getLineResidual($this->documentCounterpartLine($move));
    }

    public function refreshPaymentState(AccountMove $move): AccountMove
    {
        if (!in_array($move->move_type, ['out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true)) {
            return $move;
        }

        $total = round((float) $move->amount_total, self::SCALE);
        $residual = $this->documentResidual($move);

        $state = match (true) {
            $residual <= 0.005 => 'paid',
            $residual < $total => 'partial',
            default => 'not_paid',
        };

        $move->update(['payment_state' => $state]);

        return $move->fresh();
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

    private function documentCounterpartLine(AccountMove $move): AccountMoveLine
    {
        $move->loadMissing('lines.account');

        $expectedInternalType = in_array($move->move_type, ['out_invoice', 'out_refund'], true)
            ? 'receivable'
            : 'payable';

        $line = $move->lines
            ->filter(fn (AccountMoveLine $line) => $line->account?->internal_type === $expectedInternalType)
            ->sortByDesc(fn (AccountMoveLine $line) => abs($line->balance))
            ->first();

        if (!$line) {
            throw new RuntimeException('The document has no receivable or payable line to reconcile.');
        }

        return $line;
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

        if (abs($lineA->balance) < 0.005 || abs($lineB->balance) < 0.005 || ($lineA->balance > 0) === ($lineB->balance > 0)) {
            throw new RuntimeException('Reconciliation requires one debit line and one credit line.');
        }

        $debitLine = $lineA->balance > 0 ? $lineA : $lineB;
        $creditLine = $lineA->balance < 0 ? $lineA : $lineB;

        return AccountPartialReconcile::create([
            'company_id' => $lineA->company_id,
            'debit_move_line_id' => $debitLine->id,
            'credit_move_line_id' => $creditLine->id,
            'amount' => round($amount, self::SCALE),
            'date' => $date->toDateString(),
        ]);
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
        }
    }

    private function assertSameCompany(int $a, int $b, string $label): void
    {
        if ($a !== $b) {
            throw new RuntimeException("Selected {$label} belongs to a different company.");
        }
    }

    /**
     * If a payment_term_id is provided, compute invoice_date_due from the term's
     * balance line (the line with value_type = 'balance', taking its `days` offset).
     * A manually entered invoice_date_due takes precedence over the computed value.
     */
    private function resolveInvoiceDueDate(array $data): array
    {
        if (!empty($data['payment_term_id'])) {
            $term = \App\Models\Accounting\AccountingPaymentTerm::with('lines')->find($data['payment_term_id']);
            if ($term) {
                $balanceLine = $term->lines->firstWhere('value_type', 'balance') ?? $term->lines->sortByDesc('days')->first();
                $days = $balanceLine ? (int) $balanceLine->days : 0;
                $invoiceDate = Carbon::parse($data['date'] ?? now());
                $data['invoice_date_due'] = $invoiceDate->copy()->addDays($days)->toDateString();
            }
        } elseif (empty($data['invoice_date_due'])) {
            $data['invoice_date_due'] = $data['date'] ?? null;
        }

        return $data;
    }

    private function buildDocumentLines(array $data, array $items): array
    {
        $moveType = $data['move_type'] ?? 'entry';
        if (!in_array($moveType, ['out_invoice', 'in_invoice'], true)) {
            throw new RuntimeException('Unsupported accounting document type.');
        }

        $partnerId        = $data['partner_id'] ?? null;
        $controlAccountId = $data['control_account_id'] ?? null;
        $companyId        = (int) ($data['company_id'] ?? 0);
        $currency         = $data['currency'] ?? null;

        if (!$controlAccountId) {
            throw new RuntimeException('A receivable or payable account is required.');
        }

        // Determine which taxes apply (sale vs purchase)
        $taxScope = $moveType === 'out_invoice' ? 'sale' : 'purchase';

        $lines         = [];
        $subtotal      = 0.0;
        $taxTotals     = []; // account_id => amount for merging same-account tax lines
        $sequence      = 10;

        foreach ($items as $item) {
            $quantity  = round((float) ($item['quantity']   ?? 0), self::SCALE);
            $priceUnit = round((float) ($item['price_unit'] ?? 0), self::SCALE);
            $taxIds    = array_filter((array) ($item['tax_ids'] ?? []));
            $productId = $item['product_id'] ?? null;
            $uomId     = $item['uom_id']     ?? null;

            // Load applicable taxes once (company-scoped, matching sale/purchase scope)
            $taxes = $taxIds
                ? AccountTax::whereIn('id', $taxIds)
                    ->where('company_id', $companyId)
                    ->whereIn('type_tax_use', [$taxScope, 'none'])
                    ->where('active', true)
                    ->get()
                : collect();

            // For price-inclusive taxes, the price_unit is gross; extract net base
            $grossAmount = round($quantity * $priceUnit, self::SCALE);
            if ($grossAmount <= 0) {
                continue;
            }

            // Net base (after extracting inclusive taxes)
            $netAmount = $grossAmount;
            foreach ($taxes as $tax) {
                if ($tax->include_base_amount) {
                    $netAmount = $tax->extractBase($netAmount);
                }
            }
            $netAmount = round($netAmount, self::SCALE);

            $subtotal = round($subtotal + $netAmount, self::SCALE);

            // Auto-derive label: use item name if set, fall back to product name
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
                'debit'           => $moveType === 'in_invoice' ? $netAmount : 0,
                'credit'          => $moveType === 'out_invoice' ? $netAmount : 0,
                'currency'        => $currency,
                'amount_currency' => $netAmount,
                'sequence'        => $sequence,
                'tax_ids'         => $taxes->pluck('id')->all(),
                'tax_base_amount' => $netAmount,
            ];
            $sequence += 10;

            // Accumulate tax amounts grouped by tax account
            foreach ($taxes as $tax) {
                // Inclusive taxes: computeAmount expects the gross (price_unit×qty) so it can extract
                // the embedded portion correctly. Exclusive taxes operate on the net base.
                $baseForCompute = $tax->include_base_amount ? $grossAmount : $netAmount;
                $taxAmount = $tax->computeAmount($baseForCompute);
                if ($taxAmount <= 0) {
                    continue;
                }
                $taxAccountId = $tax->account_id;
                if (!$taxAccountId) {
                    continue; // no account configured — skip
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
                $taxTotals[$key]['tax_base_amount']  = round($taxTotals[$key]['tax_base_amount'] + $netAmount, self::SCALE);
            }
        }

        if ($subtotal <= 0) {
            throw new RuntimeException('Invoice total must be greater than zero.');
        }

        // Emit one line per tax group
        $totalTaxes = 0.0;
        foreach ($taxTotals as $taxLine) {
            $amount = $taxLine['amount'];
            $totalTaxes = round($totalTaxes + $amount, self::SCALE);
            $lines[] = [
                'account_id'      => $taxLine['account_id'],
                'partner_id'      => $taxLine['partner_id'],
                'name'            => $taxLine['name'],
                'debit'           => $moveType === 'in_invoice' ? $amount : 0,
                'credit'          => $moveType === 'out_invoice' ? $amount : 0,
                'currency'        => $taxLine['currency'],
                'amount_currency' => $amount,
                'sequence'        => $sequence,
                'tax_line_id'     => $taxLine['tax_line_id'],
                'tax_base_amount' => $taxLine['tax_base_amount'],
            ];
            $sequence += 10;
        }

        $grandTotal = round($subtotal + $totalTaxes, self::SCALE);

        $lines[] = [
            'account_id'      => $controlAccountId,
            'partner_id'      => $partnerId,
            'name'            => $moveType === 'out_invoice' ? 'Customer balance' : 'Vendor balance',
            'debit'           => $moveType === 'out_invoice' ? $grandTotal : 0,
            'credit'          => $moveType === 'in_invoice'  ? $grandTotal : 0,
            'currency'        => $currency,
            'amount_currency' => $grandTotal,
            'sequence'        => 9999,
        ];

        return $lines;
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
                if (!Auth::user()?->hasPermission('accounting.lock')) {
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
