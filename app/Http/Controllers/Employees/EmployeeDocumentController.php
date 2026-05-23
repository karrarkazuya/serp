<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeDocument;
use App\Services\Company\CompanyContextService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly FileService $fileService,
    ) {}

    public function store(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'name'               => 'required|string|max:255',
            'document_type'      => 'nullable|in:contract,id_card,passport,certificate,resume,medical,other',
            'issue_date'         => 'nullable|date',
            'expiry_date'        => 'nullable|date',
            'notify_before_days' => 'nullable|integer|min:0|max:365',
            'notes'              => 'nullable|string',
            'file'               => 'nullable|file|max:10240',
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

    public function unlink(Request $_request, Employee $employee, EmployeeDocument $document)
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

    /** Redirects to unified file route; access control is enforced there. */
    public function download(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 403);
        abort_unless($document->file_path, 404);

        return redirect()->route('files.serve', $document->file_path);
    }

    /** Redirects to unified file route; access control is enforced there. */
    public function preview(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        abort_unless($document->employee_id === $employee->id, 403);
        abort_unless($document->file_path, 404);

        return redirect()->route('files.serve', $document->file_path);
    }
}
