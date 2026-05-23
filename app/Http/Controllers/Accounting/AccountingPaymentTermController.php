<?php

namespace App\Http\Controllers\Accounting;

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

        $query = AccountingPaymentTerm::query()->with('company');

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
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
            ->when(!empty($activeCompanyIds), fn ($q) => $q->forCompanies($activeCompanyIds))
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

        return redirect()->route('accounting.payment-terms.show', $term)->with('success', 'Payment term created.');
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

        return redirect()->route('accounting.payment-terms.show', $paymentTerm)->with('success', 'Payment term updated.');
    }

    public function archive(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $paymentTerm->update(['active' => false]));

        return redirect()->route('accounting.payment-terms.show', $paymentTerm)->with('success', 'Payment term archived.');
    }

    public function unarchive(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('update', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $paymentTerm->update(['active' => true]));

        return redirect()->route('accounting.payment-terms.show', $paymentTerm)->with('success', 'Payment term restored.');
    }

    public function unlink(Request $request, AccountingPaymentTerm $paymentTerm)
    {
        $this->authorize('delete', $paymentTerm);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($paymentTerm->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $paymentTerm->delete());

        return redirect()->route('accounting.payment-terms.index')->with('success', 'Payment term deleted.');
    }
}
