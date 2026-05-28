<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreJournalRequest;
use App\Http\Requests\Accounting\UpdateJournalRequest;
use App\Models\Accounting\AccountJournal;
use App\Services\Accounting\AccountingService;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountJournalController extends Controller
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountJournal::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        // forCompanies() is fail-closed (see AccountJournal::scopeForCompanies).
        $query = AccountJournal::query()->with(['company', 'defaultAccount'])->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->inactive();
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountJournal::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['company', 'defaultAccount'])->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.journals.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'code', defaultDirection: 'asc');

        $journals = $query->paginate(40)->withQueryString();

        return view('accounting.journals.index', compact('journals'));
    }

    public function show(AccountJournal $journal)
    {
        $this->authorize('view', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        $journal->load(['company', 'defaultAccount', 'suspenseAccount', 'creator', 'updater']);

        $recentMoves = $journal->moves()
            ->with('partner')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(20)
            ->get();

        $allIds = AccountJournal::active()
            ->forCompanies($activeCompanyIds)
            ->orderBy('code')
            ->pluck('id');
        $currentIndex = $allIds->search($journal->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal = $allIds->count();

        return view('accounting.journals.show', compact(
            'journal', 'recentMoves', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', AccountJournal::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $journalTypes = AccountJournal::TYPES;

        return view('accounting.journals.create', compact('defaultCompanyId', 'journalTypes'));
    }

    public function store(StoreJournalRequest $request)
    {
        $journal = DB::transaction(fn () => $this->accounting->createJournal($request->validated()));

        return redirect()
            ->route('accounting.journals.show', $journal)
            ->with('success', 'Journal created.');
    }

    public function edit(AccountJournal $journal)
    {
        $this->authorize('update', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        $journalTypes = AccountJournal::TYPES;

        return view('accounting.journals.edit', compact('journal', 'journalTypes'));
    }

    public function write(UpdateJournalRequest $request, AccountJournal $journal)
    {
        $this->authorize('update', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->updateJournal($journal, $request->validated()));

        return redirect()
            ->route('accounting.journals.show', $journal)
            ->with('success', 'Journal updated.');
    }

    public function archive(Request $_request, AccountJournal $journal)
    {
        $this->authorize('update', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->archiveJournal($journal));
        return redirect()->route('accounting.journals.index')->with('success', 'Journal archived.');
    }

    public function unarchive(Request $_request, AccountJournal $journal)
    {
        $this->authorize('update', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->unarchiveJournal($journal));
        return redirect()->route('accounting.journals.show', $journal)->with('success', 'Journal restored.');
    }

    public function unlink(Request $_request, AccountJournal $journal)
    {
        $this->authorize('delete', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        try {
            DB::transaction(fn () => $this->accounting->deleteJournal($journal));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.journals.index')->with('success', 'Journal deleted.');
    }

    public function addComment(Request $request, AccountJournal $journal)
    {
        $this->authorize('comment', $journal);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($journal->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $journal->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
