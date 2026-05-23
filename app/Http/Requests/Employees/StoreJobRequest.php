<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        return [
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string',
            'requirements'       => 'nullable|string',
            'expected_employees' => 'nullable|integer|min:0',
            'no_of_recruitment'  => 'nullable|integer|min:0',
            'state'              => 'nullable|in:open,recruitment,closed',
            'active'             => 'boolean',
            'company_id'         => ['nullable', $companyRule],
            'department_id'      => 'nullable|exists:hr_departments,id',
        ];
    }
}
