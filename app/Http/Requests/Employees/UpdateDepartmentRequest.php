<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('employees.write');
    }

    public function rules(): array
    {
        $dept             = $this->route('department');
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule      = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        return [
            'name'       => 'sometimes|required|string|max:255',
            'note'       => 'nullable|string',
            'active'     => 'boolean',
            'company_id' => ['nullable', $companyRule],
            'parent_id'  => ['nullable', Rule::exists('hr_departments', 'id')->whereNot('id', $dept->id)],
            'manager_id' => 'nullable|exists:hr_employees,id',
        ];
    }
}
