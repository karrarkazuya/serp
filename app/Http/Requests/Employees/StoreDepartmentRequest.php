<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        return [
            'name'       => 'required|string|max:255',
            'note'       => 'nullable|string',
            'active'     => 'boolean',
            'company_id' => ['nullable', $companyRule],
            'parent_id'  => 'nullable|exists:hr_departments,id',
            'manager_id' => 'nullable|exists:hr_employees,id',
        ];
    }
}
