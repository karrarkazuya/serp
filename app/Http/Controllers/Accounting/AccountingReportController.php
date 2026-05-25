<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Http\Request;

class AccountingReportController extends Controller
{
    public function __construct(
        private readonly AccountingReportService $reports,
    ) {}

    // ── General Ledger ──────────────────────────────────────────────────────

    public function generalLedger(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, [
            'date_from', 'date_to', 'account_id', 'journal_id', 'partner_id',
        ]);
        $lines = $this->reports->generalLedger($filters)->paginate(100)->withQueryString();

        return view('accounting.reports.general-ledger', [
            'lines'   => $lines,
            'filters' => $filters,
        ]);
    }

    // ── Trial Balance ───────────────────────────────────────────────────────

    public function trialBalance(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, [
            'date_from', 'date_to', 'account_type', 'journal_id',
        ]);
        $rows = $this->reports->trialBalance($filters);

        return view('accounting.reports.trial-balance', [
            'rows'         => $rows,
            'totalDebit'   => (float) $rows->sum('total_debit'),
            'totalCredit'  => (float) $rows->sum('total_credit'),
            'filters'      => $filters,
        ]);
    }

    // ── Profit & Loss ───────────────────────────────────────────────────────

    public function profitAndLoss(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['date_from', 'date_to', 'journal_id']);
        $data    = $this->reports->profitAndLoss($filters);

        return view('accounting.reports.profit-and-loss', array_merge($data, ['filters' => $filters]));
    }

    // ── Balance Sheet ───────────────────────────────────────────────────────

    public function balanceSheet(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['date_to']);
        $data    = $this->reports->balanceSheet($filters);

        return view('accounting.reports.balance-sheet', array_merge($data, ['filters' => $filters]));
    }

    // ── Cash Flow ───────────────────────────────────────────────────────────

    public function cashFlow(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['date_from', 'date_to', 'journal_id']);
        $data    = $this->reports->cashFlow($filters);

        return view('accounting.reports.cash-flow', array_merge($data, ['filters' => $filters]));
    }

    // ── Tax Report ──────────────────────────────────────────────────────────

    public function taxReport(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['date_from', 'date_to', 'tax_use']);
        $rows    = $this->reports->taxReport($filters);

        return view('accounting.reports.tax-report', [
            'rows'    => $rows,
            'filters' => $filters,
        ]);
    }

    // ── Partner Ledger ──────────────────────────────────────────────────────

    public function partnerLedger(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, [
            'date_from', 'date_to', 'partner_id', 'account_id', 'partner_scope',
        ]);

        $scope = $filters['partner_scope'] ?? 'ar_ap';
        if ($scope === 'receivable')      $filters['receivable_only'] = true;
        elseif ($scope === 'payable')     $filters['payable_only']    = true;
        elseif ($scope === 'ar_ap')       $filters['ar_ap_only']      = true;
        // 'all' = no scope restriction

        $rows = $this->reports->partnerLedger($filters);

        return view('accounting.reports.partner-ledger', [
            'rows'    => $rows,
            'filters' => $filters,
        ]);
    }

    // ── Aged Receivable / Payable ──────────────────────────────────────────

    public function agedReceivable(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['as_of', 'partner_id']);
        $rows    = $this->reports->agedReport($filters['as_of'], 'out_invoice', $filters['partner_id'] ?? null);

        return view('accounting.reports.aged-receivable', [
            'rows'    => $rows,
            'filters' => $filters,
        ]);
    }

    public function agedPayable(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['as_of', 'partner_id']);
        $rows    = $this->reports->agedReport($filters['as_of'], 'in_invoice', $filters['partner_id'] ?? null);

        return view('accounting.reports.aged-payable', [
            'rows'    => $rows,
            'filters' => $filters,
        ]);
    }

    // ── Journal Audit ───────────────────────────────────────────────────────

    public function journalAudit(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, [
            'date_from', 'date_to', 'journal_id', 'partner_id', 'state', 'move_type',
        ]);
        $moves = $this->reports->journalAudit($filters)->paginate(100)->withQueryString();

        return view('accounting.reports.journal-audit', [
            'moves'   => $moves,
            'filters' => $filters,
        ]);
    }

    // ── Bank Reconciliation ─────────────────────────────────────────────────

    public function bankReconciliation(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['date_from', 'date_to', 'journal_id']);
        $data    = $this->reports->bankReconciliation($filters);

        $lines = collect();
        if ($data['lines_query']) {
            $lines = $data['lines_query']->paginate(100)->withQueryString();
        }

        return view('accounting.reports.bank-reconciliation', [
            'bankJournals' => $data['bank_journals'],
            'lines'        => $lines,
            'filters'      => $filters,
        ]);
    }

    // ── Executive Summary ───────────────────────────────────────────────────

    public function executiveSummary(Request $request)
    {
        abort_unless(auth()->user()->hasPermission('accounting.read'), 403);

        $filters = $this->filters($request, ['date_from', 'date_to']);
        $data    = $this->reports->executiveSummary($filters);

        return view('accounting.reports.executive-summary', array_merge($data, ['filters' => $filters]));
    }

    // ── Shared filter normalization ─────────────────────────────────────────

    /**
     * Read and validate report filters from the request. Returns a normalised
     * array. Unknown keys are dropped. Date strings that don't parse are
     * dropped. Scoped IDs that don't belong to the active companies are
     * dropped so users can't filter on out-of-scope partner/journal/account
     * via query manipulation.
     */
    private function filters(Request $request, array $keys): array
    {
        $out = [];

        // Date-range preset overrides date_from/date_to when set.
        $preset = $request->query('preset');
        if (in_array('date_from', $keys, true) || in_array('date_to', $keys, true)) {
            if ($preset && $resolved = $this->reports->resolvePreset($preset)) {
                $out['date_from'] = $resolved[0];
                $out['date_to']   = $resolved[1];
                $out['preset']    = $preset;
            } else {
                $range = $this->reports->parseDateRange($request->query('date_from'), $request->query('date_to'));
                if (in_array('date_from', $keys, true)) $out['date_from'] = $range['date_from'];
                if (in_array('date_to',   $keys, true)) $out['date_to']   = $range['date_to'];
            }
        }

        if (in_array('as_of', $keys, true)) {
            $out['as_of'] = $this->reports->parseDate($request->query('as_of')) ?? now()->toDateString();
        }

        if (in_array('account_id', $keys, true)) {
            $out['account_id'] = $this->reports->validateScopedId($request->query('account_id'), 'accounts');
        }
        if (in_array('journal_id', $keys, true)) {
            $out['journal_id'] = $this->reports->validateScopedId($request->query('journal_id'), 'account_journals');
        }
        if (in_array('partner_id', $keys, true)) {
            // Contacts can be global (company_id NULL) so we don't strictly
            // scope by company here — the report's own company gate prevents
            // data leak. We do still validate as integer.
            $raw = $request->query('partner_id');
            $out['partner_id'] = ($raw && ctype_digit((string) $raw)) ? (int) $raw : null;
        }

        if (in_array('state', $keys, true)) {
            $state = $request->query('state');
            $out['state'] = in_array($state, ['draft', 'posted', 'cancelled'], true) ? $state : null;
        }

        if (in_array('move_type', $keys, true)) {
            $moveType = $request->query('move_type');
            $out['move_type'] = in_array($moveType, ['entry', 'out_invoice', 'in_invoice', 'out_refund', 'in_refund'], true) ? $moveType : null;
        }

        if (in_array('account_type', $keys, true)) {
            $type = $request->query('account_type');
            $valid = array_merge(
                AccountingReportService::INCOME_TYPES,
                AccountingReportService::EXPENSE_TYPES,
                AccountingReportService::ASSET_TYPES,
                AccountingReportService::LIAB_TYPES,
                AccountingReportService::EQUITY_TYPES,
            );
            $out['account_type'] = in_array($type, $valid, true) ? $type : null;
        }

        if (in_array('tax_use', $keys, true)) {
            $use = $request->query('tax_use');
            $out['tax_use'] = in_array($use, ['sale', 'purchase', 'none'], true) ? $use : null;
        }

        if (in_array('partner_scope', $keys, true)) {
            $scope = $request->query('partner_scope', 'ar_ap');
            $out['partner_scope'] = in_array($scope, ['ar_ap', 'receivable', 'payable', 'all'], true) ? $scope : 'ar_ap';
        }

        return $out;
    }
}
