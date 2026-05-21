<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeDocument;
use App\Services\Company\CompanyContextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EmployeeDocumentController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
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

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $data['file_path'] = $file->storeAs(
                'documents/employees/' . $employee->id,
                Str::uuid() . '.' . $file->getClientOriginalExtension(),
                'local'
            );
        }
        unset($data['file']);
        $data['employee_id']   = $employee->id;
        $data['document_type'] ??= 'other';

        DB::transaction(fn () => EmployeeDocument::create($data));

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
                Storage::disk('local')->delete($document->file_path);
            }
            $document->delete();
        });

        return redirect()->back()->with('success', 'Document deleted.');
    }

    public function download(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        $this->authorize('view', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);
        abort_unless($document->employee_id === $employee->id, 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return response()->download(
            Storage::disk('local')->path($document->file_path),
            $document->name . '.' . pathinfo($document->file_path, PATHINFO_EXTENSION)
        );
    }

    public function preview(Request $_request, Employee $employee, EmployeeDocument $document)
    {
        $this->authorize('view', $employee);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);
        abort_unless($document->employee_id === $employee->id, 403);
        abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);

        return response()->file(Storage::disk('local')->path($document->file_path));
    }
}
