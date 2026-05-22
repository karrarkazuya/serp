<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Goal;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoalController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Goal::class);

        $query = Goal::query();

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $goals = $query->paginate(50)->withQueryString();

        return view('employees.goals.index', compact('goals'));
    }

    public function show(Goal $goal)
    {
        $this->authorize('view', $goal);

        return view('employees.goals.show', compact('goal'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Goal::class);

        return view('employees.goals.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Goal::class);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ]);

        $goal = DB::transaction(fn () => Goal::create($data));

        return redirect()->route('employees.goals.show', $goal)->with('success', 'Goal created.');
    }

    public function edit(Goal $goal)
    {
        $this->authorize('update', $goal);

        return view('employees.goals.edit', compact('goal'));
    }

    public function write(Request $request, Goal $goal)
    {
        $this->authorize('update', $goal);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ]);

        DB::transaction(fn () => $goal->update($data));

        return redirect()->route('employees.goals.show', $goal)->with('success', 'Goal updated.');
    }

    public function archive(Request $_request, Goal $goal)
    {
        $this->authorize('update', $goal);

        DB::transaction(fn () => $goal->update(['active' => false]));

        return redirect()->route('employees.goals.index')->with('success', 'Goal archived.');
    }

    public function unarchive(Request $_request, Goal $goal)
    {
        $this->authorize('update', $goal);

        DB::transaction(fn () => $goal->update(['active' => true]));

        return redirect()->route('employees.goals.show', $goal)->with('success', 'Goal restored.');
    }

    public function unlink(Request $_request, Goal $goal)
    {
        $this->authorize('delete', $goal);

        DB::transaction(fn () => $goal->delete());

        return redirect()->route('employees.goals.index')->with('success', 'Goal deleted.');
    }

    public function addComment(Request $request, Goal $goal)
    {
        $this->authorize('comment', $goal);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $goal->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
