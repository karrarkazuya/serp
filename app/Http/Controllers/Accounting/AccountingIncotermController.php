<?php

namespace App\Http\Controllers\Accounting;

use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Controllers\Controller;
use App\Models\Accounting\AccountingIncoterm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AccountingIncotermController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', AccountingIncoterm::class);

        $query = AccountingIncoterm::query();

        SearchFilters::apply($query, $request);

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(AccountingIncoterm::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('accounting.incoterms.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request, defaultColumn: 'code', defaultDirection: 'asc');

        $incoterms = $query->paginate(40)->withQueryString();

        return view('accounting.incoterms.index', compact('incoterms'));
    }

    public function show(AccountingIncoterm $incoterm)
    {
        $this->authorize('view', $incoterm);

        $incoterm->load(['creator', 'updater']);

        $allIds = AccountingIncoterm::orderBy('code')->pluck('id');
        $currentIndex = $allIds->search($incoterm->id);
        $prevId = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal    = $allIds->count();

        return view('accounting.incoterms.show', compact(
            'incoterm', 'prevId', 'nextId', 'recordPosition', 'recordTotal'
        ));
    }

    public function create()
    {
        $this->authorize('create', AccountingIncoterm::class);

        return view('accounting.incoterms.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', AccountingIncoterm::class);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'unique:accounting_incoterms,code'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $incoterm = DB::transaction(fn () => AccountingIncoterm::create($data));

        return redirect()->route('accounting.incoterms.show', $incoterm)->with('success', 'Incoterm created.');
    }

    public function edit(AccountingIncoterm $incoterm)
    {
        $this->authorize('update', $incoterm);

        return view('accounting.incoterms.edit', compact('incoterm'));
    }

    public function write(Request $request, AccountingIncoterm $incoterm)
    {
        $this->authorize('update', $incoterm);

        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', Rule::unique('accounting_incoterms', 'code')->ignore($incoterm->id)],
            'name' => ['required', 'string', 'max:255'],
        ]);

        DB::transaction(fn () => $incoterm->update($data));

        return redirect()->route('accounting.incoterms.show', $incoterm)->with('success', 'Incoterm updated.');
    }

    public function unlink(Request $request, AccountingIncoterm $incoterm)
    {
        $this->authorize('delete', $incoterm);

        DB::transaction(fn () => $incoterm->delete());

        return redirect()->route('accounting.incoterms.index')->with('success', 'Incoterm deleted.');
    }

    public function addComment(Request $request, AccountingIncoterm $incoterm)
    {
        $this->authorize('comment', $incoterm);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $incoterm->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
