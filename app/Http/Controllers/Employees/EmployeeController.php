<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreEmployeeRequest;
use App\Http\Requests\Employees\UpdateEmployeeRequest;
use App\Models\Employees\Department;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCategory;
use App\Models\Employees\EmployeeDocument;
use App\Models\Employees\Job;
use App\Models\Employees\ResourceCalendar;
use App\Models\Employees\SkillType;
use App\Models\Employees\WorkLocation;
use App\Services\Company\CompanyContextService;
use App\Services\Employees\EmployeeService;
use App\Services\FileService;
use App\Helpers\GroupsQuery;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly CompanyContextService $companyContext,
        private readonly FileService $fileService,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Employee::query()->with(['company', 'department', 'job', 'manager', 'categories']);

        if (!empty($activeCompanyIds)) {
            $query->forCompanies($activeCompanyIds);
        }

        SearchFilters::apply($query, $request);

        if ($parentId = $request->query('parent_id')) {
            $query->where('parent_id', (int) $parentId);
        }

        $filter = $request->query('filter', '');
        if ($filter === 'archived') {
            $query->inactive();
        } elseif ($filter === 'all') {
            // no filter
        } else {
            $query->active();
        }

        if ($status = $request->query('status')) {
            $query->where('employment_status', $status);
        }

        $view = $request->query('view', 'kanban');

        if ($view === 'tree') {
            $all = (clone $query)
                ->with(['job', 'department'])
                ->orderBy('name')
                ->limit(500)
                ->get();

            $treeNodes = $this->buildEmployeeTree($all);
            $total     = $all->count();

            return view('employees.index', compact('treeNodes', 'total', 'view'));
        }

        $groupBy = $request->query('group_by');
        if ($view === 'list' && $groupBy) {
            $fields = SearchFilters::fieldsFor(Employee::class);
            if (isset($fields[$groupBy])) {
                $records = (clone $query)
                    ->with(['company', 'department', 'job', 'categories'])
                    ->orderBy('name')
                    ->get();
                $groups = GroupsQuery::apply($records, $fields[$groupBy]);
                return view('employees.index', compact('groups', 'view'));
            }
        }

        SortsTable::apply($query, $request);

        $employees = $query->paginate(24)->withQueryString();

        return view('employees.index', compact('employees', 'view'));
    }

    public function show(Employee $employee)
    {
        $this->authorize('view', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $employee->load([
            'company', 'department', 'job', 'manager', 'coach',
            'expenseManager', 'attendanceManager',
            'workLocation', 'resourceCalendar.attendances', 'user', 'contact',
            'categories', 'departureReason',
            'currentContract', 'contracts',
            'skills.skill', 'skills.skillType', 'skills.skillLevel',
            'documents.attachedFile', 'emergencyContacts', 'dependents', 'bankAccounts',
            'subordinates', 'creator', 'updater',
        ]);

        $allIds = Employee::active()
            ->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))
            ->orderBy('name')
            ->pluck('id');

        $currentIndex    = $allIds->search($employee->id);
        $prevId          = $currentIndex > 0 ? $allIds[$currentIndex - 1] : null;
        $nextId          = $currentIndex !== false && $currentIndex < $allIds->count() - 1 ? $allIds[$currentIndex + 1] : null;
        $recordPosition  = $currentIndex !== false ? $currentIndex + 1 : null;
        $recordTotal     = $allIds->count();

        $chain = [];
        $parentId = $employee->parent_id;
        while ($parentId && count($chain) < 3) {
            $mgr = Employee::withCount('subordinates')->find($parentId);
            if (!$mgr) break;
            array_unshift($chain, $mgr);
            $parentId = $mgr->parent_id;
        }

        return view('employees.show', compact(
            'employee', 'prevId', 'nextId', 'recordPosition', 'recordTotal', 'chain'
        ));
    }

    public function create(Request $_request)
    {
        $this->authorize('create', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $defaultCompanyId = count($activeCompanyIds) === 1 ? $activeCompanyIds[0] : null;

        $departments   = Department::active()->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))->orderBy('name')->get(['id', 'name']);
        $jobs          = Job::active()->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))->orderBy('name')->get(['id', 'name']);
        $workLocations = WorkLocation::active()->orderBy('name')->get(['id', 'name']);
        $calendars     = ResourceCalendar::active()->orderBy('name')->get(['id', 'name']);
        $categories    = EmployeeCategory::active()->orderBy('name')->get();
        $managers      = Employee::active()->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))->orderBy('name')->get(['id', 'name']);

        return view('employees.create', compact(
            'defaultCompanyId', 'departments', 'jobs', 'workLocations', 'calendars', 'categories', 'managers'
        ));
    }

    public function store(StoreEmployeeRequest $request)
    {
        $this->authorize('create', Employee::class);

        $data        = $request->validated();
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        $fileRecord = null;
        if ($request->hasFile('avatar')) {
            $fileRecord     = $this->fileService->store($request->file('avatar'), 'avatars/employees', 'employees.read');
            $data['avatar'] = $fileRecord->uuid;
        }

        $employee = DB::transaction(function () use ($data, $categoryIds) {
            $employee = $this->employeeService->create($data);
            $employee->categories()->sync($categoryIds);
            return $employee;
        });

        $fileRecord?->update(['source_type' => $employee->getTable(), 'source_id' => $employee->id]);

        return redirect()->route('employees.show', $employee)->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $employee->load(['categories', 'skills.skill', 'skills.skillType', 'skills.skillLevel', 'documents.attachedFile', 'emergencyContacts', 'dependents', 'bankAccounts', 'resourceCalendar.attendances']);

        $departments   = Department::active()->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))->orderBy('name')->get(['id', 'name']);
        $jobs          = Job::active()->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))->orderBy('name')->get(['id', 'name']);
        $workLocations = WorkLocation::active()->orderBy('name')->get(['id', 'name']);
        $calendars     = ResourceCalendar::active()->orderBy('name')->get(['id', 'name']);
        $categories    = EmployeeCategory::active()->orderBy('name')->get();
        $managers      = Employee::active()
            ->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))
            ->where('id', '!=', $employee->id)
            ->orderBy('name')
            ->get(['id', 'name']);
        $skillTypes = SkillType::active()->with(['skills', 'levels'])->get();

        return view('employees.edit', compact(
            'employee', 'departments', 'jobs', 'workLocations', 'calendars', 'categories', 'managers', 'skillTypes'
        ));
    }

    public function write(UpdateEmployeeRequest $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $data        = $request->validated();
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);
        unset($data['avatar']);

        // Cycle guard on hierarchy FKs — the form request only validates that the
        // target exists and is in an active company, not that pointing at it would
        // form a loop with $employee's own subtree. Without this, A→B→A bricks the
        // tree view (buildEmployeeTree recurses unbounded).
        foreach (['parent_id', 'coach_id', 'expense_manager_id', 'attendance_manager_id'] as $fk) {
            if (!array_key_exists($fk, $data) || !$data[$fk]) continue;
            $targetId = (int) $data[$fk];
            if ($targetId === $employee->id || $this->isEmployeeDescendantOf($targetId, $employee->id)) {
                return back()->withInput()->with('error', "Selected {$fk} would create a circular reporting line.");
            }
        }

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                $this->fileService->deleteByUuid($employee->avatar);
            }
            $fileRecord     = $this->fileService->store($request->file('avatar'), 'avatars/employees', 'employees.read', null, $employee);
            $data['avatar'] = $fileRecord->uuid;
        }

        $skillsData      = $request->input('skills');
        $deleteDocIds    = array_filter((array) $request->input('delete_document_ids', []));
        $newDoc          = $request->input('new_document');

        DB::transaction(function () use ($employee, $data, $categoryIds, $skillsData, $deleteDocIds, $newDoc, $request) {
            $this->employeeService->update($employee, $data);
            $employee->categories()->sync($categoryIds);

            if ($skillsData !== null) {
                $employee->skills()->forceDelete();
                foreach ((array) $skillsData as $s) {
                    if (!empty($s['skill_id'])) {
                        $employee->skills()->create([
                            'employee_id'    => $employee->id,
                            'skill_type_id'  => $s['skill_type_id'] ?: null,
                            'skill_id'       => $s['skill_id'],
                            'skill_level_id' => $s['skill_level_id'] ?: null,
                        ]);
                    }
                }
            }

            if (!empty($deleteDocIds)) {
                $docsToDelete = $employee->documents()->whereIn('id', $deleteDocIds)->get();
                foreach ($docsToDelete as $doc) {
                    if ($doc->file_path) {
                        $this->fileService->deleteByUuid($doc->file_path);
                    }
                    $doc->forceDelete();
                }
            }

            if (!empty($newDoc['name'])) {
                // Validate the inline doc payload the same way EmployeeDocumentController
                // does — without this, this path accepted any document_type string and
                // any notify_before_days value.
                $newDocValidated = validator($newDoc, [
                    'name'               => 'required|string|max:255',
                    'document_type'      => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
                    'issue_date'         => 'nullable|date',
                    'expiry_date'        => 'nullable|date',
                    'notify_before_days' => 'nullable|integer|min:0|max:365',
                    'notes'              => 'nullable|string',
                ])->validate();

                $docData = [
                    'employee_id'        => $employee->id,
                    'name'               => $newDocValidated['name'],
                    'document_type'      => $newDocValidated['document_type'] ?? 'other',
                    'issue_date'         => $newDocValidated['issue_date'] ?? null,
                    'expiry_date'        => $newDocValidated['expiry_date'] ?? null,
                    'notify_before_days' => $newDocValidated['notify_before_days'] ?? 30,
                    'notes'              => $newDocValidated['notes'] ?? null,
                ];
                $docFileRecord = null;
                if ($request->hasFile('new_document.file')) {
                    $docFileRecord        = $this->fileService->store(
                        $request->file('new_document.file'),
                        'documents/employees/' . $employee->id,
                        'employees.read'
                    );
                    $docData['file_path'] = $docFileRecord->uuid;
                }
                $doc = EmployeeDocument::create($docData);
                $docFileRecord?->update(['source_type' => $doc->getTable(), 'source_id' => $doc->id]);
            }
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Employee updated successfully.');
    }

    public function archive(Request $_request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->employeeService->archive($employee));

        return redirect()->route('employees.index')->with('success', 'Employee archived.');
    }

    public function unarchive(Request $_request, Employee $employee)
    {
        $this->authorize('update', $employee);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->employeeService->unarchive($employee));

        return redirect()->route('employees.show', $employee)->with('success', 'Employee restored.');
    }

    public function unlink(Request $_request, Employee $employee)
    {
        $this->authorize('delete', $employee);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        DB::transaction(fn () => $this->employeeService->delete($employee));

        return redirect()->route('employees.index')->with('success', 'Employee deleted.');
    }

    /** Kept for backward compat with Blade views; redirects to unified file route. */
    public function serveAvatar(string $uuid)
    {
        $employee = Employee::where('uuid', $uuid)->firstOrFail();
        abort_unless($employee->avatar, 404);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        return redirect()->route('files.serve', $employee->avatar);
    }

    public function addComment(Request $request, Employee $employee)
    {
        $this->authorize('comment', $employee);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $employee->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }

    public function checkLinkConflict(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $field   = $request->query('field');
        $value   = $request->query('value');
        $exclude = $request->query('exclude');

        if (!in_array($field, ['contact_id', 'user_id']) || !$value) {
            return response()->json(['conflict' => false]);
        }

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $existing = Employee::where($field, $value)
            ->when(!empty($activeCompanyIds), fn($q) => $q->forCompanies($activeCompanyIds))
            ->when($exclude, fn($q) => $q->where('id', '!=', (int) $exclude))
            ->first(['id', 'name', 'uuid']);

        return response()->json([
            'conflict' => $existing !== null,
            'employee' => $existing ? ['id' => $existing->id, 'name' => $existing->name] : null,
        ]);
    }

    private function buildEmployeeTree(Collection $employees): array
    {
        $map = [];
        foreach ($employees as $emp) {
            $map[$emp->id] = [
                'id'       => $emp->id,
                'name'     => $emp->name,
                'url'      => route('employees.show', $emp),
                'avatar'   => $emp->avatar_url,
                'initials' => mb_strtoupper(mb_substr($emp->name, 0, 2)),
                'subtitle' => $emp->job_title ?? $emp->job?->name,
                'meta'     => $emp->department?->name,
                'badge'    => $emp->active ? null : 'Archived',
                'badge_color' => 'gray',
                'children' => [],
            ];
        }

        $childrenOf = [];
        $roots      = [];

        foreach ($employees as $emp) {
            if ($emp->parent_id && isset($map[$emp->parent_id])) {
                $childrenOf[$emp->parent_id][] = $emp->id;
            } else {
                $roots[] = $emp->id;
            }
        }

        // Bounded recursion + visited-set so an already-corrupted parent_id cycle
        // in the data can't produce infinite recursion or duplicate subtrees.
        $buildNode = function (int $id, array $visited = []) use (&$buildNode, &$map, $childrenOf): array {
            $node = $map[$id];
            $visited[$id] = true;
            foreach ($childrenOf[$id] ?? [] as $childId) {
                if (isset($visited[$childId])) continue;
                $node['children'][] = $buildNode($childId, $visited);
            }
            return $node;
        };

        return array_map(fn ($id) => $buildNode($id), $roots);
    }

    /**
     * Is $candidateId in $rootId's subtree (i.e. would pointing at it create a
     * cycle if assigned as $rootId's parent/coach/manager)? Walks parent_id
     * upward from $candidateId; if we hit $rootId, candidate is a descendant.
     * Bounded so corrupted data can't hang the request.
     */
    private function isEmployeeDescendantOf(int $candidateId, int $rootId): bool
    {
        $seen = [];
        $currentId = $candidateId;

        for ($i = 0; $i < 64; $i++) {
            if (in_array($currentId, $seen, true)) {
                return false;
            }
            $seen[] = $currentId;

            $parentId = Employee::where('id', $currentId)->value('parent_id');
            if (!$parentId) {
                return false;
            }
            if ((int) $parentId === $rootId) {
                return true;
            }
            $currentId = (int) $parentId;
        }

        return false;
    }
}
