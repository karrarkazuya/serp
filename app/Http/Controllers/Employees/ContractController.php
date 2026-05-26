<?php

namespace App\Http\Controllers\Employees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Employees\StoreContractRequest;
use App\Models\Employees\Contract;
use App\Models\Employees\Department;
use App\Models\Employees\Employee;
use App\Models\Employees\Job;
use App\Models\Employees\ResourceCalendar;
use App\Models\Settings\Company;
use App\Services\Company\CompanyContextService;
use App\Services\Chatter\ChatterService;
use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

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
        abort_unless(!empty($activeCompanyIds) && in_array($employee->company_id, $activeCompanyIds), 403);

        $fileRecord = null;

        DB::transaction(function () use ($request, $employee, &$fileRecord) {
            $data = array_merge($request->validated(), ['employee_id' => $employee->id]);
            if ($request->hasFile('image')) {
                $fileRecord    = $this->fileService->store($request->file('image'), 'contracts/images', 'employees.read');
                $data['image'] = $fileRecord->uuid;
            }
            $contract = Contract::create($data);
            $fileRecord?->update(['source_type' => $contract->getTable(), 'source_id' => $contract->id]);
            $this->chatterService->log($employee, "Contract created: {$contract->name}", 'log');
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Contract created.');
    }

    public function write(Request $request, Employee $employee, Contract $contract)
    {
        $this->authorize('update', $contract);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($employee->company_id, $activeCompanyIds), 403);

        // Scope every FK rule to the actor's active companies — without this, a user
        // editing a contract for an employee in company A could set department_id /
        // job_id / resource_calendar_id to records belonging to company B (mismatched
        // data, cross-tenant link).
        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $deptRule    = Rule::exists('hr_departments', 'id')->whereIn('company_id', $activeCompanyIds);
        $jobRule     = Rule::exists('hr_jobs', 'id')->whereIn('company_id', $activeCompanyIds);
        // hr_resource_calendars: company_id is nullable (calendars can be shared), so
        // we accept rows that are either in scope OR explicitly cross-company (null).
        $calendarRule = Rule::exists('hr_resource_calendars', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id')->orWhereIn('company_id', $activeCompanyIds);
        });

        $data = $request->validate([
            'name'                 => 'required|string|max:255',
            'department_id'        => ['nullable', $deptRule],
            'job_id'               => ['nullable', $jobRule],
            'company_id'           => ['nullable', $companyRule],
            'resource_calendar_id' => ['nullable', $calendarRule],
            'date_start'           => 'nullable|date',
            'date_end'             => 'nullable|date|after_or_equal:date_start',
            'trial_date_start'     => 'nullable|date',
            'trial_date_end'       => 'nullable|date',
            'state'                => 'nullable|in:draft,open,close,cancelled',
            'contract_type'        => 'nullable|in:full_time,part_time,temporary,internship,contractor',
            'wage'                 => 'nullable|numeric|min:0',
            'currency'             => 'nullable|string|max:10',
            'notes'                => 'nullable|string',
        ]);

        DB::transaction(function () use ($employee, $contract, $data) {
            $changes = $this->detectContractChanges($contract, $data);
            $contract->update($data);
            if (!empty($changes)) {
                $this->chatterService->logUpdated($employee, $changes, "Contract \"{$contract->name}\"");
            }
        });

        return redirect()->route('employees.show', $employee)->with('success', 'Contract updated.');
    }

    public function setActive(Request $_request, Employee $employee, Contract $contract)
    {
        $this->authorize('update', $contract);

        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($employee->company_id, $activeCompanyIds), 403);

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
        abort_unless(!empty($activeCompanyIds) && in_array($employee->company_id, $activeCompanyIds), 403);

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

    private function detectContractChanges(Contract $contract, array $data): array
    {
        $changes = [];
        foreach ($contract->chatterTracked as $field => $label) {
            if (!array_key_exists($field, $data)) continue;
            $old = (string) ($contract->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;
            $changes[] = [
                'field' => $field,
                'label' => $label,
                'from'  => $this->resolveContractValue($field, $contract->{$field}),
                'to'    => $this->resolveContractValue($field, $data[$field]),
            ];
        }
        return $changes;
    }

    private function resolveContractValue(string $field, mixed $value): string
    {
        if ($value === null || $value === '') return '—';
        return match ($field) {
            'department_id'        => Department::find($value)?->name ?? "#{$value}",
            'job_id'               => Job::find($value)?->name ?? "#{$value}",
            'company_id'           => Company::find($value)?->name ?? "#{$value}",
            'resource_calendar_id' => ResourceCalendar::find($value)?->name ?? "#{$value}",
            default                => (string) $value,
        };
    }

    /** Kept for backward compat; redirects to unified file route. */
    public function serveImage(Employee $employee, Contract $contract)
    {
        abort_unless($contract->employee_id === $employee->id, 404);
        abort_unless($contract->image, 404);

        // Without the company gate, any `employees.read` holder could iterate URL
        // pairs (/employees/{id}/contracts/{contract}/image) and pull contract
        // images for employees in companies they have no legitimate access to.
        $activeCompanyIds = $this->companyContext->getActiveCompanyIds();
        abort_unless(!empty($activeCompanyIds) && in_array($employee->company_id, $activeCompanyIds), 403);

        return redirect()->route('files.serve', $contract->image);
    }
}
