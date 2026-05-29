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
            'title'   => __('accounting.report_general_ledger'),
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'date',    'label' => __('accounting.col_date')],
                ['key' => 'entry',   'label' => __('accounting.col_entry')],
                ['key' => 'journal', 'label' => __('accounting.col_journal')],
                ['key' => 'account', 'label' => __('accounting.col_account')],
                ['key' => 'partner', 'label' => __('accounting.col_partner')],
                ['key' => 'label',   'label' => __('accounting.col_label')],
                ['key' => 'debit',   'label' => __('accounting.col_debit'),  'align' => 'right'],
                ['key' => 'credit',  'label' => __('accounting.col_credit'), 'align' => 'right'],
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
            'title'   => __('accounting.report_trial_balance'),
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'code',    'label' => __('accounting.col_code')],
                ['key' => 'account', 'label' => __('accounting.col_account')],
                ['key' => 'debit',   'label' => __('accounting.col_debit'),   'align' => 'right'],
                ['key' => 'credit',  'label' => __('accounting.col_credit'),  'align' => 'right'],
                ['key' => 'balance', 'label' => __('accounting.col_balance'), 'align' => 'right'],
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
                'section' => __('accounting.section_income'),
                'code'    => $row->account_code,
                'account' => $row->account_name,
                'amount'  => number_format(abs((float) $row->net), 2, '.', ''),
            ]);
        }
        $records->push([
            'section' => __('accounting.section_income'), 'code' => '', 'account' => __('accounting.total_income'),
            'amount'  => number_format((float) $data['total_income'], 2, '.', ''),
        ]);
        foreach ($data['expense'] as $row) {
            $records->push([
                'section' => __('accounting.section_expenses'),
                'code'    => $row->account_code,
                'account' => $row->account_name,
                'amount'  => number_format(abs((float) $row->net), 2, '.', ''),
            ]);
        }
        $records->push([
            'section' => __('accounting.section_expenses'), 'code' => '', 'account' => __('accounting.total_expenses'),
            'amount'  => number_format((float) $data['total_expense'], 2, '.', ''),
        ]);
        $records->push([
            'section' => $data['net_profit'] >= 0 ? __('accounting.net_profit') : __('accounting.net_loss'),
            'code'    => '',
            'account' => '',
            'amount'  => number_format(abs((float) $data['net_profit']), 2, '.', ''),
        ]);

        return [
            'title'   => __('accounting.report_profit_loss'),
            'meta'    => $this->dateMeta($f),
            'records' => $records,
            'columns' => [
                ['key' => 'section', 'label' => __('accounting.col_section')],
                ['key' => 'code',    'label' => __('accounting.col_code')],
                ['key' => 'account', 'label' => __('accounting.col_account')],
                ['key' => 'amount',  'label' => __('accounting.col_amount'), 'align' => 'right'],
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
        $emit(__('accounting.section_assets'), $data['assets']);
        $records->push(['section' => __('accounting.section_assets'), 'code' => '', 'account' => __('accounting.total_assets'), 'amount' => number_format((float) $data['total_assets'], 2, '.', '')]);
        $emit(__('accounting.section_liabilities'), $data['liabilities']);
        $records->push(['section' => __('accounting.section_liabilities'), 'code' => '', 'account' => __('accounting.total_liabilities'), 'amount' => number_format((float) $data['total_liabilities'], 2, '.', '')]);
        $emit(__('accounting.section_equity'), $data['equity']);
        $records->push(['section' => __('accounting.section_equity'), 'code' => '', 'account' => __('accounting.current_year_earnings'), 'amount' => number_format((float) $data['current_year_earnings'], 2, '.', '')]);
        $records->push(['section' => __('accounting.section_equity'), 'code' => '', 'account' => __('accounting.total_equity'), 'amount' => number_format((float) $data['total_equity'], 2, '.', '')]);

        return [
            'title'   => __('accounting.report_balance_sheet'),
            'meta'    => [__('accounting.meta_as_of') => $f['date_to'] ?? __('accounting.meta_today')],
            'records' => $records,
            'columns' => [
                ['key' => 'section', 'label' => __('accounting.col_section')],
                ['key' => 'code',    'label' => __('accounting.col_code')],
                ['key' => 'account', 'label' => __('accounting.col_account')],
                ['key' => 'amount',  'label' => __('accounting.col_amount'), 'align' => 'right'],
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
            'title'   => __('accounting.report_cash_flow'),
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'code',    'label' => __('accounting.col_code')],
                ['key' => 'account', 'label' => __('accounting.col_account')],
                ['key' => 'inflow',  'label' => __('accounting.col_inflow'),  'align' => 'right'],
                ['key' => 'outflow', 'label' => __('accounting.col_outflow'), 'align' => 'right'],
                ['key' => 'net',     'label' => __('accounting.col_net'),     'align' => 'right'],
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
            'title'   => __('accounting.report_tax'),
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'use',    'label' => __('accounting.col_tax_use')],
                ['key' => 'tax',    'label' => __('accounting.col_tax')],
                ['key' => 'base',   'label' => __('accounting.col_base'),   'align' => 'right'],
                ['key' => 'debit',  'label' => __('accounting.col_debit'),  'align' => 'right'],
                ['key' => 'credit', 'label' => __('accounting.col_credit'), 'align' => 'right'],
                ['key' => 'net',    'label' => __('accounting.col_net'),    'align' => 'right'],
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
            'title'   => __('accounting.report_partner_ledger'),
            'meta'    => array_merge($this->dateMeta($f), [__('accounting.meta_scope') => $this->scopeLabel($scope)]),
            'records' => $rows,
            'columns' => [
                ['key' => 'partner', 'label' => __('accounting.col_partner')],
                ['key' => 'type',    'label' => __('accounting.col_type')],
                ['key' => 'debit',   'label' => __('accounting.col_debit'),   'align' => 'right'],
                ['key' => 'credit',  'label' => __('accounting.col_credit'),  'align' => 'right'],
                ['key' => 'balance', 'label' => __('accounting.col_balance'), 'align' => 'right'],
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
        return $this->buildAged($r, 'out_invoice', __('accounting.report_aged_receivable'));
    }

    private function buildAgedPayable(Request $r): array
    {
        return $this->buildAged($r, 'in_invoice', __('accounting.report_aged_payable'));
    }

    private function buildAged(Request $r, string $moveType, string $title): array
    {
        $f = $this->filters($r, ['as_of', 'partner_id']);
        $rows = $this->reports->agedReport($f['as_of'], $moveType, $f['partner_id'] ?? null)
            ->map(fn ($row) => [
                'invoice' => $row->name ?: __('accounting.aged_draft_fallback'),
                'partner' => $row->partner_name,
                'invoice_date' => $row->invoice_date instanceof \DateTimeInterface ? $row->invoice_date->format('Y-m-d') : (string) $row->invoice_date,
                'due_date'     => $row->invoice_date_due instanceof \DateTimeInterface ? $row->invoice_date_due->format('Y-m-d') : (string) $row->invoice_date_due,
                'bucket'  => $row->bucket,
                'days'    => $row->days_overdue,
                'residual'=> number_format((float) $row->residual, 2, '.', ''),
            ]);

        return [
            'title'   => $title,
            'meta'    => [__('accounting.meta_as_of') => $f['as_of']],
            'records' => $rows,
            'columns' => [
                ['key' => 'invoice',      'label' => __('accounting.col_document')],
                ['key' => 'partner',      'label' => __('accounting.col_partner')],
                ['key' => 'invoice_date', 'label' => __('accounting.col_date')],
                ['key' => 'due_date',     'label' => __('accounting.col_due_date')],
                ['key' => 'bucket',       'label' => __('accounting.col_bucket')],
                ['key' => 'days',         'label' => __('accounting.col_days'),     'align' => 'right'],
                ['key' => 'residual',     'label' => __('accounting.col_residual'), 'align' => 'right'],
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
            'title'   => __('accounting.report_journal_audit'),
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'date',     'label' => __('accounting.col_date')],
                ['key' => 'entry',    'label' => __('accounting.col_entry')],
                ['key' => 'ref',      'label' => __('accounting.col_reference')],
                ['key' => 'journal',  'label' => __('accounting.col_journal')],
                ['key' => 'partner',  'label' => __('accounting.col_partner')],
                ['key' => 'state',    'label' => __('accounting.col_state')],
                ['key' => 'amount',   'label' => __('accounting.col_amount'),   'align' => 'right'],
                ['key' => 'currency', 'label' => __('accounting.col_currency')],
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
            'title'   => __('accounting.report_bank_recon'),
            'meta'    => $this->dateMeta($f),
            'records' => $rows,
            'columns' => [
                ['key' => 'date',    'label' => __('accounting.col_date')],
                ['key' => 'entry',   'label' => __('accounting.col_entry')],
                ['key' => 'partner', 'label' => __('accounting.col_partner')],
                ['key' => 'label',   'label' => __('accounting.col_label')],
                ['key' => 'debit',   'label' => __('accounting.col_debit'),  'align' => 'right'],
                ['key' => 'credit',  'label' => __('accounting.col_credit'), 'align' => 'right'],
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
            ['metric' => __('accounting.metric_total_income'),    'value' => number_format($data['total_income'],  2, '.', '')],
            ['metric' => __('accounting.metric_total_expense'),   'value' => number_format($data['total_expense'], 2, '.', '')],
            ['metric' => __('accounting.metric_net_profit_loss'), 'value' => number_format($data['net_profit'],    2, '.', '')],
            ['metric' => __('accounting.metric_total_assets'),    'value' => number_format($data['total_assets'],  2, '.', '')],
            ['metric' => __('accounting.metric_total_liabs'),     'value' => number_format($data['total_liabs'],   2, '.', '')],
            ['metric' => __('accounting.metric_draft_entries'),   'value' => (string) $data['draft_count']],
            ['metric' => __('accounting.metric_overdue_docs'),    'value' => (string) $data['overdue_count']],
        ]);

        return [
            'title'   => __('accounting.report_executive'),
            'meta'    => $this->dateMeta($f),
            'records' => $records,
            'columns' => [
                ['key' => 'metric', 'label' => __('accounting.col_metric')],
                ['key' => 'value',  'label' => __('accounting.col_value'), 'align' => 'right'],
            ],
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function dateMeta(array $f): array
    {
        $meta = [];
        if (!empty($f['date_from'])) $meta[__('accounting.meta_from')] = $f['date_from'];
        if (!empty($f['date_to']))   $meta[__('accounting.meta_to')]   = $f['date_to'];
        return $meta;
    }

    private function scopeLabel(string $scope): string
    {
        return match ($scope) {
            'receivable' => __('accounting.scope_receivable'),
            'payable'    => __('accounting.scope_payable'),
            'ar_ap'      => __('accounting.scope_ar_ap'),
            'all'        => __('accounting.scope_all_accounts'),
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
