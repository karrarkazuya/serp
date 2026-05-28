<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreCurrencyRateRequest;
use App\Http\Requests\Accounting\UpdateCurrencyRateRequest;
use App\Models\Accounting\CurrencyRate;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurrencyRateController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', CurrencyRate::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // forCompanies() is fail-closed: an empty $activeCompanyIds array
        // returns no rows (whereIn([]) compiles to "WHERE 0 = 1"). See
        // CurrencyRate::scopeForCompanies.
        $query = CurrencyRate::query()->with('company')->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($currency = $request->query('currency')) {
            $query->where('currency', $currency);
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(CurrencyRate::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.currencies.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'date', defaultDirection: 'desc');

        $rates = $query->paginate(40)->withQueryString();

        return view('accounting.currencies.index', compact('rates'));
    }

    public function show(CurrencyRate $currencyRate)
    {
        $this->authorize('view', $currencyRate);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($currencyRate->company_id, $activeCompanyIds), 403);

        $currencyRate->load(['company', 'creator', 'updater']);

        return view('accounting.currencies.show', compact('currencyRate'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', CurrencyRate::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('accounting.currencies.create', compact('defaultCompanyId'));
    }

    public function store(StoreCurrencyRateRequest $request)
    {
        $data = $request->validated();
        $data['active'] = (bool) ($data['active'] ?? true);

        $rate = DB::transaction(fn () => CurrencyRate::create($data));

        return redirect()->route('accounting.currencies.show', $rate)->with('success', 'Exchange rate saved.');
    }

    public function edit(CurrencyRate $currencyRate)
    {
        $this->authorize('update', $currencyRate);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($currencyRate->company_id, $activeCompanyIds), 403);

        return view('accounting.currencies.edit', compact('currencyRate'));
    }

    public function write(UpdateCurrencyRateRequest $request, CurrencyRate $currencyRate)
    {
        $this->authorize('update', $currencyRate);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($currencyRate->company_id, $activeCompanyIds), 403);

        $data = $request->validated();
        $data['active'] = (bool) ($data['active'] ?? true);

        DB::transaction(fn () => $currencyRate->update($data));

        return redirect()->route('accounting.currencies.show', $currencyRate)->with('success', 'Exchange rate updated.');
    }

    public function unlink(Request $request, CurrencyRate $currencyRate)
    {
        $this->authorize('delete', $currencyRate);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($currencyRate->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $currencyRate->delete());

        return redirect()->route('accounting.currencies.index')->with('success', 'Exchange rate deleted.');
    }

    public function addComment(Request $request, CurrencyRate $currencyRate)
    {
        $this->authorize('comment', $currencyRate);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($currencyRate->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $currencyRate->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
