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
        // `hr_departments.company_id` is nullable on purpose — shared departments
        // belong to every company. Accept null or one of the actor's active companies,
        // excluding self to prevent A→A loops.
        $parentRule       = Rule::exists('hr_departments', 'id')
            ->whereNot('id', $dept->id)
            ->where(function ($q) use ($activeCompanyIds) {
                $q->whereNull('company_id');
                if (!empty($activeCompanyIds)) {
                    $q->orWhereIn('company_id', $activeCompanyIds);
                }
            });
        // Managers are real employees and always belong to a single company.
        $empRule          = Rule::exists('hr_employees', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });

        return [
            'name'       => 'sometimes|required|string|max:255',
            'note'       => 'nullable|string',
            'active'     => 'boolean',
            'company_id' => ['nullable', $companyRule],
            'parent_id'  => ['nullable', $parentRule],
            'manager_id' => ['nullable', $empRule],
        ];
    }
}
