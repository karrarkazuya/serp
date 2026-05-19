<?php

namespace App\Http\Controllers\Workflow;

use App\Http\Controllers\Controller;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use App\Http\Requests\Workflow\StoreDepartmentRequest;
use App\Models\Settings\Company;
use App\Models\Workflow\Department;
use App\Services\Company\CompanyContextService;
use App\Services\Workflow\WorkflowConfigService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DepartmentController extends Controller
{
    public function __construct(
        private readonly WorkflowConfigService $configService,
        private readonly CompanyContextService $companyContext,
    ) {}

    public function read(Request $request)
    {
        $this->authorize('viewAny', Department::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $query = Department::query()->with('company');
        if (!empty($activeCompanyIds)) {
            $query->where(fn ($q) => $q->whereIn('company_id', $activeCompanyIds)->orWhereNull('company_id'));
        }
        SearchFilters::apply($query, $request);
        SortsTable::apply($query, $request);
        $departments = $query->paginate(30)->withQueryString();

        return view('workflow.configuration.departments.index', compact('departments'));
    }

    public function create()
    {
        $this->authorize('create', Department::class);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $companies = Company::whereIn('id', $activeCompanyIds)->active()->orderBy('name')->get();

        return view('workflow.configuration.departments.create', compact('companies'));
    }

    public function show(Department $department)
    {
        $this->authorize('view', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($department->company_id) || in_array($department->company_id, $activeCompanyIds), 403);

        $department->load(['company', 'workflowUsers.user']);
        $messages = $department->chatterMessages()->with('user')->latest()->get();

        return view('workflow.configuration.departments.show', compact('department', 'messages'));
    }

    public function store(StoreDepartmentRequest $request)
    {
        DB::transaction(fn () => $this->configService->createDepartment($request->validated()));

        return redirect()->route('workflow.config.departments.index')->with('success', 'Department created.');
    }

    public function edit(Department $department)
    {
        $this->authorize('update', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($department->company_id) || in_array($department->company_id, $activeCompanyIds), 403);
        $companies = Company::whereIn('id', $activeCompanyIds)->active()->orderBy('name')->get();

        return view('workflow.configuration.departments.edit', compact('department', 'companies'));
    }

    public function write(Request $request, Department $department)
    {
        $this->authorize('update', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($department->company_id) || in_array($department->company_id, $activeCompanyIds), 403);
        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'active'     => 'boolean',
        ]);
        DB::transaction(fn () => $this->configService->updateDepartment($department, $data));

        return redirect()->route('workflow.config.departments.index')->with('success', 'Department updated.');
    }

    public function unlink(Department $department)
    {
        $this->authorize('delete', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($department->company_id) || in_array($department->company_id, $activeCompanyIds), 403);
        DB::transaction(fn () => $this->configService->deleteDepartment($department));

        return redirect()->route('workflow.config.departments.index')->with('success', 'Department deleted.');
    }

    public function addComment(Request $request, Department $department)
    {
        $this->authorize('comment', $department);
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(is_null($department->company_id) || in_array($department->company_id, $activeCompanyIds), 403);

        $request->validate(['body' => 'required|string|max:5000']);
        DB::transaction(fn () => $department->logComment($request->body));

        return back()->with('success', 'Comment added.');
    }
}
