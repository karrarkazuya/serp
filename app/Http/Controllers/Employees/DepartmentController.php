<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreDepartmentRequest;
use App\Http\Requests\Employees\UpdateDepartmentRequest;
use App\Models\Employees\Department;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\DepartmentService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        // Fail-closed multi-tenant gate (see EmployeeController::read).
        empty($activeCompanyIds)
            ? $query->whereRaw('1 = 0')
            : $query->forCompanies($activeCompanyIds);

        SearchFilters::apply($query, $request);

        if ($request->query('filter') === 'archived') {
            $query->where('active', false);
        } elseif ($request->query('filter') === 'all') {
            // no filter
        } else {
            $query->active();
        }

        $view = $request->query('view', 'list');

        if ($view === 'tree') {
            $all = (clone $query)
                ->with(['manager'])
                ->withCount('employees')
                ->orderBy('name')
                ->limit(500)
                ->get();

            $treeNodes = $this->buildDepartmentTree($all);
            $total     = $all->count();

            return view('employees.departments.index', compact('treeNodes', 'total', 'view'));
        }

        $groupBy = $request->query('group_by');
        if ($view === 'list' && $groupBy) {
            $fields = SearchFilters::fieldsFor(Department::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)->with(['company', 'manager', 'parent'])->withCount('employees')->orderBy('id')->get();
                $groups  = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.departments.index', compact('groups', 'view'));
            }
        }

        SortsTable::apply($query, $request);

        $departments = $query->withCount('employees')->paginate(50)->withQueryString();

        return view('employees.departments.index', compact('departments', 'view'));
    }

    public function show(Department $department)
    {
        $this->authorize('view', $department);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

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

        return redirect()->route('employees.departments.show', $dept)->with('success', __('employees.department_created'));
    }

    public function edit(Department $department)
    {
        $this->authorize('update', $department);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

        return view('employees.departments.edit', compact('department'));
    }

    public function write(UpdateDepartmentRequest $request, Department $department)
    {
        $this->authorize('update', $department);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->update($department, $request->validated()));

        return redirect()->route('employees.departments.show', $department)->with('success', __('employees.department_updated'));
    }

    public function archive(Request $_request, Department $department)
    {
        $this->authorize('update', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->archive($department));

        return redirect()->route('employees.departments.index')->with('success', __('employees.department_archived'));
    }

    public function unarchive(Request $_request, Department $department)
    {
        $this->authorize('update', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->unarchive($department));

        return redirect()->route('employees.departments.show', $department)->with('success', __('employees.department_unarchived'));
    }

    public function unlink(Request $_request, Department $department)
    {
        $this->authorize('delete', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->deptService->delete($department));

        return redirect()->route('employees.departments.index')->with('success', __('employees.department_deleted'));
    }

    public function addComment(Request $request, Department $department)
    {
        $this->authorize('comment', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($department->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $department->logComment($request->body));

        return back()->with('success', __('employees.comment_added'));
    }

    private function buildDepartmentTree(Collection $departments): array
    {
        $map = [];
        foreach ($departments as $dept) {
            $map[$dept->id] = [
                'id'          => $dept->id,
                'name'        => $dept->name,
                'url'         => route('employees.departments.show', $dept),
                'avatar'      => null,
                'initials'    => mb_strtoupper(mb_substr($dept->name, 0, 2)),
                'subtitle'    => $dept->manager?->name,
                'meta'        => $dept->employees_count ? trans_choice('employees.employees_count', $dept->employees_count, ['count' => $dept->employees_count]) : null,
                'badge'       => $dept->active ? null : __('common.archived'),
                'badge_color' => 'gray',
                'children'    => [],
            ];
        }

        $childrenOf = [];
        $roots      = [];

        foreach ($departments as $dept) {
            if ($dept->parent_id && isset($map[$dept->parent_id])) {
                $childrenOf[$dept->parent_id][] = $dept->id;
            } else {
                $roots[] = $dept->id;
            }
        }

        $buildNode = function (int $id) use (&$buildNode, &$map, $childrenOf): array {
            $node = $map[$id];
            foreach ($childrenOf[$id] ?? [] as $childId) {
                $node['children'][] = $buildNode($childId);
            }
            return $node;
        };

        return array_map($buildNode, $roots);
    }
}
