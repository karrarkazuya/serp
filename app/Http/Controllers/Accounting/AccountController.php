<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreAccountRequest;
use App\Http\Requests\Accounting\UpdateAccountRequest;
use App\Models\Accounting\Account;
use App\Services\Accounting\AccountingService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Account::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $view = $request->query('view', 'list');

        // forCompanies() is fail-closed (see Account::scopeForCompanies).

        // ── Tree view: no pagination, full hierarchy ──
        if ($view === 'tree') {
            $records = Account::query()
                ->forCompanies($activeCompanyIds)
                ->when($request->query('filter') !== 'all', function ($q) use ($request) {
                    $request->query('filter') === 'archived' ? $q->inactive() : $q->active();
                })
                ->orderBy('code')
                ->limit(2000)
                ->get();

            $treeNodes = $this->buildAccountTree($records);
            $total     = $records->count();

            return view('accounting.accounts.index', compact('treeNodes', 'total', 'view'));
        }

        // ── List view (default) ──
        $query = Account::query()->with(['company', 'parent'])->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        if ($type = $request->query('account_type')) {
            $query->where('account_type', $type);
        }

        if ($view === 'list') {
            $groupBy = $request->query('group_by');
            if ($groupBy) {
                $fields = SearchFilters::fieldsFor(Account::class);
                if (isset($fields[$groupBy])) {
                    $records = (clone $query)->with(['company', 'parent'])->orderBy('id')->get();
                    $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                    return view('accounting.accounts.index', compact('groups', 'view'));
                }
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'code', defaultDirection: 'asc');

        $accounts = $query->paginate(40)->withQueryString();

        return view('accounting.accounts.index', compact('accounts', 'view'));
    }

    /**
     * Build the x-tree nodes array from a flat collection of accounts.
     * Uses parent_id to assemble a nested structure.
     */
    private function buildAccountTree(\Illuminate\Support\Collection $records): array
    {
        $map = [];
        foreach ($records as $r) {
            $map[$r->id] = [
                'id'          => $r->id,
                'name'        => $r->code . ' — ' . $r->name,
                'url'         => route('accounting.accounts.show', $r),
                'avatar'      => null,
                'initials'    => mb_substr($r->code, 0, 2),
                'subtitle'    => $r->name_en,
                'meta'        => $r->type_label,
                'badge'       => $r->active ? null : 'Archived',
                'badge_color' => $r->active ? 'gray' : 'orange',
                'children'    => [],
            ];
        }

        $childrenOf = [];
        $roots      = [];
        foreach ($records as $r) {
            if ($r->parent_id && isset($map[$r->parent_id])) {
                $childrenOf[$r->parent_id][] = $r->id;
            } else {
                $roots[] = $r->id;
            }
        }

        $build = function (int $id) use (&$build, &$map, $childrenOf): array {
            $node = $map[$id];
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $node['children'][] = $build($childId);
            }
            return $node;
        };

        return array_map($build, $roots);
    }

    public function show(Account $account)
    {
        $this->authorize('view', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        $account->load(['company', 'parent', 'children', 'creator', 'updater']);

        $balance = $this->accounting->getAccountBalance($account);

        $allIds = Account::active()
            ->forCompanies($activeCompanyIds)
            ->orderBy('code')
            ->pluck('id');
        $currentIndex = $allIds->search($account->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal = $allIds->count();

        return view('accounting.accounts.show', compact(
            'account', 'balance', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Account::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $accountTypes = Account::TYPES;

        return view('accounting.accounts.create', compact('defaultCompanyId', 'accountTypes'));
    }

    public function store(StoreAccountRequest $request)
    {
        $data = $request->validated();

        // Hierarchy cycle guard (Rule 11): the parent_id rule only checks
        // existence + same-company. It can't detect A → B → A loops at create
        // time when the new account isn't in the DB yet, but it CAN catch a
        // user pointing the new account at a parent chain that already loops
        // (corrupted data). The same bounded walk is the actual guard at
        // update time below; running it here keeps the two paths symmetric.
        if (!empty($data['parent_id'])
            && $this->parentChainHasCycle((int) $data['parent_id'])
        ) {
            return back()->withInput()->with('error', __('accounting.parent_cycle'));
        }

        $account = DB::transaction(fn () => $this->accounting->createAccount($data));

        return redirect()
            ->route('accounting.accounts.show', $account)
            ->with('success', __('accounting.created'));
    }

    public function edit(Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        $accountTypes = Account::TYPES;

        return view('accounting.accounts.edit', compact('account', 'accountTypes'));
    }

    public function write(UpdateAccountRequest $request, Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        $data = $request->validated();

        // Hierarchy cycle guard (Rule 11): block self-parenting and walks
        // where the proposed parent eventually points back at $account.
        // Without this, "Account A → parent = B" + "Account B → parent = A"
        // would silently create an infinite tree-walk; buildAccountTree's
        // recursion blows the stack and reports time out.
        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $parentId = (int) $data['parent_id'];
            if ($parentId === $account->id
                || $this->isAccountDescendantOf($parentId, $account->id)
                || $this->parentChainHasCycle($parentId)
            ) {
                return back()->withInput()->with('error', __('accounting.parent_cycle'));
            }
        }

        DB::transaction(fn () => $this->accounting->updateAccount($account, $data));

        return redirect()
            ->route('accounting.accounts.show', $account)
            ->with('success', __('accounting.updated'));
    }

    public function archive(Request $_request, Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->archiveAccount($account));
        return redirect()->route('accounting.accounts.index')->with('success', __('accounting.archived'));
    }

    public function unarchive(Request $_request, Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->unarchiveAccount($account));
        return redirect()->route('accounting.accounts.show', $account)->with('success', __('accounting.restored'));
    }

    public function unlink(Request $_request, Account $account)
    {
        $this->authorize('delete', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        try {
            DB::transaction(fn () => $this->accounting->deleteAccount($account));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.accounts.index')->with('success', __('accounting.deleted'));
    }

    public function addComment(Request $request, Account $account)
    {
        $this->authorize('comment', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $account->logComment($request->body));

        return back()->with('success', __('accounting.comment_added'));
    }

    /**
     * Bounded walk: is $candidateId reachable from $startId by following
     * parent_id 64 hops? 64 levels is well past any sane Chart of Accounts
     * (Iraqi UAS tops out at ~5), but the cap is deliberately high so the
     * walk also escapes pre-existing corrupted data instead of hanging.
     *
     * Returns true when $candidateId appears anywhere in $startId's parent
     * chain — i.e. setting $startId as a child of $candidateId would close
     * the loop.
     */
    private function isAccountDescendantOf(int $startId, int $candidateId): bool
    {
        $cursor = $startId;
        for ($i = 0; $i < 64 && $cursor; $i++) {
            if ($cursor === $candidateId) {
                return true;
            }
            $cursor = (int) (Account::whereKey($cursor)->value('parent_id') ?? 0);
        }
        return false;
    }

    /**
     * Detect pre-existing corruption: walking up parent_id for 64 hops
     * should always terminate. If it doesn't, the chart is already cyclic
     * and we should refuse to grow it further.
     */
    private function parentChainHasCycle(int $startId): bool
    {
        $seen = [];
        $cursor = $startId;
        for ($i = 0; $i < 64 && $cursor; $i++) {
            if (isset($seen[$cursor])) {
                return true;
            }
            $seen[$cursor] = true;
            $cursor = (int) (Account::whereKey($cursor)->value('parent_id') ?? 0);
        }
        return false;
    }
}
