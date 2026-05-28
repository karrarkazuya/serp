<?php

namespace App\Services\Accounting;

use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Services\Company\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountingReportService
{
    public const INCOME_TYPES  = ['income', 'income_other'];
    public const EXPENSE_TYPES = ['expense', 'expense_depreciation', 'expense_direct_cost'];
    public const ASSET_TYPES   = ['asset_receivable', 'asset_cash', 'asset_current', 'asset_non_current', 'asset_prepayments', 'asset_fixed'];
    public const LIAB_TYPES    = ['liability_payable', 'liability_credit_card', 'liability_current', 'liability_non_current'];
    public const EQUITY_TYPES  = ['equity', 'equity_unaffected'];

    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    private function activeCompanyIds(): array
    {
        return $this->companyContext->getActiveCompanyIds();
    }

    // ── Filter normalization ─────────────────────────────────────────────────

    /**
     * Normalise the date range filter values from the request.
     * Returns ['date_from' => ?string, 'date_to' => ?string] as Y-m-d strings,
     * or null when missing/invalid. Invalid dates are silently dropped so a
     * bad query string can't error out the report — the report just renders
     * unfiltered on that side.
     */
    public function parseDateRange(?string $from, ?string $to): array
    {
        return [
            'date_from' => $this->parseDate($from),
            'date_to'   => $this->parseDate($to),
        ];
    }

    public function parseDate(?string $value): ?string
    {
        if (!$value) return null;
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Validate that an id-like filter belongs to the active companies.
     * Returns the integer id when valid; null otherwise. This blocks a user
     * from filtering on a partner/account/journal id from another company
     * even though the underlying report query would already gate-by-company.
     */
    public function validateScopedId(?string $rawId, string $table, ?string $companyColumn = 'company_id'): ?int
    {
        if (!$rawId || !ctype_digit((string) $rawId)) return null;
        $id = (int) $rawId;
        $companyIds = $this->activeCompanyIds();
        if (empty($companyIds)) return null;

        $query = DB::table($table)->where('id', $id);
        if ($companyColumn) {
            $query->whereIn($companyColumn, $companyIds);
        }
        return $query->exists() ? $id : null;
    }

    // ── Core query builders ──────────────────────────────────────────────────

    private function baseLineQuery(array $companyIds): Builder
    {
        $query = AccountMoveLine::query()
            ->where('account_move_lines.state', 'posted');

        if (empty($companyIds)) {
            $query->whereRaw('1 = 0');
        } else {
            $query->whereIn('account_move_lines.company_id', $companyIds);
        }

        return $query;
    }

    /**
     * GENERAL LEDGER: posted journal items with optional date/account/journal/partner filters.
     */
    public function generalLedger(array $filters): Builder
    {
        $companyIds = $this->activeCompanyIds();
        $query = $this->baseLineQuery($companyIds)
            ->with(['account', 'journal', 'move', 'partner'])
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
            ->when($filters['account_id'] ?? null, fn ($q, $v) => $q->where('account_id', $v))
            ->when($filters['journal_id'] ?? null, fn ($q, $v) => $q->where('account_move_lines.journal_id', $v))
            ->when($filters['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->orderBy('account_move_lines.date')
            ->orderBy('account_move_lines.id');

        return $query;
    }

    /**
     * TRIAL BALANCE: aggregate debit/credit/balance per account.
     */
    public function trialBalance(array $filters): Collection
    {
        $companyIds = $this->activeCompanyIds();

        return AccountMoveLine::query()
            ->select(
                'account_move_lines.account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.account_type',
                'accounts.internal_type',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                // Aliased as `net_balance` (not `balance`) because AccountMoveLine
                // has a getBalanceAttribute() accessor that would shadow the
                // SQL column and return (debit - credit) = 0 from the empty
                // per-row debit/credit attributes (we only select totals).
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net_balance'),
            )
            ->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
            ->when($filters['account_type'] ?? null, fn ($q, $v) => $q->where('accounts.account_type', $v))
            ->when($filters['journal_id'] ?? null, fn ($q, $v) => $q->where('account_move_lines.journal_id', $v))
            ->groupBy('account_move_lines.account_id', 'accounts.code', 'accounts.name', 'accounts.account_type', 'accounts.internal_type')
            ->orderBy('accounts.code')
            ->get();
    }

    /**
     * P&L / BALANCE SHEET helper: aggregate per account filtered by account_type set.
     */
    private function sumByAccountType(array $types, ?string $dateFrom, ?string $dateTo, ?int $journalId = null): Collection
    {
        $companyIds = $this->activeCompanyIds();

        return AccountMoveLine::query()
            ->select(
                'account_move_lines.account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.account_type',
                'accounts.internal_type',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net'),
            )
            ->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->whereIn('accounts.account_type', $types)
            ->when($dateFrom, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
            ->when($dateTo,   fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
            ->when($journalId, fn ($q, $v) => $q->where('account_move_lines.journal_id', $v))
            ->groupBy('account_move_lines.account_id', 'accounts.code', 'accounts.name', 'accounts.account_type', 'accounts.internal_type')
            ->orderBy('accounts.code')
            ->get();
    }

    /**
     * PROFIT & LOSS.
     */
    public function profitAndLoss(array $filters): array
    {
        $income  = $this->sumByAccountType(self::INCOME_TYPES,  $filters['date_from'] ?? null, $filters['date_to'] ?? null, $filters['journal_id'] ?? null);
        $expense = $this->sumByAccountType(self::EXPENSE_TYPES, $filters['date_from'] ?? null, $filters['date_to'] ?? null, $filters['journal_id'] ?? null);

        $totalIncome  = (float) $income->sum('net');
        $totalExpense = (float) $expense->sum('net');

        return [
            'income'        => $income,
            'expense'       => $expense,
            'total_income'  => abs($totalIncome),
            'total_expense' => abs($totalExpense),
            'net_profit'    => abs($totalIncome) - abs($totalExpense),
        ];
    }

    /**
     * BALANCE SHEET — semantically cumulative as of `date_to`.
     * Ignores `date_from` so the report shows correct standing balances on a
     * given date instead of "movement during a period" which would be wrong.
     */
    public function balanceSheet(array $filters): array
    {
        $asOf = $filters['date_to'] ?? null;

        $assets      = $this->sumByAccountType(self::ASSET_TYPES,  null, $asOf);
        $liabilities = $this->sumByAccountType(self::LIAB_TYPES,   null, $asOf);
        $equity      = $this->sumByAccountType(self::EQUITY_TYPES, null, $asOf);

        // Current-year earnings = revenue - expenses since the start of the
        // current fiscal year up to `as_of`. We use Jan 1 of the as-of year as
        // the start (matches Odoo's default fiscal year).
        $yearStart = $asOf ? Carbon::parse($asOf)->copy()->startOfYear()->toDateString() : null;
        $income   = $this->sumByAccountType(self::INCOME_TYPES,  $yearStart, $asOf)->sum('net');
        $expenses = $this->sumByAccountType(self::EXPENSE_TYPES, $yearStart, $asOf)->sum('net');
        $currentYearEarnings = abs((float) $income) - abs((float) $expenses);

        return [
            'as_of'                 => $asOf,
            'assets'                => $assets,
            'liabilities'           => $liabilities,
            'equity'                => $equity,
            'current_year_earnings' => $currentYearEarnings,
            'total_assets'          => (float) $assets->sum('net'),
            'total_liabilities'     => abs((float) $liabilities->sum('net')),
            'total_equity'          => abs((float) $equity->sum('net')) + $currentYearEarnings,
        ];
    }

    /**
     * CASH FLOW: movements through bank/cash accounts.
     */
    public function cashFlow(array $filters): array
    {
        $companyIds = $this->activeCompanyIds();

        $rows = AccountMoveLine::query()
            ->select(
                'account_move_lines.account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.account_type',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net'),
            )
            ->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->where('accounts.account_type', 'asset_cash')
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
            ->when($filters['journal_id'] ?? null, fn ($q, $v) => $q->where('account_move_lines.journal_id', $v))
            ->groupBy('account_move_lines.account_id', 'accounts.code', 'accounts.name', 'accounts.account_type')
            ->orderBy('accounts.code')
            ->get();

        return [
            'rows'           => $rows,
            'total_inflow'   => (float) $rows->sum('total_debit'),
            'total_outflow'  => (float) $rows->sum('total_credit'),
            'net_cash_flow'  => (float) ($rows->sum('total_debit') - $rows->sum('total_credit')),
        ];
    }

    /**
     * TAX REPORT: per-tax aggregated base and tax amounts. Splits sale vs
     * purchase using the linked tax's `type_tax_use` for compliance-grade
     * separation of output VAT (sale → credit-side) and input VAT (purchase
     * → debit-side).
     */
    public function taxReport(array $filters): Collection
    {
        $companyIds = $this->activeCompanyIds();

        return AccountMoveLine::query()
            ->select(
                'account_move_lines.tax_line_id',
                'account_taxes.name as tax_name',
                'account_taxes.amount as tax_rate',
                'account_taxes.type_tax_use as tax_use',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                DB::raw('SUM(account_move_lines.tax_base_amount) as total_base'),
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net'),
            )
            ->join('account_taxes', 'account_taxes.id', '=', 'account_move_lines.tax_line_id')
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->whereNotNull('account_move_lines.tax_line_id')
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
            ->when($filters['tax_use']   ?? null, fn ($q, $v) => $q->where('account_taxes.type_tax_use', $v))
            ->groupBy('account_move_lines.tax_line_id', 'account_taxes.name', 'account_taxes.amount', 'account_taxes.type_tax_use')
            ->orderBy('account_taxes.type_tax_use')
            ->orderBy('account_taxes.name')
            ->get();
    }

    /**
     * PARTNER LEDGER: per-partner debit/credit/balance.
     */
    public function partnerLedger(array $filters): Collection
    {
        $companyIds = $this->activeCompanyIds();

        $query = AccountMoveLine::query()
            ->select(
                'account_move_lines.partner_id',
                'contacts.name as partner_name',
                'contacts.contact_type',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                // See trialBalance() comment — `balance` alias collides with
                // the AccountMoveLine getBalanceAttribute() accessor.
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net_balance'),
            )
            ->join('contacts', 'contacts.id', '=', 'account_move_lines.partner_id')
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->whereNotNull('account_move_lines.partner_id')
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
            ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
            ->when($filters['partner_id'] ?? null, fn ($q, $v) => $q->where('account_move_lines.partner_id', $v))
            ->when($filters['account_id'] ?? null, fn ($q, $v) => $q->where('account_move_lines.account_id', $v))
            ->groupBy('account_move_lines.partner_id', 'contacts.name', 'contacts.contact_type')
            ->orderBy('contacts.name');

        // Restrict to receivable/payable only by default — that's what the
        // "partner ledger" means in accounting. Without this the report would
        // bucket cash/expense lines per partner which is rarely useful.
        if (!empty($filters['receivable_only'])) {
            $query->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
                  ->where('accounts.internal_type', 'receivable');
        } elseif (!empty($filters['payable_only'])) {
            $query->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
                  ->where('accounts.internal_type', 'payable');
        } elseif (!empty($filters['ar_ap_only'])) {
            $query->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
                  ->whereIn('accounts.internal_type', ['receivable', 'payable']);
        }

        return $query->get();
    }

    /**
     * AGED REPORT (receivable or payable): per-installment bucketing.
     */
    public function agedReport(string $asOf, string $moveType, ?int $partnerId = null): Collection
    {
        $companyIds   = $this->activeCompanyIds();
        $internalType = $moveType === 'out_invoice' ? 'receivable' : 'payable';

        $lines = AccountMoveLine::query()
            ->with(['move', 'account', 'partner', 'matchedDebits', 'matchedCredits'])
            ->whereHas('account', fn ($q) => $q->where('internal_type', $internalType))
            ->whereHas('move', fn ($q) => $q->where('move_type', $moveType)->where('state', '!=', 'cancelled'))
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->where('state', 'posted')
            ->whereNotNull('partner_id')
            ->when($partnerId, fn ($q, $v) => $q->where('partner_id', $v))
            ->get();

        $asOfDate = Carbon::parse($asOf);

        $installmentMeta = [];
        foreach ($lines->groupBy('move_id') as $moveLines) {
            $sorted = $moveLines->sortBy([
                fn ($a, $b) => ($a->date_maturity?->timestamp ?? 0) <=> ($b->date_maturity?->timestamp ?? 0),
                fn ($a, $b) => $a->sequence <=> $b->sequence,
            ])->values();
            $total = $sorted->count();
            foreach ($sorted as $idx => $line) {
                $installmentMeta[$line->id] = ['number' => $idx + 1, 'total' => $total];
            }
        }

        return $lines
            ->map(function (AccountMoveLine $line) use ($asOfDate, $installmentMeta) {
                $matched  = (float) $line->matchedDebits->sum('amount') + (float) $line->matchedCredits->sum('amount');
                $balance  = (float) $line->debit - (float) $line->credit;
                $residual = round(abs($balance) - $matched, 2);
                $dueDate  = $line->date_maturity ?? $line->date;
                $meta     = $installmentMeta[$line->id] ?? ['number' => 1, 'total' => 1];

                return (object) [
                    'move_id'            => $line->move_id,
                    'line_id'            => $line->id,
                    'name'               => $line->move?->name,
                    'invoice_date'       => $line->move?->date,
                    'invoice_date_due'   => $dueDate,
                    'partner_id'         => $line->partner_id,
                    'partner_name'       => $line->partner?->name,
                    'residual'           => $residual,
                    'days_overdue'       => max(0, (int) ($asOfDate->diffInDays($dueDate, false) * -1)),
                    'installment_number' => $meta['number'],
                    'total_installments' => $meta['total'],
                ];
            })
            ->filter(fn ($row) => $row->residual > 0.005)
            ->filter(fn ($row) => $row->invoice_date_due && $row->invoice_date_due <= $asOfDate)
            ->map(function ($row) {
                $row->bucket = match (true) {
                    $row->days_overdue <= 0  => 'Current',
                    $row->days_overdue <= 30 => '1–30',
                    $row->days_overdue <= 60 => '31–60',
                    $row->days_overdue <= 90 => '61–90',
                    default                  => '90+',
                };
                return $row;
            })
            ->sortBy('invoice_date_due')
            ->values();
    }

    /**
     * JOURNAL AUDIT: every move (draft/posted/cancelled) for review.
     */
    public function journalAudit(array $filters): Builder
    {
        $companyIds = $this->activeCompanyIds();

        $query = AccountMove::query()
            ->with(['journal', 'partner', 'company'])
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->when($filters['date_from']  ?? null, fn ($q, $v) => $q->where('date', '>=', $v))
            ->when($filters['date_to']    ?? null, fn ($q, $v) => $q->where('date', '<=', $v))
            ->when($filters['journal_id'] ?? null, fn ($q, $v) => $q->where('journal_id', $v))
            ->when($filters['partner_id'] ?? null, fn ($q, $v) => $q->where('partner_id', $v))
            ->when($filters['state']      ?? null, fn ($q, $v) => $q->where('state', $v))
            ->when($filters['move_type']  ?? null, fn ($q, $v) => $q->where('move_type', $v))
            ->orderBy('date', 'desc')
            ->orderBy('name');

        // Default to posted unless caller explicitly chose another state.
        if (empty($filters['state'])) {
            $query->where('state', 'posted');
        }

        return $query;
    }

    /**
     * BANK RECONCILIATION: posted lines on a single bank/cash journal.
     */
    public function bankReconciliation(array $filters): array
    {
        $companyIds = $this->activeCompanyIds();

        $bankJournals = AccountJournal::query()
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->whereIn('type', ['bank', 'cash'])
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $linesQuery = null;
        if (!empty($filters['journal_id'])) {
            $linesQuery = AccountMoveLine::query()
                ->with(['move', 'account', 'partner'])
                ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
                ->where('account_move_lines.journal_id', $filters['journal_id'])
                ->where('account_move_lines.state', 'posted')
                ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '>=', $v))
                ->when($filters['date_to']   ?? null, fn ($q, $v) => $q->where('account_move_lines.date', '<=', $v))
                ->orderBy('account_move_lines.date')
                ->orderBy('account_move_lines.id');
        }

        return [
            'bank_journals' => $bankJournals,
            'lines_query'   => $linesQuery,
        ];
    }

    /**
     * EXECUTIVE SUMMARY: high-level KPIs.
     */
    public function executiveSummary(array $filters): array
    {
        $companyIds = $this->activeCompanyIds();
        $dateFrom = $filters['date_from'] ?? null;
        $dateTo   = $filters['date_to']   ?? null;

        $totalIncome    = abs((float) $this->sumByAccountType(self::INCOME_TYPES,  $dateFrom, $dateTo)->sum('net'));
        $totalExpense   = abs((float) $this->sumByAccountType(self::EXPENSE_TYPES, $dateFrom, $dateTo)->sum('net'));
        $netProfit      = $totalIncome - $totalExpense;
        $totalAssets    = (float) $this->sumByAccountType(self::ASSET_TYPES, null, $dateTo)->sum('net');
        $totalLiabs     = abs((float) $this->sumByAccountType(self::LIAB_TYPES, null, $dateTo)->sum('net'));

        $draftCount = AccountMove::query()
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->where('state', 'draft')
            ->count();

        $overdueCount = AccountMove::query()
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->whereIn('move_type', ['out_invoice', 'in_invoice'])
            ->where('state', 'posted')
            ->whereIn('payment_state', ['not_paid', 'partial'])
            ->where('invoice_date_due', '<', now()->toDateString())
            ->count();

        return [
            'total_income'  => $totalIncome,
            'total_expense' => $totalExpense,
            'net_profit'    => $netProfit,
            'total_assets'  => $totalAssets,
            'total_liabs'   => $totalLiabs,
            'draft_count'   => $draftCount,
            'overdue_count' => $overdueCount,
        ];
    }

    // ── Date range presets ───────────────────────────────────────────────────

    /**
     * Resolve a date-range preset string ("this_month", "last_quarter", ...)
     * to an explicit [from, to] tuple. Returns null if the preset isn't known.
     */
    public function resolvePreset(string $preset): ?array
    {
        $now = now();
        return match ($preset) {
            'today'         => [$now->copy()->toDateString(), $now->copy()->toDateString()],
            'yesterday'     => [$now->copy()->subDay()->toDateString(), $now->copy()->subDay()->toDateString()],
            'this_month'    => [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()],
            'last_month'    => [$now->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $now->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'this_quarter'  => [$now->copy()->startOfQuarter()->toDateString(), $now->copy()->endOfQuarter()->toDateString()],
            'last_quarter'  => [$now->copy()->subQuarterNoOverflow()->startOfQuarter()->toDateString(), $now->copy()->subQuarterNoOverflow()->endOfQuarter()->toDateString()],
            'this_year'     => [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()],
            'last_year'     => [$now->copy()->subYear()->startOfYear()->toDateString(), $now->copy()->subYear()->endOfYear()->toDateString()],
            'ytd'           => [$now->copy()->startOfYear()->toDateString(), $now->copy()->toDateString()],
            default         => null,
        };
    }
}
