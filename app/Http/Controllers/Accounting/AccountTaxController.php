<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreTaxRequest;
use App\Http\Requests\Accounting\UpdateTaxRequest;
use App\Models\Accounting\AccountTax;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountTaxController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountTax::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // forCompanies() is fail-closed (see AccountTax::scopeForCompanies).
        $query = AccountTax::query()->with(['account', 'company'])->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        if ($use = $request->query('type_tax_use')) {
            $query->where('type_tax_use', $use);
        }

        $amountTypes = AccountTax::AMOUNT_TYPES;
        $typeTaxUse  = AccountTax::TYPE_TAX_USE;

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountTax::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['account', 'company'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.taxes.index', compact('groups', 'amountTypes', 'typeTaxUse'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'name', defaultDirection: 'asc');

        $taxes = $query->paginate(40)->withQueryString();

        return view('accounting.taxes.index', compact('taxes', 'amountTypes', 'typeTaxUse'));
    }

    public function show(AccountTax $tax)
    {
        $this->authorize('view', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        $tax->load(['account', 'company', 'creator', 'updater']);

        $allIds = AccountTax::query()
            ->forCompanies($activeCompanyIds)
            ->orderBy('name')
            ->pluck('id');
        $currentIndex = $allIds->search($tax->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        $amountTypes = AccountTax::AMOUNT_TYPES;
        $typeTaxUse  = AccountTax::TYPE_TAX_USE;

        return view('accounting.taxes.show', compact(
            'tax', 'prevId', 'nextId', 'recordPosition', 'recordTotal', 'amountTypes', 'typeTaxUse'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', AccountTax::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $amountTypes = AccountTax::AMOUNT_TYPES;
        $typeTaxUse  = AccountTax::TYPE_TAX_USE;

        return view('accounting.taxes.create', compact('defaultCompanyId', 'amountTypes', 'typeTaxUse'));
    }

    public function store(StoreTaxRequest $request)
    {
        $data = $request->validated();
        $data['include_base_amount'] = (bool) ($data['include_base_amount'] ?? false);
        $data['active']              = (bool) ($data['active'] ?? true);

        $tax = DB::transaction(fn () => AccountTax::create($data));

        return redirect()->route('accounting.taxes.show', $tax)->with('success', 'Tax created.');
    }

    public function edit(AccountTax $tax)
    {
        $this->authorize('update', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        $tax->load(['account']);

        $amountTypes = AccountTax::AMOUNT_TYPES;
        $typeTaxUse  = AccountTax::TYPE_TAX_USE;

        return view('accounting.taxes.edit', compact('tax', 'amountTypes', 'typeTaxUse'));
    }

    public function write(UpdateTaxRequest $request, AccountTax $tax)
    {
        $this->authorize('update', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        $data = $request->validated();
        $data['include_base_amount'] = (bool) ($data['include_base_amount'] ?? false);
        $data['active']              = (bool) ($data['active'] ?? true);

        DB::transaction(fn () => $tax->update($data));

        return redirect()->route('accounting.taxes.show', $tax)->with('success', 'Tax updated.');
    }

    public function archive(Request $request, AccountTax $tax)
    {
        $this->authorize('update', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $tax->update(['active' => false]));

        return redirect()->route('accounting.taxes.show', $tax)->with('success', 'Tax archived.');
    }

    public function unarchive(Request $request, AccountTax $tax)
    {
        $this->authorize('update', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $tax->update(['active' => true]));

        return redirect()->route('accounting.taxes.show', $tax)->with('success', 'Tax restored.');
    }

    public function unlink(Request $request, AccountTax $tax)
    {
        $this->authorize('delete', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $tax->delete());

        return redirect()->route('accounting.taxes.index')->with('success', 'Tax deleted.');
    }

    public function addComment(Request $request, AccountTax $tax)
    {
        $this->authorize('comment', $tax);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($tax->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $tax->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
