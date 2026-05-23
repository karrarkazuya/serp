<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\Account;
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
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $activeCompanyIds))
            ->where('account_move_lines.state', 'posted')
            ->when($dateFrom, fn ($q) => $q->where('account_move_lines.date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('account_move_lines.date', '<=', $dateTo))
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->when($journalId, fn ($q) => $q->where('account_move_lines.journal_id', $journalId))
            ->orderBy('account_move_lines.date')
            ->orderBy('account_move_lines.id');

        $lines = $query->paginate(100)->withQueryString();

        $journals = $this->journals($activeCompanyIds);
        $accounts = $this->accounts($activeCompanyIds);

        return view('accounting.reports.general-ledger', compact(
            'lines', 'journals', 'accounts', 'dateFrom', 'dateTo', 'accountId', 'journalId'
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
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'posted')
            ->when($dateFrom,  fn ($q) => $q->where('date', '>=', $dateFrom))
            ->when($dateTo,    fn ($q) => $q->where('date', '<=', $dateTo))
            ->when($journalId, fn ($q) => $q->where('journal_id', $journalId))
            ->orderBy('date')
            ->orderBy('name')
            ->paginate(100)->withQueryString();

        $journals = $this->journals($activeCompanyIds);

        return view('accounting.reports.journal-audit', compact('moves', 'journals', 'dateFrom', 'dateTo', 'journalId'));
    }

    // ── Bank Reconciliation ─────────────────────────────────────────────────

    public function bankReconciliation(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $bankJournals = AccountJournal::query()
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
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('state', 'draft')->count();

        $overdueCount = AccountMove::query()
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

    private function journals(array $activeCompanyIds): \Illuminate\Database\Eloquent\Collection
    {
        return AccountJournal::query()
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'type']);
    }

    private function accounts(array $activeCompanyIds): \Illuminate\Database\Eloquent\Collection
    {
        return Account::query()
            ->when(!empty($activeCompanyIds), fn ($q) => $q->whereIn('company_id', $activeCompanyIds))
            ->where('active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);
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
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_move_lines.company_id', $companyIds))
            ->where('account_move_lines.state', 'posted')
            ->whereIn('accounts.account_type', $types)
            ->when($dateFrom, fn ($q) => $q->where('account_move_lines.date', '>=', $dateFrom))
            ->when($dateTo,   fn ($q) => $q->where('account_move_lines.date', '<=', $dateTo))
            ->groupBy('account_move_lines.account_id', 'accounts.code', 'accounts.name', 'accounts.account_type')
            ->orderBy('accounts.code')
            ->get();
    }

    private function agedReport(array $companyIds, string $asOf, string $moveType): \Illuminate\Support\Collection
    {
        $accountType = $moveType === 'out_invoice' ? 'asset_receivable' : 'liability_payable';

        return AccountMove::query()
            ->select(
                'account_moves.id',
                'account_moves.name',
                'account_moves.date as invoice_date',
                'account_moves.invoice_date_due',
                'account_moves.partner_id',
                'contacts.name as partner_name',
                'account_moves.amount_total as residual'
            )
            ->join('contacts', 'contacts.id', '=', 'account_moves.partner_id')
            ->when(!empty($companyIds), fn ($q) => $q->whereIn('account_moves.company_id', $companyIds))
            ->where('account_moves.move_type', $moveType)
            ->where('account_moves.state', 'posted')
            ->whereIn('account_moves.payment_state', ['not_paid', 'partial'])
            ->where('account_moves.invoice_date_due', '<=', $asOf)
            ->orderBy('account_moves.invoice_date_due')
            ->get()
            ->map(function ($row) use ($asOf) {
                $daysOverdue = now()->parse($asOf)->diffInDays($row->invoice_date_due, false) * -1;
                $row->days_overdue = max(0, (int) $daysOverdue);
                $row->bucket = match (true) {
                    $row->days_overdue <= 0  => 'Current',
                    $row->days_overdue <= 30 => '1–30',
                    $row->days_overdue <= 60 => '31–60',
                    $row->days_overdue <= 90 => '61–90',
                    default                  => '90+',
                };
                return $row;
            });
    }
}
