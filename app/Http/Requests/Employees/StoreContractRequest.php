<?php

namespace App\Http\Requests\Employees;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContractRequest extends FormRequest
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
            'name'                => 'required|string|max:255',
            'company_id'          => ['nullable', $companyRule],
            'department_id'       => 'nullable|exists:hr_departments,id',
            'job_id'              => 'nullable|exists:hr_jobs,id',
            'resource_calendar_id' => 'nullable|exists:hr_resource_calendars,id',
            'date_start'          => 'nullable|date',
            'date_end'            => 'nullable|date|after_or_equal:date_start',
            'trial_date_start'    => 'nullable|date',
            'trial_date_end'      => 'nullable|date|after_or_equal:trial_date_start',
            'state'               => 'nullable|in:draft,open,close,cancelled',
            'contract_type'       => 'nullable|in:full_time,part_time,temporary,internship,contractor',
            'wage'                => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|max:10',
            'notes'               => 'nullable|string',
            'image'               => 'nullable|image|max:5120',
        ];
    }
}
