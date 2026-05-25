<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Services\Accounting\AccountingReportExportService;
use App\Services\Accounting\AccountingReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Exports every accounting report. One public action (`__invoke`) dispatches
 * by `$report` to a per-report buildXxx() that returns the rows, columns,
 * title, and human meta lines. Authorization is uniform: every export
 * requires `accounting.export`. Filter parsing reuses the same parseDate /
 * validateScopedId helpers as the report controller so a user can't pass an
 * out-of-scope filter on the export endpoint either.
 */
class AccountingReportExportController extends Controller
{
    private const REPORTS = [
        'general-ledger', 'trial-balance', 'profit-and-loss', 'balance-sheet',
        'cash-flow', 'tax-report', 'partner-ledger', 'aged-receivable',
        'aged-payable', 'journal-audit', 'bank-reconciliation', 'executive-summary',
    ];

    public function __construct(
        private readonly AccountingReportService $reports,
        private readonly AccountingReportExportService $exporter,
    ) {}

    public function __invoke(Request $request, string $report)
    {
        abort_unless(auth()->user()->hasPermission('accounting.export'), 403);
        abort_unless(in_array($report, self::REPORTS, true), 404);

        $data = $request->validate([
            'format' => ['required', Rule::in(['xlsx', 'csv', 'pdf'])],
        ]);

        $method  = 'build' . str_replace(' ', '', ucwords(str_replace('-', ' ', $report)));
        $payload = $this->{$method}($request);

        return $this->exporter->download(
            records:  $payload['records'],
            columns:  $payload['columns'],
            format:   $data['format'],
            filename: str_replace('-', '_', $report) . '_' . now()->format('Ymd_His'),
            title:    $payload['title'],
            meta:     $payload['meta']   ?? [],
            totals:   $payload['totals'] ?? [],
        );
    }

    // ── General Ledger ──────────────────────────────────────────────────────

    private function buildGeneralLedger(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'account_id', 'journal_id', 'partner_id']);
        $rows = $this->reports->generalLedger($f)->limit(50000)->get()->map(fn ($line) => [
            'date'    => $line->date?->format('Y-m-d') ?? '',
            'entry'   => $line->move?->display_name ?? '',
            'journal' => $line->journal?->name ?? '',
            'account' => trim(($line->account?->code ?? '') . ' ' . ($line->account?->name ?? '')),
            'partner' => $line->partner?->name ?? '',
            'label'   => $line->name ?? '',
            'debit'   => number_format((float) $line->debit, 2, '.', ''),
            'credit'  => number_format((float) $line->credit, 2, '.', ''),
        ]);

        return [
            'title'   => 'General Ledger',
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'date',    'label' => 'Date'],
                ['key' => 'entry',   'label' => 'Entry'],
                ['key' => 'journal', 'label' => 'Journal'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'partner', 'label' => 'Partner'],
                ['key' => 'label',   'label' => 'Label'],
                ['key' => 'debit',   'label' => 'Debit',  'align' => 'right'],
                ['key' => 'credit',  'label' => 'Credit', 'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'debit',  'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['debit']),  2, '.', '')],
                ['key' => 'credit', 'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['credit']), 2, '.', '')],
            ],
        ];
    }

    // ── Trial Balance ───────────────────────────────────────────────────────

    private function buildTrialBalance(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'account_type', 'journal_id']);
        $rows = $this->reports->trialBalance($f)->map(fn ($row) => [
            'code'    => $row->account_code,
            'account' => $row->account_name,
            'debit'   => number_format((float) $row->total_debit, 2, '.', ''),
            'credit'  => number_format((float) $row->total_credit, 2, '.', ''),
            'balance' => number_format((float) $row->net_balance, 2, '.', ''),
        ]);

        return [
            'title'   => 'Trial Balance',
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'code',    'label' => 'Code'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'debit',   'label' => 'Debit',   'align' => 'right'],
                ['key' => 'credit',  'label' => 'Credit',  'align' => 'right'],
                ['key' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'debit',   'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['debit']),   2, '.', '')],
                ['key' => 'credit',  'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['credit']),  2, '.', '')],
                ['key' => 'balance', 'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['balance']), 2, '.', '')],
            ],
        ];
    }

    // ── Profit & Loss ───────────────────────────────────────────────────────

    private function buildProfitAndLoss(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'journal_id']);
        $data = $this->reports->profitAndLoss($f);

        $records = collect();
        foreach ($data['income'] as $row) {
            $records->push([
                'section' => 'Income',
                'code'    => $row->account_code,
                'account' => $row->account_name,
                'amount'  => number_format(abs((float) $row->net), 2, '.', ''),
            ]);
        }
        $records->push([
            'section' => 'Income', 'code' => '', 'account' => 'Total Income',
            'amount'  => number_format((float) $data['total_income'], 2, '.', ''),
        ]);
        foreach ($data['expense'] as $row) {
            $records->push([
                'section' => 'Expenses',
                'code'    => $row->account_code,
                'account' => $row->account_name,
                'amount'  => number_format(abs((float) $row->net), 2, '.', ''),
            ]);
        }
        $records->push([
            'section' => 'Expenses', 'code' => '', 'account' => 'Total Expenses',
            'amount'  => number_format((float) $data['total_expense'], 2, '.', ''),
        ]);
        $records->push([
            'section' => $data['net_profit'] >= 0 ? 'Net Profit' : 'Net Loss',
            'code'    => '',
            'account' => '',
            'amount'  => number_format(abs((float) $data['net_profit']), 2, '.', ''),
        ]);

        return [
            'title'   => 'Profit and Loss',
            'meta'    => $this->dateMeta($f),
            'records' => $records,
            'columns' => [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'code',    'label' => 'Code'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'amount',  'label' => 'Amount', 'align' => 'right'],
            ],
        ];
    }

    // ── Balance Sheet ───────────────────────────────────────────────────────

    private function buildBalanceSheet(Request $r): array
    {
        $f = $this->filters($r, ['date_to']);
        $data = $this->reports->balanceSheet($f);

        $records = collect();
        $emit = function (string $section, $rows) use ($records) {
            foreach ($rows as $row) {
                $records->push([
                    'section' => $section,
                    'code'    => $row->account_code,
                    'account' => $row->account_name,
                    'amount'  => number_format(abs((float) $row->net), 2, '.', ''),
                ]);
            }
            return $records;
        };
        $emit('Assets',      $data['assets']);
        $records->push(['section' => 'Assets', 'code' => '', 'account' => 'Total Assets', 'amount' => number_format((float) $data['total_assets'], 2, '.', '')]);
        $emit('Liabilities', $data['liabilities']);
        $records->push(['section' => 'Liabilities', 'code' => '', 'account' => 'Total Liabilities', 'amount' => number_format((float) $data['total_liabilities'], 2, '.', '')]);
        $emit('Equity',      $data['equity']);
        $records->push(['section' => 'Equity', 'code' => '', 'account' => 'Current Year Earnings', 'amount' => number_format((float) $data['current_year_earnings'], 2, '.', '')]);
        $records->push(['section' => 'Equity', 'code' => '', 'account' => 'Total Equity', 'amount' => number_format((float) $data['total_equity'], 2, '.', '')]);

        return [
            'title'   => 'Balance Sheet',
            'meta'    => ['As Of' => $f['date_to'] ?? 'today'],
            'records' => $records,
            'columns' => [
                ['key' => 'section', 'label' => 'Section'],
                ['key' => 'code',    'label' => 'Code'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'amount',  'label' => 'Amount', 'align' => 'right'],
            ],
        ];
    }

    // ── Cash Flow ───────────────────────────────────────────────────────────

    private function buildCashFlow(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'journal_id']);
        $data = $this->reports->cashFlow($f);

        $rows = $data['rows']->map(fn ($row) => [
            'code'    => $row->account_code,
            'account' => $row->account_name,
            'inflow'  => number_format((float) $row->total_debit, 2, '.', ''),
            'outflow' => number_format((float) $row->total_credit, 2, '.', ''),
            'net'     => number_format((float) $row->net, 2, '.', ''),
        ]);

        return [
            'title'   => 'Cash Flow Statement',
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'code',    'label' => 'Code'],
                ['key' => 'account', 'label' => 'Account'],
                ['key' => 'inflow',  'label' => 'Inflow',  'align' => 'right'],
                ['key' => 'outflow', 'label' => 'Outflow', 'align' => 'right'],
                ['key' => 'net',     'label' => 'Net',     'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'inflow',  'value' => number_format($data['total_inflow'],  2, '.', '')],
                ['key' => 'outflow', 'value' => number_format($data['total_outflow'], 2, '.', '')],
                ['key' => 'net',     'value' => number_format($data['net_cash_flow'], 2, '.', '')],
            ],
        ];
    }

    // ── Tax Report ──────────────────────────────────────────────────────────

    private function buildTaxReport(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'tax_use']);
        $rows = $this->reports->taxReport($f)->map(fn ($row) => [
            'use'      => ucfirst((string) $row->tax_use),
            'tax'      => $row->tax_name . ' (' . rtrim(rtrim(number_format((float) $row->tax_rate, 2, '.', ''), '0'), '.') . '%)',
            'base'     => number_format((float) $row->total_base, 2, '.', ''),
            'debit'    => number_format((float) $row->total_debit, 2, '.', ''),
            'credit'   => number_format((float) $row->total_credit, 2, '.', ''),
            'net'      => number_format((float) $row->net, 2, '.', ''),
        ]);

        return [
            'title'   => 'Tax Report',
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'use',    'label' => 'Tax Use'],
                ['key' => 'tax',    'label' => 'Tax'],
                ['key' => 'base',   'label' => 'Base',   'align' => 'right'],
                ['key' => 'debit',  'label' => 'Debit',  'align' => 'right'],
                ['key' => 'credit', 'label' => 'Credit', 'align' => 'right'],
                ['key' => 'net',    'label' => 'Net',    'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'base',   'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['base']),   2, '.', '')],
                ['key' => 'debit',  'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['debit']),  2, '.', '')],
                ['key' => 'credit', 'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['credit']), 2, '.', '')],
                ['key' => 'net',    'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['net']),    2, '.', '')],
            ],
        ];
    }

    // ── Partner Ledger ──────────────────────────────────────────────────────

    private function buildPartnerLedger(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'partner_id', 'account_id', 'partner_scope']);
        $scope = $f['partner_scope'] ?? 'ar_ap';
        if ($scope === 'receivable')      $f['receivable_only'] = true;
        elseif ($scope === 'payable')     $f['payable_only']    = true;
        elseif ($scope === 'ar_ap')       $f['ar_ap_only']      = true;

        $rows = $this->reports->partnerLedger($f)->map(fn ($row) => [
            'partner' => $row->partner_name,
            'type'    => ucfirst((string) $row->contact_type),
            'debit'   => number_format((float) $row->total_debit, 2, '.', ''),
            'credit'  => number_format((float) $row->total_credit, 2, '.', ''),
            'balance' => number_format((float) $row->net_balance, 2, '.', ''),
        ]);

        return [
            'title'   => 'Partner Ledger',
            'meta'    => array_merge($this->dateMeta($f), ['Scope' => $this->scopeLabel($scope)]),
            'records' => $rows,
            'columns' => [
                ['key' => 'partner', 'label' => 'Partner'],
                ['key' => 'type',    'label' => 'Type'],
                ['key' => 'debit',   'label' => 'Debit',   'align' => 'right'],
                ['key' => 'credit',  'label' => 'Credit',  'align' => 'right'],
                ['key' => 'balance', 'label' => 'Balance', 'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'debit',   'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['debit']),   2, '.', '')],
                ['key' => 'credit',  'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['credit']),  2, '.', '')],
                ['key' => 'balance', 'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['balance']), 2, '.', '')],
            ],
        ];
    }

    // ── Aged Receivable / Payable ──────────────────────────────────────────

    private function buildAgedReceivable(Request $r): array
    {
        return $this->buildAged($r, 'out_invoice', 'Aged Receivable');
    }

    private function buildAgedPayable(Request $r): array
    {
        return $this->buildAged($r, 'in_invoice', 'Aged Payable');
    }

    private function buildAged(Request $r, string $moveType, string $title): array
    {
        $f = $this->filters($r, ['as_of', 'partner_id']);
        $rows = $this->reports->agedReport($f['as_of'], $moveType, $f['partner_id'] ?? null)
            ->map(fn ($row) => [
                'invoice' => $row->name ?: '(Draft)',
                'partner' => $row->partner_name,
                'invoice_date' => $row->invoice_date instanceof \DateTimeInterface ? $row->invoice_date->format('Y-m-d') : (string) $row->invoice_date,
                'due_date'     => $row->invoice_date_due instanceof \DateTimeInterface ? $row->invoice_date_due->format('Y-m-d') : (string) $row->invoice_date_due,
                'bucket'  => $row->bucket,
                'days'    => $row->days_overdue,
                'residual'=> number_format((float) $row->residual, 2, '.', ''),
            ]);

        return [
            'title'   => $title,
            'meta'    => ['As Of' => $f['as_of']],
            'records' => $rows,
            'columns' => [
                ['key' => 'invoice',      'label' => 'Document'],
                ['key' => 'partner',      'label' => 'Partner'],
                ['key' => 'invoice_date', 'label' => 'Date'],
                ['key' => 'due_date',     'label' => 'Due Date'],
                ['key' => 'bucket',       'label' => 'Bucket'],
                ['key' => 'days',         'label' => 'Days',     'align' => 'right'],
                ['key' => 'residual',     'label' => 'Residual', 'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'residual', 'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['residual']), 2, '.', '')],
            ],
        ];
    }

    // ── Journal Audit ───────────────────────────────────────────────────────

    private function buildJournalAudit(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'journal_id', 'partner_id', 'state', 'move_type']);
        $rows = $this->reports->journalAudit($f)->limit(50000)->get()->map(fn ($move) => [
            'date'    => $move->date?->format('Y-m-d') ?? '',
            'entry'   => $move->display_name,
            'ref'     => $move->ref ?? '',
            'journal' => $move->journal?->name ?? '',
            'partner' => $move->partner?->name ?? '',
            'state'   => ucfirst($move->state),
            'amount'  => number_format((float) $move->amount_total, 2, '.', ''),
            'currency'=> $move->currency,
        ]);

        return [
            'title'   => 'Journal Audit',
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'date',     'label' => 'Date'],
                ['key' => 'entry',    'label' => 'Entry'],
                ['key' => 'ref',      'label' => 'Reference'],
                ['key' => 'journal',  'label' => 'Journal'],
                ['key' => 'partner',  'label' => 'Partner'],
                ['key' => 'state',    'label' => 'State'],
                ['key' => 'amount',   'label' => 'Amount',   'align' => 'right'],
                ['key' => 'currency', 'label' => 'Currency'],
            ],
        ];
    }

    // ── Bank Reconciliation ─────────────────────────────────────────────────

    private function buildBankReconciliation(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to', 'journal_id']);
        abort_unless(!empty($f['journal_id']), 422, 'journal_id is required for this export');

        $data = $this->reports->bankReconciliation($f);
        $lines = $data['lines_query'] ? $data['lines_query']->limit(50000)->get() : collect();

        $rows = $lines->map(fn ($line) => [
            'date'    => $line->date?->format('Y-m-d') ?? '',
            'entry'   => $line->move?->display_name ?? '',
            'partner' => $line->partner?->name ?? '',
            'label'   => $line->name ?? '',
            'debit'   => number_format((float) $line->debit, 2, '.', ''),
            'credit'  => number_format((float) $line->credit, 2, '.', ''),
        ]);

        return [
            'title'   => 'Bank Reconciliation',
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'date',    'label' => 'Date'],
                ['key' => 'entry',   'label' => 'Entry'],
                ['key' => 'partner', 'label' => 'Partner'],
                ['key' => 'label',   'label' => 'Label'],
                ['key' => 'debit',   'label' => 'Debit',  'align' => 'right'],
                ['key' => 'credit',  'label' => 'Credit', 'align' => 'right'],
            ],
            'totals' => [
                ['key' => 'debit',  'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['debit']),  2, '.', '')],
                ['key' => 'credit', 'value' => number_format((float) $rows->sum(fn ($r) => (float) $r['credit']), 2, '.', '')],
            ],
        ];
    }

    // ── Executive Summary ───────────────────────────────────────────────────

    private function buildExecutiveSummary(Request $r): array
    {
        $f = $this->filters($r, ['date_from', 'date_to']);
        $data = $this->reports->executiveSummary($f);

        $records = collect([
            ['metric' => 'Total Income',         'value' => number_format($data['total_income'],  2, '.', '')],
            ['metric' => 'Total Expense',        'value' => number_format($data['total_expense'], 2, '.', '')],
            ['metric' => 'Net Profit / Loss',    'value' => number_format($data['net_profit'],    2, '.', '')],
            ['metric' => 'Total Assets (as of)', 'value' => number_format($data['total_assets'],  2, '.', '')],
            ['metric' => 'Total Liabilities',    'value' => number_format($data['total_liabs'],   2, '.', '')],
            ['metric' => 'Draft Entries',        'value' => (string) $data['draft_count']],
            ['metric' => 'Overdue Documents',    'value' => (string) $data['overdue_count']],
        ]);

        return [
            'title'   => 'Executive Summary',
            'meta'    => $this->dateMeta($f),
            'records' => $records,
            'columns' => [
                ['key' => 'metric', 'label' => 'Metric'],
                ['key' => 'value',  'label' => 'Value', 'align' => 'right'],
            ],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function dateMeta(array $f): array
    {
        $meta = [];
        if (!empty($f['date_from'])) $meta['From'] = $f['date_from'];
        if (!empty($f['date_to']))   $meta['To']   = $f['date_to'];
        return $meta;
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'receivable' => 'Receivables',
            'payable'    => 'Payables',
            'ar_ap'      => 'AR + AP',
            'all'        => 'All accounts',
            default      => $scope,
        };
    }

    /**
     * Shared filter parser — same shape as AccountingReportController::filters(),
     * intentionally duplicated to avoid a Trait that would cross controller
     * boundaries. Any drift between the two would be obvious in a single search.
     */
    private function filters(Request $request, array $keys): array
    {
        $out = [];

        $preset = $request->query('preset');
        if (in_array('date_from', $keys, true) || in_array('date_to', $keys, true)) {
            if ($preset && $resolved = $this->reports->resolvePreset($preset)) {
                $out['date_from'] = $resolved[0];
                $out['date_to']   = $resolved[1];
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
