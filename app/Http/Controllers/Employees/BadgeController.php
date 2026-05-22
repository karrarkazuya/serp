<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Badge;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BadgeController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', Badge::class);

        $query = Badge::query();

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $badges = $query->paginate(50)->withQueryString();

        return view('employees.badges.index', compact('badges'));
    }

    public function show(Badge $badge)
    {
        $this->authorize('view', $badge);

        return view('employees.badges.show', compact('badge'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Badge::class);

        return view('employees.badges.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', Badge::class);

        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ]);

        $badge = DB::transaction(fn () => Badge::create($data));

        return redirect()->route('employees.badges.show', $badge)->with('success', 'Badge created.');
    }

    public function edit(Badge $badge)
    {
        $this->authorize('update', $badge);

        return view('employees.badges.edit', compact('badge'));
    }

    public function write(Request $request, Badge $badge)
    {
        $this->authorize('update', $badge);

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'active'      => 'boolean',
        ]);

        DB::transaction(fn () => $badge->update($data));

        return redirect()->route('employees.badges.show', $badge)->with('success', 'Badge updated.');
    }

    public function archive(Request $_request, Badge $badge)
    {
        $this->authorize('update', $badge);

        DB::transaction(fn () => $badge->update(['active' => false]));

        return redirect()->route('employees.badges.index')->with('success', 'Badge archived.');
    }

    public function unarchive(Request $_request, Badge $badge)
    {
        $this->authorize('update', $badge);

        DB::transaction(fn () => $badge->update(['active' => true]));

        return redirect()->route('employees.badges.show', $badge)->with('success', 'Badge restored.');
    }

    public function unlink(Request $_request, Badge $badge)
    {
        $this->authorize('delete', $badge);

        DB::transaction(fn () => $badge->delete());

        return redirect()->route('employees.badges.index')->with('success', 'Badge deleted.');
    }

    public function addComment(Request $request, Badge $badge)
    {
        $this->authorize('comment', $badge);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $badge->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
