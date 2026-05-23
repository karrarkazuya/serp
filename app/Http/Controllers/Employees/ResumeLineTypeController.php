<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\ResumeLineType;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ResumeLineTypeController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', ResumeLineType::class);

        $query = ResumeLineType::query();

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(ResumeLineType::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.resume-line-types.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $lineTypes = $query->paginate(50)->withQueryString();

        return view('employees.resume-line-types.index', compact('lineTypes'));
    }

    public function show(ResumeLineType $resumeLineType)
    {
        $this->authorize('view', $resumeLineType);

        return view('employees.resume-line-types.show', compact('resumeLineType'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', ResumeLineType::class);

        return view('employees.resume-line-types.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', ResumeLineType::class);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        $resumeLineType = DB::transaction(fn () => ResumeLineType::create($data));

        return redirect()->route('employees.resume-line-types.show', $resumeLineType)->with('success', 'Resume line type created.');
    }

    public function edit(ResumeLineType $resumeLineType)
    {
        $this->authorize('update', $resumeLineType);

        return view('employees.resume-line-types.edit', compact('resumeLineType'));
    }

    public function write(Request $request, ResumeLineType $resumeLineType)
    {
        $this->authorize('update', $resumeLineType);

        $data = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'active' => 'boolean',
        ]);

        DB::transaction(fn () => $resumeLineType->update($data));

        return redirect()->route('employees.resume-line-types.show', $resumeLineType)->with('success', 'Resume line type updated.');
    }

    public function archive(Request $_request, ResumeLineType $resumeLineType)
    {
        $this->authorize('update', $resumeLineType);

        DB::transaction(fn () => $resumeLineType->update(['active' => false]));

        return redirect()->route('employees.resume-line-types.index')->with('success', 'Resume line type archived.');
    }

    public function unarchive(Request $_request, ResumeLineType $resumeLineType)
    {
        $this->authorize('update', $resumeLineType);

        DB::transaction(fn () => $resumeLineType->update(['active' => true]));

        return redirect()->route('employees.resume-line-types.show', $resumeLineType)->with('success', 'Resume line type restored.');
    }

    public function unlink(Request $_request, ResumeLineType $resumeLineType)
    {
        $this->authorize('delete', $resumeLineType);

        DB::transaction(fn () => $resumeLineType->delete());

        return redirect()->route('employees.resume-line-types.index')->with('success', 'Resume line type deleted.');
    }

    public function addComment(Request $request, ResumeLineType $resumeLineType)
    {
        $this->authorize('comment', $resumeLineType);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $resumeLineType->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
