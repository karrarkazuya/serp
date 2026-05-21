<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreContractRequest;
use App\Models\Employees\Contract;
use App\Models\Employees\Employee;
use App\Services\Company\CompanyContextService;
use App\Services\Chatter\ChatterService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContractController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ChatterService $chatterService,
        private readonly FileService $fileService,
    ) {}

    public function store(StoreContractRequest $request, Employee $employee)
    {
        $this->authorize('create', Contract::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $fileRecord = null;

        $contract = DB::transaction(function () use ($request, $employee, &$fileRecord) {
            $data = array_merge($request->validated(), ['employee_id' => $employee->id]);
            if ($request->hasFile('image')) {
                $fileRecord    = $this->fileService->store($request->file('image'), 'contracts/images', 'employees.read');
                $data['image'] = $fileRecord->uuid;
            }
            $contract = Contract::create($data);
            $this->chatterService->log($employee, "Contract created: {$contract->name}", 'log');
            return $contract;
        });

        $fileRecord?->update(['source_type' => $contract->getTable(), 'source_id' => $contract->id]);

        return redirect()->route('employees.show', $employee)->with('success', 'Contract created.');
    }

    public function write(Request $request, Employee $employee, Contract $contract)
    {
        $this->authorize('update', $contract);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'department_id' => 'nullable|exists:hr_departments,id',
            'job_id'        => 'nullable|exists:hr_jobs,id',
            'date_start'    => 'nullable|date',
            'date_end'      => 'nullable|date|after_or_equal:date_start',
            'state'         => 'nullable|in:draft,open,close,cancelled',
            'contract_type' => 'nullable|in:full_time,part_time,temporary,internship,contractor',
            'wage'          => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        DB::transaction(function () use ($employee, $contract, $data) {
            $changes = [];
            foreach (['name', 'state', 'contract_type', 'wage', 'date_start', 'date_end'] as $field) {
                if (isset($data[$field]) && (string) $contract->{$field} !== (string) $data[$field]) {
                    $changes[] = ucwords(str_replace('_', ' ', $field))
                        . ': ' . ($contract->{$field} ?? '—')
                        . ' → ' . ($data[$field] ?? '—');
                }
            }
            $contract->update($data);
            if (!empty($changes)) {
                $this->chatterService->log(
                    $employee,
                    "Contract \"{$contract->name}\" updated: " . implode('; ', $changes),
                    'log'
                );
            }
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Contract updated.');
    }

    public function setActive(Request $_request, Employee $employee, Contract $contract)
    {
        $this->authorize('update', $contract);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        DB::transaction(function () use ($employee, $contract) {
            $employee->update(['contract_id' => $contract->id, 'wage' => $contract->wage]);
            $this->chatterService->log($employee, "Current contract set to: {$contract->name}", 'log');
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Active contract set.');
    }

    public function unlink(Request $_request, Employee $employee, Contract $contract)
    {
        $this->authorize('delete', $contract);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        DB::transaction(function () use ($employee, $contract) {
            if ($employee->contract_id === $contract->id) {
                $employee->update(['contract_id' => null]);
            }
            if ($contract->image) {
                $this->fileService->deleteByUuid($contract->image);
            }
            $name = $contract->name;
            $contract->delete();
            $this->chatterService->log($employee, "Contract deleted: {$name}", 'system');
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Contract deleted.');
    }

    /** Kept for backward compat; redirects to unified file route. */
    public function serveImage(Employee $employee, Contract $contract)
    {
        abort_unless($contract->employee_id === $employee->id, 404);
        abort_unless($contract->image, 404);

        return redirect()->route('files.serve', $contract->image);
    }
}
