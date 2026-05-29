<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingPaymentTerm;
use App\Models\Accounting\AccountingPaymentTermLine;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingPaymentTermController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountingPaymentTerm::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // forCompanies() is fail-closed (see AccountingPaymentTerm::scopeForCompanies).
        $query = AccountingPaymentTerm::query()->with('company')->withCount('lines')->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountingPaymentTerm::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->withCount('lines')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.payment-terms.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'name', defaultDirection: 'asc');

        $terms = $query->paginate(40)->withQueryString();

        return view('accounting.payment-terms.index', compact('terms'));
    }

    public function show(AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('view', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        $paymentTerm->load(['company', 'lines', 'creator', 'updater']);

        $allIds = AccountingPaymentTerm::query()
            ->forCompanies($activeCompanyIds)
            ->orderBy('name')
            ->pluck('id');
        $currentIndex = $allIds->search($paymentTerm->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('accounting.payment-terms.show', compact(
            'paymentTerm', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', AccountingPaymentTerm::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('accounting.payment-terms.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', AccountingPaymentTerm::class);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name'       => ['required', 'string', 'max:255'],
            'note'       => ['nullable', 'string'],
            'active'     => ['nullable', 'boolean'],
            'lines'                   => ['nullable', 'array'],
            'lines.*.value_type'      => ['required_with:lines', 'in:percent,fixed,balance'],
            'lines.*.value'           => ['nullable', 'numeric', 'min:0'],
            'lines.*.days'            => ['nullable', 'integer', 'min:0'],
        ]);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);

        $data['active'] = (bool) ($data['active'] ?? true);
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $this->assertPaymentTermLinesValid($lines);

        $term = DB::transaction(function () use ($data, $lines) {
            $term = AccountingPaymentTerm::create($data);
            foreach ($lines as $seq => $line) {
                $term->lines()->create([
                    'value_type' => $line['value_type'],
                    'value'      => $line['value'] ?? 0,
                    'days'       => $line['days'] ?? 0,
                    'sequence'   => $seq,
                ]);
            }
            return $term;
        });

        return redirect()->route('accounting.payment-terms.show', $term)->with('success', __('accounting.created'));
    }

    public function edit(AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        $paymentTerm->load('lines');

        return view('accounting.payment-terms.edit', compact('paymentTerm'));
    }

    public function write(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'name'                    => ['required', 'string', 'max:255'],
            'note'                    => ['nullable', 'string'],
            'active'                  => ['nullable', 'boolean'],
            'lines'                   => ['nullable', 'array'],
            'lines.*.value_type'      => ['required_with:lines', 'in:percent,fixed,balance'],
            'lines.*.value'           => ['nullable', 'numeric', 'min:0'],
            'lines.*.days'            => ['nullable', 'integer', 'min:0'],
        ]);

        $data['active'] = array_key_exists('active', $data) ? (bool) $data['active'] : $paymentTerm->active;
        $lines = $data['lines'] ?? [];
        unset($data['lines']);

        $this->assertPaymentTermLinesValid($lines);

        DB::transaction(function () use ($paymentTerm, $data, $lines) {
            $paymentTerm->update($data);
            $paymentTerm->lines()->delete();
            foreach ($lines as $seq => $line) {
                $paymentTerm->lines()->create([
                    'value_type' => $line['value_type'],
                    'value'      => $line['value'] ?? 0,
                    'days'       => $line['days'] ?? 0,
                    'sequence'   => $seq,
                ]);
            }
        });

        return redirect()->route('accounting.payment-terms.show', $paymentTerm)->with('success', __('accounting.updated'));
    }

    public function archive(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $paymentTerm->update(['active' => false]));

        return redirect()->route('accounting.payment-terms.show', $paymentTerm)->with('success', __('accounting.archived'));
    }

    public function unarchive(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $paymentTerm->update(['active' => true]));

        return redirect()->route('accounting.payment-terms.show', $paymentTerm)->with('success', __('accounting.restored'));
    }

    public function unlink(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('delete', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $paymentTerm->delete());

        return redirect()->route('accounting.payment-terms.index')->with('success', __('accounting.deleted'));
    }

    public function addComment(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('comment', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $paymentTerm->logComment($request->body));

        return back()->with('success', __('accounting.comment_added'));
    }

    /**
     * D2 (Odoo parity): a payment term must have exactly one `balance` line
     * and its `percent` lines must sum to <= 100. Without these checks the
     * term silently produces invoices whose AR is under- or over-allocated:
     *   - "30% in 0 days" with no balance line → invoice's receivable only
     *     accounts for 30% of the total
     *   - "60% + 50% + balance" → over-allocates to 110%+balance
     *   - Two `balance` lines → ambiguous, Odoo rejects
     * Empty $lines (no schedule) is valid — interpreted as full balance at
     * invoice date, matching Odoo's "Immediate Payment" term.
     */
    private function assertPaymentTermLinesValid(array $lines): void
    {
        if (empty($lines)) {
            return;
        }

        $balanceCount = 0;
        $percentSum   = 0.0;

        foreach ($lines as $i => $line) {
            $type  = $line['value_type'] ?? null;
            $value = (float) ($line['value'] ?? 0);
            if ($type === 'balance') {
                $balanceCount++;
            } elseif ($type === 'percent') {
                if ($value <= 0 || $value > 100) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "lines.{$i}.value" => __('accounting.pt_percent_range'),
                    ]);
                }
                $percentSum += $value;
            } elseif ($type === 'fixed' && $value <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    "lines.{$i}.value" => __('accounting.pt_fixed_positive'),
                ]);
            }
        }

        if ($balanceCount === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => __('accounting.pt_balance_required'),
            ]);
        }
        if ($balanceCount > 1) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => __('accounting.pt_balance_one_only'),
            ]);
        }
        if (round($percentSum, 4) > 100.0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'lines' => __('accounting.pt_percent_over_100', ['pct' => number_format($percentSum, 2)]),
            ]);
        }
    }
}
