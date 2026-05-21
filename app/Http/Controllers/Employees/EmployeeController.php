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
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeController extends Controller
{
    public function __construct(
        private readonly EmployeeService $employeeService,
        private readonly CompanyContextService $companyContext,
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

        SortsTable::apply($query, $request);

        $employees = $query->paginate(24)->withQueryString();

        return view('employees.index', compact('employees'));
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
            'documents', 'emergencyContacts', 'dependents', 'bankAccounts',
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
        $data        = $request->validated();
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $data['avatar'] = $file->storeAs(
                'avatars/employees',
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'local'
            );
        }

        $employee = DB::transaction(function () use ($data, $categoryIds) {
            $employee = $this->employeeService->create($data);
            $employee->categories()->sync($categoryIds);
            return $employee;
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Employee created successfully.');
    }

    public function edit(Employee $employee)
    {
        $this->authorize('update', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $employee->load(['categories', 'skills.skill', 'skills.skillType', 'skills.skillLevel', 'documents', 'emergencyContacts', 'dependents', 'bankAccounts', 'resourceCalendar.attendances']);

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
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $data        = $request->validated();
        $categoryIds = $data['categories'] ?? [];
        unset($data['categories']);
        unset($data['avatar']);

        if ($request->hasFile('avatar')) {
            if ($employee->avatar) {
                Storage::disk('local')->delete($employee->avatar);
            }
            $file = $request->file('avatar');
            $data['avatar'] = $file->storeAs(
                'avatars/employees',
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'local'
            );
        }

        $skillsData      = $request->input('skills');
        $deleteDocIds    = array_filter((array) $request->input('delete_document_ids', []));
        $newDoc          = $request->input('new_document');

        DB::transaction(function () use ($employee, $data, $categoryIds, $skillsData, $deleteDocIds, $newDoc, $request) {
            $this->employeeService->update($employee, $data);
            $employee->categories()->sync($categoryIds);

            if ($skillsData !== null) {
                $employee->skills()->delete();
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
                        Storage::disk('local')->delete($doc->file_path);
                    }
                    $doc->delete();
                }
            }

            if (!empty($newDoc['name'])) {
                $docData = [
                    'employee_id'        => $employee->id,
                    'name'               => $newDoc['name'],
                    'document_type'      => $newDoc['document_type'] ?? 'other',
                    'issue_date'         => $newDoc['issue_date'] ?: null,
                    'expiry_date'        => $newDoc['expiry_date'] ?: null,
                    'notify_before_days' => $newDoc['notify_before_days'] ?? 30,
                    'notes'              => $newDoc['notes'] ?? null,
                ];
                if ($request->hasFile('new_document.file')) {
                    $file = $request->file('new_document.file');
                    $docData['file_path'] = $file->storeAs(
                        'documents/employees/' . $employee->id,
                        Str::uuid() . '.' . $file->getClientOriginalExtension(),
                        'local'
                    );
                }
                EmployeeDocument::create($docData);
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

    public function serveAvatar(string $uuid)
    {
        $employee = Employee::where('uuid', $uuid)->firstOrFail();

        $this->authorize('view', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        abort_unless($employee->avatar && Storage::disk('local')->exists($employee->avatar), 404);

        return response()->file(Storage::disk('local')->path($employee->avatar));
    }

    public function addComment(Request $request, Employee $employee)
    {
        $this->authorize('comment', $employee);
        $request->validate(['body' => 'required|string|max:5000']);
        $employee->logComment($request->body);

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

        $existing = Employee::where($field, $value)
            ->when($exclude, fn($q) => $q->where('id', '!=', (int) $exclude))
            ->first(['id', 'name', 'uuid']);

        return response()->json([
            'conflict' => $existing !== null,
            'employee' => $existing ? ['id' => $existing->id, 'name' => $existing->name] : null,
        ]);
    }
}
