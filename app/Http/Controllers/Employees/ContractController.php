<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreContractRequest;
use App\Models\Employees\Contract;
use App\Models\Employees\Employee;
use App\Services\Company\CompanyContextService;
use App\Services\Chatter\ChatterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContractController extends Controller
{
    public function __construct(
        private readonly CompanyContextService $companyContext,
        private readonly ChatterService $chatterService,
    ) {}

    public function store(StoreContractRequest $request, Employee $employee)
    {
        $this->authorize('create', Contract::class);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);

        DB::transaction(function () use ($request, $employee) {
            $data = array_merge($request->validated(), ['employee_id' => $employee->id]);
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $data['image'] = $file->storeAs(
                    'contracts/images',
                    Str::uuid() . '.' . $file->getClientOriginalExtension(),
                    'local'
                );
            }
            $contract = Contract::create($data);
            $this->chatterService->log($employee, "Contract created: {$contract->name}", 'log');
        });

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
                Storage::disk('local')->delete($contract->image);
            }
            $name = $contract->name;
            $contract->delete();
            $this->chatterService->log($employee, "Contract deleted: {$name}", 'system');
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Contract deleted.');
    }

    public function serveImage(Employee $employee, Contract $contract)
    {
        $this->authorize('view', $contract);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(empty($activeCompanyIds) || in_array($employee->company_id, $activeCompanyIds), 403);
        abort_unless($contract->employee_id === $employee->id, 404);
        abort_unless($contract->image && Storage::disk('local')->exists($contract->image), 404);

        return response()->file(Storage::disk('local')->path($contract->image));
    }
}
