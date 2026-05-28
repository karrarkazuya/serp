<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingAccountGroup;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountingAccountGroupController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountingAccountGroup::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // forCompanies() is fail-closed (see AccountingAccountGroup::scopeForCompanies).
        $query = AccountingAccountGroup::query()->with(['company', 'parent'])->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountingAccountGroup::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['company', 'parent'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.account-groups.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'name', defaultDirection: 'asc');

        $accountGroups = $query->paginate(40)->withQueryString();

        return view('accounting.account-groups.index', compact('accountGroups'));
    }

    public function show(AccountingAccountGroup $accountGroup)
    {
        $this->authorize('view', $accountGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($accountGroup->company_id, $activeCompanyIds), 403);

        $accountGroup->load(['company', 'parent', 'children', 'creator', 'updater']);

        $allIds = AccountingAccountGroup::query()
            ->forCompanies($activeCompanyIds)
            ->orderBy('name')
            ->pluck('id');
        $currentIndex = $allIds->search($accountGroup->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('accounting.account-groups.show', compact(
            'accountGroup', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create()
    {
        $this->authorize('create', AccountingAccountGroup::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('accounting.account-groups.create', compact('defaultCompanyId'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', AccountingAccountGroup::class);

        $data = $request->validate([
            'company_id'        => ['required', 'exists:companies,id'],
            'parent_id'         => ['nullable', 'exists:accounting_account_groups,id'],
            'name'              => ['required', 'string', 'max:255'],
            'code_prefix_start' => ['nullable', 'string', 'max:50'],
            'code_prefix_end'   => ['nullable', 'string', 'max:50'],
        ]);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($data['company_id'], $activeCompanyIds), 403);

        if (!empty($data['parent_id'])) {
            $parent = AccountingAccountGroup::findOrFail($data['parent_id']);
            abort_unless(in_array($parent->company_id, $activeCompanyIds), 403);
        }

        $group = DB::transaction(fn () => AccountingAccountGroup::create($data));

        return redirect()->route('accounting.account-groups.show', $group)->with('success', 'Account group created.');
    }

    public function edit(AccountingAccountGroup $accountGroup)
    {
        $this->authorize('update', $accountGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($accountGroup->company_id, $activeCompanyIds), 403);

        return view('accounting.account-groups.edit', compact('accountGroup'));
    }

    public function write(Request $request, AccountingAccountGroup $accountGroup)
    {
        $this->authorize('update', $accountGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($accountGroup->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'parent_id'         => ['nullable', 'exists:accounting_account_groups,id'],
            'name'              => ['required', 'string', 'max:255'],
            'code_prefix_start' => ['nullable', 'string', 'max:50'],
            'code_prefix_end'   => ['nullable', 'string', 'max:50'],
        ]);

        if (!empty($data['parent_id'])) {
            $parent = AccountingAccountGroup::findOrFail($data['parent_id']);
            abort_unless(in_array($parent->company_id, $activeCompanyIds), 403);
        }

        DB::transaction(fn () => $accountGroup->update($data));

        return redirect()->route('accounting.account-groups.show', $accountGroup)->with('success', 'Account group updated.');
    }

    public function unlink(Request $request, AccountingAccountGroup $accountGroup)
    {
        $this->authorize('delete', $accountGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($accountGroup->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $accountGroup->delete());

        return redirect()->route('accounting.account-groups.index')->with('success', 'Account group deleted.');
    }

    public function addComment(Request $request, AccountingAccountGroup $accountGroup)
    {
        $this->authorize('comment', $accountGroup);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($accountGroup->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $accountGroup->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
