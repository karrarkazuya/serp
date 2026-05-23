<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\EmploymentType;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmploymentTypeController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', EmploymentType::class);

        $query = EmploymentType::query();

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
            $fields = SearchFilters::fieldsFor(EmploymentType::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.employment-types.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $employmentTypes = $query->paginate(50)->withQueryString();

        return view('employees.employment-types.index', compact('employmentTypes'));
    }

    public function show(EmploymentType $employmentType)
    {
        $this->authorize('view', $employmentType);

        return view('employees.employment-types.show', compact('employmentType'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', EmploymentType::class);

        return view('employees.employment-types.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', EmploymentType::class);

        $data = $request->validate([
            'name'   => 'required|string|max:255',
            'active' => 'boolean',
        ]);

        $employmentType = DB::transaction(fn () => EmploymentType::create($data));

        return redirect()->route('employees.employment-types.show', $employmentType)->with('success', 'Employment type created.');
    }

    public function edit(EmploymentType $employmentType)
    {
        $this->authorize('update', $employmentType);

        return view('employees.employment-types.edit', compact('employmentType'));
    }

    public function write(Request $request, EmploymentType $employmentType)
    {
        $this->authorize('update', $employmentType);

        $data = $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'active' => 'boolean',
        ]);

        DB::transaction(fn () => $employmentType->update($data));

        return redirect()->route('employees.employment-types.show', $employmentType)->with('success', 'Employment type updated.');
    }

    public function archive(Request $_request, EmploymentType $employmentType)
    {
        $this->authorize('update', $employmentType);

        DB::transaction(fn () => $employmentType->update(['active' => false]));

        return redirect()->route('employees.employment-types.index')->with('success', 'Employment type archived.');
    }

    public function unarchive(Request $_request, EmploymentType $employmentType)
    {
        $this->authorize('update', $employmentType);

        DB::transaction(fn () => $employmentType->update(['active' => true]));

        return redirect()->route('employees.employment-types.show', $employmentType)->with('success', 'Employment type restored.');
    }

    public function unlink(Request $_request, EmploymentType $employmentType)
    {
        $this->authorize('delete', $employmentType);

        DB::transaction(fn () => $employmentType->delete());

        return redirect()->route('employees.employment-types.index')->with('success', 'Employment type deleted.');
    }

    public function addComment(Request $request, EmploymentType $employmentType)
    {
        $this->authorize('comment', $employmentType);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $employmentType->logComment($request->body));
        return back()->with('success', 'Comment added.');
    }
}
