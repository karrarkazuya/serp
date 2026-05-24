<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accounting\StoreMoveRequest;
use App\Http\Requests\Accounting\UpdateMoveRequest;
use App\Models\Accounting\AccountMove;
use App\Services\Accounting\AccountingService;
use App\Services\Company\CompanyContextService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountMoveController extends Controller
{
    public function __construct(
        private readonly AccountingService $accounting,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountMove::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = AccountMove::query()->with(['journal', 'partner'])->where('move_type', 'entry');

        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        $stateFilter = $request->query('state');
        if ($stateFilter && in_array($stateFilter, ['draft', 'posted', 'cancelled'], true)) {
            $query->where('state', $stateFilter);
        }

        if ($journalId = $request->query('journal_id')) {
            $query->where('journal_id', (int) $journalId);
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountMove::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['journal', 'partner'])->orderBy('date', 'desc')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.moves.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'date', defaultDirection: 'desc');

        $moves = $query->paginate(40)->withQueryString();

        return view('accounting.moves.index', compact('moves'));
    }

    public function show(AccountMove $move)
    {
        $this->authorize('view', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        $redirect = match($move->move_type) {
            'out_invoice' => route('accounting.invoices.show', $move),
            'in_invoice'  => route('accounting.bills.show', $move),
            'out_refund'  => route('accounting.credit-notes.show', $move),
            'in_refund'   => route('accounting.refunds.show', $move),
            default       => null,
        };
        if ($redirect) {
            return redirect($redirect);
        }

        $move->load(['journal', 'partner', 'lines.account', 'lines.partner', 'creator', 'updater', 'poster', 'reversedMove', 'reversal']);

        $balance = $this->accounting->computeMoveBalance($move);

        $allIds = AccountMove::query()
            ->where('move_type', 'entry')
            ->when(!empty($activeCompanyIds), fn ($q) => $q->forCompanies($activeCompanyIds))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->pluck('id');
        $currentIndex = $allIds->search($move->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal = $allIds->count();

        $moveTypes = AccountMove::MOVE_TYPES;

        return view('accounting.moves.show', compact(
            'move', 'balance', 'prevId', 'nextId', 'recordPosition', 'recordTotal', 'moveTypes'
        ));
    }

    public function create(Request $request)
    {
        $this->authorize('create', AccountMove::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $preselectedJournalId = $request->query('journal_id') ? (int) $request->query('journal_id') : null;

        $moveTypes = AccountMove::MOVE_TYPES;

        return view('accounting.moves.create', compact('defaultCompanyId', 'preselectedJournalId', 'moveTypes'));
    }

    public function store(StoreMoveRequest $request)
    {
        $data  = $request->validated();
        $lines = $data['lines'];
        $action = $data['action'] ?? 'save';
        unset($data['lines'], $data['action']);

        try {
            $move = DB::transaction(function () use ($data, $lines, $action) {
                $created = $this->accounting->createMove($data, $lines);
                if ($action === 'post') {
                    if (!auth()->user()->hasPermission('accounting.post')) {
                        abort(403, 'You do not have permission to post entries.');
                    }
                    $created = $this->accounting->postMove($created);
                }
                return $created;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.moves.show', $move)
            ->with('success', $move->isPosted() ? 'Entry posted.' : 'Entry saved as draft.');
    }

    public function edit(AccountMove $move)
    {
        $this->authorize('update', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        if (!$move->isDraft()) {
            return redirect()
                ->route('accounting.moves.show', $move)
                ->with('error', 'Only draft entries can be edited.');
        }

        $move->load(['lines.account', 'lines.partner', 'journal', 'partner']);

        $moveTypes = AccountMove::MOVE_TYPES;

        return view('accounting.moves.edit', compact('move', 'moveTypes'));
    }

    public function write(UpdateMoveRequest $request, AccountMove $move)
    {
        $this->authorize('update', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        $data  = $request->validated();
        $lines = $data['lines'];
        $action = $data['action'] ?? 'save';
        // Belt-and-braces: company_id is already pinned at the form layer
        // (UpdateMoveRequest::rules uses Rule::in([$move->company_id])), but
        // strip it from $data here too so the service cannot mutate the move's
        // company_id even if a future refactor loosens the form rule.
        unset($data['lines'], $data['action'], $data['company_id']);

        try {
            $move = DB::transaction(function () use ($move, $data, $lines, $action) {
                $updated = $this->accounting->updateMove($move, $data, $lines);
                if ($action === 'post') {
                    if (!auth()->user()->hasPermission('accounting.post')) {
                        abort(403, 'You do not have permission to post entries.');
                    }
                    $updated = $this->accounting->postMove($updated);
                }
                return $updated;
            });
        } catch (\RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('accounting.moves.show', $move)
            ->with('success', $move->isPosted() ? 'Entry posted.' : 'Entry updated.');
    }

    public function post(Request $_request, AccountMove $move)
    {
        $this->authorize('post', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        try {
            DB::transaction(fn () => $this->accounting->postMove($move));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.moves.show', $move)->with('success', 'Entry posted.');
    }

    public function resetToDraft(Request $_request, AccountMove $move)
    {
        $this->authorize('post', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->resetMoveToDraft($move));
        return redirect()->route('accounting.moves.show', $move)->with('success', 'Entry reset to draft.');
    }

    public function cancel(Request $_request, AccountMove $move)
    {
        // D3 (Odoo parity): the dedicated `cancel` policy ability gates
        // posted-invoice cancellation to accounting managers; pure journal
        // entries (move_type=entry) fall through to the post-permission check.
        $this->authorize('cancel', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->accounting->cancelMove($move));
        return redirect()->route('accounting.moves.show', $move)->with('success', 'Entry cancelled.');
    }

    public function reverse(Request $request, AccountMove $move)
    {
        $this->authorize('post', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        $request->validate(['reversal_date' => 'nullable|date']);
        $date = $request->filled('reversal_date') ? Carbon::parse($request->input('reversal_date')) : null;

        try {
            $reversal = DB::transaction(fn () => $this->accounting->reverseMove($move, $date));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        // Odoo parity (O5): the reversal lands in draft. Auto-reconcile with
        // the original move fires when the user posts it.
        return redirect()
            ->route('accounting.moves.show', $reversal)
            ->with('success', 'Reversal entry drafted. Review the lines and post to apply.');
    }

    public function unlink(Request $_request, AccountMove $move)
    {
        $this->authorize('delete', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        try {
            DB::transaction(fn () => $this->accounting->deleteMove($move));
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.moves.index')->with('success', 'Entry deleted.');
    }

    public function addComment(Request $request, AccountMove $move)
    {
        $this->authorize('comment', $move);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(in_array($move->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $move->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
