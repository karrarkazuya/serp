<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Challenge;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChallengeController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Challenge::class);

        $query = Challenge::query();

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $challenges = $query->paginate(50)->withQueryString();

        return view('employees.challenges.index', compact('challenges'));
    }

    public function show(Challenge $challenge)
    {
        $this->authorize('view', $challenge);

        return view('employees.challenges.show', compact('challenge'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Challenge::class);

        return view('employees.challenges.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Challenge::class);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ]);

        $challenge = DB::transaction(fn () => Challenge::create($data));

        return redirect()->route('employees.challenges.show', $challenge)->with('success', 'Challenge created.');
    }

    public function edit(Challenge $challenge)
    {
        $this->authorize('update', $challenge);

        return view('employees.challenges.edit', compact('challenge'));
    }

    public function write(Request $request, Challenge $challenge)
    {
        $this->authorize('update', $challenge);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ]);

        DB::transaction(fn () => $challenge->update($data));

        return redirect()->route('employees.challenges.show', $challenge)->with('success', 'Challenge updated.');
    }

    public function archive(Request $_request, Challenge $challenge)
    {
        $this->authorize('update', $challenge);

        DB::transaction(fn () => $challenge->update(['active' => false]));

        return redirect()->route('employees.challenges.index')->with('success', 'Challenge archived.');
    }

    public function unarchive(Request $_request, Challenge $challenge)
    {
        $this->authorize('update', $challenge);

        DB::transaction(fn () => $challenge->update(['active' => true]));

        return redirect()->route('employees.challenges.show', $challenge)->with('success', 'Challenge restored.');
    }

    public function unlink(Request $_request, Challenge $challenge)
    {
        $this->authorize('delete', $challenge);

        DB::transaction(fn () => $challenge->delete());

        return redirect()->route('employees.challenges.index')->with('success', 'Challenge deleted.');
    }

    public function addComment(Request $request, Challenge $challenge)
    {
        $this->authorize('comment', $challenge);
        $request->validate(['body' => 'required|string|max:5000']);
        $challenge->logComment($request->body);
        return back()->with('success', 'Comment added.');
    }
}
