<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountJournal;
use App\Models\Accounting\AccountMove;
use App\Models\Accounting\AccountMoveLine;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingReportController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    // ── General Ledger ──────────────────────────────────────────────────────

    public function generalLedger(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo, $accountId, $journalId] = $this->dateFilters($request);

        $query = AccountMoveLine::query()
            ->with(['account', 'journal', 'move'])
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $activeCompanyIds))
            ->where('account_move_lines.state', 'posted')
            ->when($dateFrom, fn ($q) => $q->where('account_move_lines.date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('account_move_lines.date', '<=', $dateTo))
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($journalId, fn ($q) => $q->where('account_move_lines.journal_id', $journalId))
            ->orderBy('account_move_lines.date')
            ->orderBy('account_move_lines.id');

        $lines = $query->paginate(100)->withQueryString();

        return view('accounting.reports.general-ledger', compact(
            'lines', 'dateFrom', 'dateTo', 'accountId', 'journalId'
        ));
    }

    // ── Trial Balance ───────────────────────────────────────────────────────

    public function trialBalance(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        $rows = AccountMoveLine::query()
            ->select(
                'account_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(debit) - SUM(credit) as balance')
            )
            ->with('account')
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'posted')
            ->when($dateFrom, fn ($q) => $q->where('date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('date', '<=', $dateTo))
            ->groupBy('account_id')
            ->orderBy('account_id')
            ->get();

        $totalDebit  = $rows->sum('total_debit');
        $totalCredit = $rows->sum('total_credit');

        return view('accounting.reports.trial-balance', compact(
            'rows', 'totalDebit', 'totalCredit', 'dateFrom', 'dateTo'
        ));
    }

    // ── Profit & Loss ───────────────────────────────────────────────────────

    public function profitAndLoss(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        $incomeTypes   = ['income', 'income_other'];
        $expenseTypes  = ['expense', 'expense_depreciation', 'expense_direct_cost'];

        $income  = $this->sumByAccountType($activeCompanyIds, $incomeTypes, $dateFrom, $dateTo);
        $expense = $this->sumByAccountType($activeCompanyIds, $expenseTypes, $dateFrom, $dateTo);

        $totalIncome  = $income->sum('net');
        $totalExpense = $expense->sum('net');
        $netProfit    = $totalIncome - $totalExpense;

        return view('accounting.reports.profit-and-loss', compact(
            'income', 'expense', 'totalIncome', 'totalExpense', 'netProfit', 'dateFrom', 'dateTo'
        ));
    }

    // ── Balance Sheet ───────────────────────────────────────────────────────

    public function balanceSheet(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        $assetTypes     = ['asset_receivable', 'asset_cash', 'asset_current', 'asset_non_current', 'asset_prepayments', 'asset_fixed'];
        $liabilityTypes = ['liability_payable', 'liability_credit_card', 'liability_current', 'liability_non_current'];
        $equityTypes    = ['equity', 'equity_unaffected'];

        $assets      = $this->sumByAccountType($activeCompanyIds, $assetTypes, $dateFrom, $dateTo);
        $liabilities = $this->sumByAccountType($activeCompanyIds, $liabilityTypes, $dateFrom, $dateTo);
        $equity      = $this->sumByAccountType($activeCompanyIds, $equityTypes, $dateFrom, $dateTo);

        // Also include current-year earnings (P&L)
        $incomeTypes  = ['income', 'income_other'];
        $expenseTypes = ['expense', 'expense_depreciation', 'expense_direct_cost'];
        $income  = $this->sumByAccountType($activeCompanyIds, $incomeTypes, $dateFrom, $dateTo)->sum('net');
        $expenses = $this->sumByAccountType($activeCompanyIds, $expenseTypes, $dateFrom, $dateTo)->sum('net');
        $currentYearEarnings = $income - $expenses;

        $totalAssets      = $assets->sum('net');
        $totalLiabilities = $liabilities->sum('net');
        $totalEquity      = $equity->sum('net') + $currentYearEarnings;

        return view('accounting.reports.balance-sheet', compact(
            'assets', 'liabilities', 'equity', 'currentYearEarnings',
            'totalAssets', 'totalLiabilities', 'totalEquity', 'dateFrom', 'dateTo'
        ));
    }

    // ── Cash Flow Statement ─────────────────────────────────────────────────

    public function cashFlow(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        // Cash flow = movements in bank/cash accounts
        $cashRows = AccountMoveLine::query()
            ->select(
                'account_move_lines.account_id',
                'accounts.name as account_name',
                'accounts.code as account_code',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net')
            )
            ->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $activeCompanyIds))
            ->where('account_move_lines.state', 'posted')
            ->where('accounts.account_type', 'asset_cash')
            ->when($dateFrom, fn ($q) => $q->where('account_move_lines.date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('account_move_lines.date', '<=', $dateTo))
            ->groupBy('account_move_lines.account_id', 'accounts.name', 'accounts.code')
            ->orderBy('accounts.code')
            ->get();

        $totalInflow  = $cashRows->sum('total_debit');
        $totalOutflow = $cashRows->sum('total_credit');
        $netCashFlow  = $totalInflow - $totalOutflow;

        return view('accounting.reports.cash-flow', compact(
            'cashRows', 'totalInflow', 'totalOutflow', 'netCashFlow', 'dateFrom', 'dateTo'
        ));
    }

    // ── Tax Report ──────────────────────────────────────────────────────────

    public function taxReport(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        $rows = AccountMoveLine::query()
            ->select(
                'tax_line_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(tax_base_amount) as total_base'),
                DB::raw('SUM(debit) - SUM(credit) as net')
            )
            ->with('taxLine')
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'posted')
            ->whereNotNull('tax_line_id')
            ->when($dateFrom, fn ($q) => $q->where('date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('date', '<=', $dateTo))
            ->groupBy('tax_line_id')
            ->orderBy('tax_line_id')
            ->get();

        return view('accounting.reports.tax-report', compact('rows', 'dateFrom', 'dateTo'));
    }

    // ── Partner Ledger ──────────────────────────────────────────────────────

    public function partnerLedger(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo, , , $partnerId] = $this->dateFilters($request);

        $rows = AccountMoveLine::query()
            ->select(
                'partner_id',
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(debit) - SUM(credit) as balance')
            )
            ->with('partner')
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'posted')
            ->whereNotNull('partner_id')
            ->when($dateFrom,  fn ($q) => $q->where('date', '>=', $dateFrom))
            ->when($dateTo,    fn ($q) => $q->where('date', '<=', $dateTo))
            ->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId))
            ->groupBy('partner_id')
            ->orderBy('partner_id')
            ->get();

        return view('accounting.reports.partner-ledger', compact('rows', 'dateFrom', 'dateTo', 'partnerId'));
    }

    // ── Aged Receivable ─────────────────────────────────────────────────────

    public function agedReceivable(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $asOf = $request->query('as_of', now()->toDateString());

        $rows = $this->agedReport($activeCompanyIds, $asOf, 'out_invoice');

        return view('accounting.reports.aged-receivable', compact('rows', 'asOf'));
    }

    // ── Aged Payable ────────────────────────────────────────────────────────

    public function agedPayable(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $asOf = $request->query('as_of', now()->toDateString());

        $rows = $this->agedReport($activeCompanyIds, $asOf, 'in_invoice');

        return view('accounting.reports.aged-payable', compact('rows', 'asOf'));
    }

    // ── Journal Audit ───────────────────────────────────────────────────────

    public function journalAudit(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo, , $journalId] = $this->dateFilters($request);

        $moves = AccountMove::query()
            ->with(['journal', 'partner', 'company'])
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'posted')
            ->when($dateFrom,  fn ($q) => $q->where('date', '>=', $dateFrom))
            ->when($dateTo,    fn ($q) => $q->where('date', '<=', $dateTo))
            ->when($journalId, fn ($q) => $q->where('journal_id', $journalId))
            ->orderBy('date')
            ->orderBy('name')
            ->paginate(100)->withQueryString();

        return view('accounting.reports.journal-audit', compact('moves', 'dateFrom', 'dateTo', 'journalId'));
    }

    // ── Bank Reconciliation ─────────────────────────────────────────────────

    public function bankReconciliation(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $bankJournals = AccountJournal::query()
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->whereIn('type', ['bank', 'cash'])
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $journalId = $request->query('journal_id');
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        $lines = collect();
        if ($journalId) {
            $lines = AccountMoveLine::query()
                ->with(['move', 'account'])
                ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
                ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $activeCompanyIds))
                ->where('account_move_lines.journal_id', $journalId)
                ->where('account_move_lines.state', 'posted')
                ->when($dateFrom, fn ($q) => $q->where('account_move_lines.date', '>=', $dateFrom))
                ->when($dateTo,   fn ($q) => $q->where('account_move_lines.date', '<=', $dateTo))
                ->orderBy('account_move_lines.date')
                ->orderBy('account_move_lines.id')
                ->paginate(100)->withQueryString();
        }

        return view('accounting.reports.bank-reconciliation', compact(
            'bankJournals', 'journalId', 'lines', 'dateFrom', 'dateTo'
        ));
    }

    // ── Executive Summary ───────────────────────────────────────────────────

    public function executiveSummary(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        [$dateFrom, $dateTo] = $this->dateFilters($request);

        $incomeTypes  = ['income', 'income_other'];
        $expenseTypes = ['expense', 'expense_depreciation', 'expense_direct_cost'];
        $assetTypes   = ['asset_receivable', 'asset_cash', 'asset_current', 'asset_non_current', 'asset_prepayments', 'asset_fixed'];
        $liabTypes    = ['liability_payable', 'liability_credit_card', 'liability_current', 'liability_non_current'];

        $totalIncome    = $this->sumByAccountType($activeCompanyIds, $incomeTypes, $dateFrom, $dateTo)->sum('net');
        $totalExpense   = $this->sumByAccountType($activeCompanyIds, $expenseTypes, $dateFrom, $dateTo)->sum('net');
        $netProfit      = $totalIncome - $totalExpense;
        $totalAssets    = $this->sumByAccountType($activeCompanyIds, $assetTypes, null, $dateTo)->sum('net');
        $totalLiabs     = $this->sumByAccountType($activeCompanyIds, $liabTypes, null, $dateTo)->sum('net');

        $draftCount   = AccountMove::query()
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'draft')->count();

        $overdueCount = AccountMove::query()
            ->when(empty($activeCompanyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->whereIn('move_type', ['out_invoice', 'in_invoice'])
            ->where('state', 'posted')
            ->whereIn('payment_state', ['not_paid', 'partial'])
            ->where('invoice_date_due', '<', now()->toDateString())
            ->count();

        return view('accounting.reports.executive-summary', compact(
            'totalIncome', 'totalExpense', 'netProfit',
            'totalAssets', 'totalLiabs', 'draftCount', 'overdueCount',
            'dateFrom', 'dateTo'
        ));
    }

    // ── Private Helpers ─────────────────────────────────────────────────────

    private function dateFilters(Request $request): array
    {
        $dateFrom  = $request->query('date_from');
        $dateTo    = $request->query('date_to');
        $accountId = $request->query('account_id');
        $journalId = $request->query('journal_id');
        $partnerId = $request->query('partner_id');
        return [$dateFrom, $dateTo, $accountId, $journalId, $partnerId];
    }

    private function sumByAccountType(array $companyIds, array $types, ?string $dateFrom, ?string $dateTo): \Illuminate\Support\Collection
    {
        return AccountMoveLine::query()
            ->select(
                'account_move_lines.account_id',
                'accounts.code as account_code',
                'accounts.name as account_name',
                'accounts.account_type',
                DB::raw('SUM(account_move_lines.debit) as total_debit'),
                DB::raw('SUM(account_move_lines.credit) as total_credit'),
                DB::raw('SUM(account_move_lines.debit) - SUM(account_move_lines.credit) as net')
            )
            ->join('accounts', 'accounts.id', '=', 'account_move_lines.account_id')
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->whereIn('accounts.account_type', $types)
            ->when($dateFrom, fn ($q) => $q->where('account_move_lines.date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('account_move_lines.date', '<=', $dateTo))
            ->groupBy('account_move_lines.account_id', 'accounts.code', 'accounts.name', 'accounts.account_type')
            ->orderBy('accounts.code')
            ->get();
    }

    /**
     * D1 / Odoo parity: bucket by per-line `date_maturity` and compute the
     * actual outstanding residual (line.balance - matched_amount), not the
     * move header's gross `amount_total`. Multi-installment invoices have N
     * receivable/payable lines, each with its own due date — the aged report
     * MUST bucket each installment separately. Without this, a "30% now,
     * 70% in 60 days" invoice reports the full 100% in the 60-day bucket
     * even after the 30% leg has been paid.
     *
     * Lines with no `date_maturity` (legacy single-counterpart invoices, or
     * direct journal entries that happened to use a receivable/payable
     * account) fall back to their own `date` for bucketing.
     */
    private function agedReport(array $companyIds, string $asOf, string $moveType): \Illuminate\Support\Collection
    {
        $internalType = $moveType === 'out_invoice' ? 'receivable' : 'payable';

        $lines = AccountMoveLine::query()
            ->with(['move', 'account', 'partner', 'matchedDebits', 'matchedCredits'])
            ->whereHas('account', fn ($q) => $q->where('internal_type', $internalType))
            ->whereHas('move', fn ($q) => $q->where('move_type', $moveType)->where('state', '!=', 'cancelled'))
            ->when(empty($companyIds), fn ($q) => $q->whereRaw('1 = 0'))
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('company_id', $companyIds))
            ->where('state', 'posted')
            ->whereNotNull('partner_id')
            ->get();

        $asOfDate = now()->parse($asOf);

        // D1 (Odoo parity): assign installment_number (1-based) per move so the
        // report can show "2/3" next to the invoice number. Sort within each
        // move by date_maturity ASC then sequence, mirroring the order used in
        // documentCounterpartLines().
        $installmentMeta = [];
        foreach ($lines->groupBy('move_id') as $moveId => $moveLines) {
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

                // Use date_maturity when set (multi-installment invoices via
                // splitGrandTotalAcrossPaymentTerm); fall back to line.date
                // for legacy single-counterpart rows.
                $dueDate = $line->date_maturity ?? $line->date;

                $meta = $installmentMeta[$line->id] ?? ['number' => 1, 'total' => 1];

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
            // Drop fully-paid installments (residual <= rounding floor).
            ->filter(fn ($row) => $row->residual > 0.005)
            // Aged report shows only items due on or before $asOf.
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
}
