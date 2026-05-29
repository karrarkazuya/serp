<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\EmployeeCategory;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeCategoryController extends Controller
{
    public function read(Request $request)
    {
        $this->authorize('viewAny', \App\Models\Employees\Employee::class);

        $query = EmployeeCategory::query()->withCount('employees');

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $groupBy = $request->query('group_by');
        if ($groupBy) {
            $fields = SearchFilters::fieldsFor(EmployeeCategory::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->withCount('employees')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.categories.index', compact('groups'));
            }
        }

        SortsTable::apply($query, $request);

        $categories = $query->paginate(50)->withQueryString();

        return view('employees.categories.index', compact('categories'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', \App\Models\Employees\Employee::class);
        return view('employees.categories.create');
    }

    public function store(Request $request)
    {
        $this->authorize('create', \App\Models\Employees\Employee::class);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
        ]);

        $category = DB::transaction(fn () => EmployeeCategory::create($data));

        return redirect()->route('employees.categories.show', $category)->with('success', __('employees.category_created'));
    }

    public function show(EmployeeCategory $employeeCategory)
    {
        $this->authorize('viewAny', \App\Models\Employees\Employee::class);
        $employeeCategory->load(['employees.job']);
        return view('employees.categories.show', compact('employeeCategory'));
    }

    public function edit(EmployeeCategory $employeeCategory)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        return view('employees.categories.edit', compact('employeeCategory'));
    }

    public function write(Request $request, EmployeeCategory $employeeCategory)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);

        $data = $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'nullable|string|max:20',
        ]);

        DB::transaction(fn () => $employeeCategory->update($data));

        return redirect()->route('employees.categories.show', $employeeCategory)->with('success', __('employees.category_updated'));
    }

    public function archive(Request $_request, EmployeeCategory $employeeCategory)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        DB::transaction(fn () => $employeeCategory->update(['active' => false]));
        return redirect()->route('employees.categories.index')->with('success', __('employees.category_archived'));
    }

    public function unarchive(Request $_request, EmployeeCategory $employeeCategory)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        DB::transaction(fn () => $employeeCategory->update(['active' => true]));
        return redirect()->route('employees.categories.show', $employeeCategory)->with('success', __('employees.category_unarchived'));
    }

    public function unlink(Request $_request, EmployeeCategory $employeeCategory)
    {
        $this->authorize('delete', \App\Models\Employees\Employee::class);
        DB::transaction(fn () => $employeeCategory->delete());
        return redirect()->route('employees.categories.index')->with('success', __('employees.category_deleted'));
    }

    public function addComment(Request $request, EmployeeCategory $employeeCategory)
    {
        $this->authorize('update', \App\Models\Employees\Employee::class);
        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $employeeCategory->logComment($request->body));
        return back()->with('success', __('employees.comment_added'));
    }
}
