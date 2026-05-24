<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeDocument;
use App\Services\Company\CompanyContextService;
use App\Services\FileService;
use App\Helpers\SearchFilters;
use App\Helpers\SortsTable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly FileService $fileService,
    ) {}

    // ── Standalone CRUD ──────────────────────────────────────────────────────

    public function read(Request $request)
    {
        $this->authorize('viewAny', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        $query = EmployeeDocument::query()->with(['employee.company']);

        if (!empty($activeCompanyIds)) {
            $query->whereHas('employee', fn ($q) => $q->whereIn('company_id', $activeCompanyIds));
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

        $documents = $query->paginate(50)->withQueryString();

        return view('employees.documents.index', compact('documents'));
    }

    public function show(EmployeeDocument $document)
    {
        $this->authorize('viewAny', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($document->employee?->company_id, $activeCompanyIds),
            403
        );

        $document->load(['employee.company', 'creator', 'updater', 'attachedFile']);

        return view('employees.documents.show', compact('document'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        $preselectedEmployee = null;
        if ($request->query('employee_id')) {
            $preselectedEmployee = Employee::find((int) $request->query('employee_id'));
            if ($preselectedEmployee && !empty($activeCompanyIds)) {
                abort_unless(in_array($preselectedEmployee->company_id, $activeCompanyIds), 403);
            }
        }

        return view('employees.documents.create', compact('preselectedEmployee'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();

        // Scope employee_id at the validation layer (not after findOrFail) so the
        // company gate doesn't disappear if the post-find check is ever refactored
        // away. An empty activeCompanyIds array means "no allowed companies", which
        // we translate to "deny all employee_id values" — matching how list pages
        // hide everything in the same state.
        $employeeRule = \Illuminate\Validation\Rule::exists('hr_employees', 'id')
            ->where(fn ($q) => empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds));

        $data = $request->validate([
            'employee_id'             => ['required', $employeeRule],
            'name'                    => 'required|string|max:255',
            'document_type'           => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issued_by'               => 'nullable|string|max:255',
            'document_number'         => 'nullable|string|max:255',
            'organizational_structure'=> 'nullable|string|max:255',
            'issue_date'              => 'nullable|date',
            'expiry_date'             => 'nullable|date',
            'notify_before_days'      => 'nullable|integer|min:0|max:365',
            'notes'                   => 'nullable|string',
            'file'                    => 'nullable|file|max:10240',
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        $fileRecord = null;
        if ($request->hasFile('file')) {
            $fileRecord       = $this->fileService->store($request->file('file'), 'documents/employees/' . $employee->id, 'employees.read');
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);
        $data['document_type'] ??= 'other';

        $document = DB::transaction(function () use ($data, $fileRecord) {
            $document = EmployeeDocument::create($data);
            $fileRecord?->update(['source_type' => $document->getTable(), 'source_id' => $document->id]);
            return $document;
        });

        return redirect()->route('employees.documents.show', $document)->with('success', 'Document created.');
    }

    public function edit(EmployeeDocument $document)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($document->employee?->company_id, $activeCompanyIds),
            403
        );

        $document->load(['employee', 'attachedFile']);

        return view('employees.documents.edit', compact('document'));
    }

    public function write(Request $request, EmployeeDocument $document)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($document->employee?->company_id, $activeCompanyIds),
            403
        );

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'document_type'           => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issued_by'               => 'nullable|string|max:255',
            'document_number'         => 'nullable|string|max:255',
            'organizational_structure'=> 'nullable|string|max:255',
            'issue_date'              => 'nullable|date',
            'expiry_date'             => 'nullable|date',
            'notify_before_days'      => 'nullable|integer|min:0|max:365',
            'notes'                   => 'nullable|string',
            'file'                    => 'nullable|file|max:10240',
        ]);

        $fileRecord = null;
        if ($request->hasFile('file')) {
            if ($document->file_path) {
                $this->fileService->deleteByUuid($document->file_path);
            }
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/employees/' . $document->employee_id, 'employees.read', null, $document);
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);

        DB::transaction(fn () => $document->update($data));

        return redirect()->route('employees.documents.show', $document)->with('success', 'Document updated.');
    }

    public function archive(Request $request, EmployeeDocument $document)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($document->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(fn () => $document->update(['active' => false]));

        return redirect()->route('employees.documents.index')->with('success', 'Document archived.');
    }

    public function unarchive(Request $request, EmployeeDocument $document)
    {
        $this->authorize('update', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($document->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(fn () => $document->update(['active' => true]));

        return redirect()->route('employees.documents.show', $document)->with('success', 'Document restored.');
    }

    public function unlink(EmployeeDocument $document)
    {
        $this->authorize('delete', Employee::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(
            empty($activeCompanyIds) || in_array($document->employee?->company_id, $activeCompanyIds),
            403
        );

        DB::transaction(function () use ($document) {
            if ($document->file_path) {
                $this->fileService->deleteByUuid($document->file_path);
            }
            $document->delete();
        });

        return redirect()->route('employees.documents.index')->with('success', 'Document deleted.');
    }

    // ── Inline helpers (used from employee edit page) ─────────────────────────

    public function employeeStore(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'document_type'           => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issued_by'               => 'nullable|string|max:255',
            'document_number'         => 'nullable|string|max:255',
            'organizational_structure'=> 'nullable|string|max:255',
            'issue_date'              => 'nullable|date',
            'expiry_date'             => 'nullable|date',
            'notify_before_days'      => 'nullable|integer|min:0|max:365',
            'notes'                   => 'nullable|string',
            'file'                    => 'nullable|file|max:10240',
        ]);

        $fileRecord = null;
        if ($request->hasFile('file')) {
            $fileRecord        = $this->fileService->store($request->file('file'), 'documents/employees/' . $employee->id, 'employees.read');
            $data['file_path'] = $fileRecord->uuid;
        }
        unset($data['file']);
        $data['employee_id']   = $employee->id;
        $data['document_type'] ??= 'other';

        DB::transaction(function () use ($data, $fileRecord) {
            $document = EmployeeDocument::create($data);
            $fileRecord?->update(['source_type' => $document->getTable(), 'source_id' => $document->id]);
        });

        return redirect()->route('employees.edit', $employee)->with('success', 'Document added.');
    }

    public function employeeUnlink(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        $this->authorize('update', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);
        abort_unless($document->employee_id === $employee->id, 403);

        DB::transaction(function () use ($document) {
            if ($document->file_path) {
                $this->fileService->deleteByUuid($document->file_path);
            }
            $document->delete();
        });

        return redirect()->back()->with('success', 'Document deleted.');
    }

    public function download(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        $this->assertCanReachEmployeeDocument($employee, $document);
        abort_unless($document->file_path, 404);

        return redirect()->route('files.serve', $document->file_path);
    }

    public function preview(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        $this->assertCanReachEmployeeDocument($employee, $document);
        abort_unless($document->file_path, 404);

        return redirect()->route('files.serve', $document->file_path);
    }

    /**
     * Gate both the parent-child relation AND the actor's company access. Without
     * the second check, any holder of `employees.read` could iterate URL pairs
     * `/employees/{id}/documents/{doc}/download` and pull HR documents (contracts,
     * IDs, passports, medical files) for employees in companies they have no
     * legitimate access to.
     */
    private function assertCanReachEmployeeDocument(Employee $employee, EmployeeDocument $document): void
    {
        abort_unless($document->employee_id === $employee->id, 403);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);
    }
}
