<?php

namespace App\Http\Controllers\Accounting;

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

        // ── Tree view: no pagination, full hierarchy ──
        if ($view === 'tree') {
            $records = Account::query()
                ->when(!empty($activeCompanyIds), fn ($q) => $q->forCompanies($activeCompanyIds))
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
        $query = Account::query()->with(['company', 'parent']);

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

        if ($type = $request->query('account_type')) {
            $query->where('account_type', $type);
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
            ->when(!empty($activeCompanyIds), fn ($q) => $q->forCompanies($activeCompanyIds))
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

        return view('accounting.accounts.create', compact('defaultCompanyId'));
    }

    public function store(StoreAccountRequest $request)
    {
        $account = DB::transaction(fn () => $this->accounting->createAccount($request->validated()));

        return redirect()
            ->route('accounting.accounts.show', $account)
            ->with('success', 'Account created.');
    }

    public function edit(Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        return view('accounting.accounts.edit', compact('account'));
    }

    public function write(UpdateAccountRequest $request, Account $account)
    {
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->updateAccount($account, $request->validated()));

        return redirect()
            ->route('accounting.accounts.show', $account)
            ->with('success', 'Account updated.');
    }

    public function archive(Request $_request, Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->archiveAccount($account));
        return redirect()->route('accounting.accounts.index')->with('success', 'Account archived.');
    }

    public function unarchive(Request $_request, Account $account)
    {
        $this->authorize('update', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->unarchiveAccount($account));
        return redirect()->route('accounting.accounts.show', $account)->with('success', 'Account restored.');
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

        return redirect()->route('accounting.accounts.index')->with('success', 'Account deleted.');
    }

    public function addComment(Request $request, Account $account)
    {
        $this->authorize('comment', $account);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($account->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $account->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
