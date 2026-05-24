<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        // `hr_departments.company_id` is nullable on purpose — shared departments
        // belong to every company. Accept null or one of the actor's active companies.
        $deptRule         = Rule::exists('hr_departments', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id');
            if (!empty($activeCompanyIds)) {
                $q->orWhereIn('company_id', $activeCompanyIds);
            }
        });

        return [
            'name'               => 'sometimes|required|string|max:255',
            'description'        => 'nullable|string',
            'requirements'       => 'nullable|string',
            'expected_employees' => 'nullable|integer|min:0',
            'no_of_recruitment'  => 'nullable|integer|min:0',
            'state'              => 'nullable|in:open,recruitment,closed',
            'active'             => 'boolean',
            'company_id'         => ['nullable', $companyRule],
            'department_id'      => ['nullable', $deptRule],
        ];
    }
}
