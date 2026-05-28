<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingTaxGroup;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingTaxGroupController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountingTaxGroup::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // forCompanies() is fail-closed (see AccountingTaxGroup::scopeForCompanies).
        $query = AccountingTaxGroup::query()->with('company')->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountingTaxGroup::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with('company')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.tax-groups.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'sequence', defaultDirection: 'asc');

        $taxGroups = $query->paginate(40)->withQueryString();

        return view('accounting.tax-groups.index', compact('taxGroups'));
    }

    public function show(AccountingTaxGroup $taxGroup)
    {
        $this->authorize('view', $taxGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($taxGroup->company_id, $activeCompanyIds), 403);

        $taxGroup->load(['company', 'creator', 'updater']);

        $allIds = AccountingTaxGroup::query()
            ->forCompanies($activeCompanyIds)
            ->orderBy('sequence')
            ->pluck('id');
        $currentIndex = $allIds->search($taxGroup->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('accounting.tax-groups.show', compact(
            'taxGroup', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create()
    {
        $this->authorize('create', AccountingTaxGroup::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('accounting.tax-groups.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', AccountingTaxGroup::class);

        $data = $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'name'       => ['required', 'string', 'max:255'],
            'sequence'   => ['nullable', 'integer', 'min:0'],
        ]);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);

        $data['sequence'] = $data['sequence'] ?? 0;

        $group = DB::transaction(fn () => AccountingTaxGroup::create($data));

        return redirect()->route('accounting.tax-groups.show', $group)->with('success', 'Tax group created.');
    }

    public function edit(AccountingTaxGroup $taxGroup)
    {
        $this->authorize('update', $taxGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($taxGroup->company_id, $activeCompanyIds), 403);

        return view('accounting.tax-groups.edit', compact('taxGroup'));
    }

    public function write(Request $request, AccountingTaxGroup $taxGroup)
    {
        $this->authorize('update', $taxGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($taxGroup->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'sequence' => ['nullable', 'integer', 'min:0'],
        ]);

        $data['sequence'] = $data['sequence'] ?? 0;

        DB::transaction(fn () => $taxGroup->update($data));

        return redirect()->route('accounting.tax-groups.show', $taxGroup)->with('success', 'Tax group updated.');
    }

    public function unlink(Request $request, AccountingTaxGroup $taxGroup)
    {
        $this->authorize('delete', $taxGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($taxGroup->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $taxGroup->delete());

        return redirect()->route('accounting.tax-groups.index')->with('success', 'Tax group deleted.');
    }

    public function addComment(Request $request, AccountingTaxGroup $taxGroup)
    {
        $this->authorize('comment', $taxGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($taxGroup->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $taxGroup->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
