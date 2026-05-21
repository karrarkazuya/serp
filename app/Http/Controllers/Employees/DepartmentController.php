<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreDepartmentRequest;
use App\Http\Requests\Employees\UpdateDepartmentRequest;
use App\Models\Employees\Department;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\DepartmentService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly DepartmentService $deptService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Department::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Department::query()->with(['company', 'manager', 'parent']);

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        SortsTable::apply($query, $request);

        $departments = $query->withCount('employees')->paginate(50)->withQueryString();

        return view('employees.departments.index', compact('departments'));
    }

    public function show(Department $department)
    {
        $this->authorize('view', $department);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($department->company_id, $activeCompanyIds), 403);

        $department->load(['company', 'manager', 'parent', 'children.manager', 'employees.job', 'creator', 'updater']);

        return view('employees.departments.show', compact('department'));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Department::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        return view('employees.departments.create', compact('defaultCompanyId'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        $dept = DB::transaction(fn () => $this->deptService->create($request->validated()));

        return redirect()->route('employees.departments.show', $dept)->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        $this->authorize('update', $department);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($department->company_id, $activeCompanyIds), 403);

        return view('employees.departments.edit', compact('department'));
    }

    public function write(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->update($department, $request->validated()));

        return redirect()->route('employees.departments.show', $department)->with('success', 'Department updated.');
    }

    public function archive(Request $_request, Department $department)
    {
        $this->authorize('update', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->archive($department));

        return redirect()->route('employees.departments.index')->with('success', 'Department archived.');
    }

    public function unarchive(Request $_request, Department $department)
    {
        $this->authorize('update', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->unarchive($department));

        return redirect()->route('employees.departments.show', $department)->with('success', 'Department restored.');
    }

    public function unlink(Request $_request, Department $department)
    {
        $this->authorize('delete', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->delete($department));

        return redirect()->route('employees.departments.index')->with('success', 'Department deleted.');
    }

    public function addComment(Request $request, Department $department)
    {
        $this->authorize('comment', $department);
        $request->validate(['body' => 'required|string|max:5000']);
        $department->logComment($request->body);

        return back()->with('success', 'Comment added.');
    }
}
