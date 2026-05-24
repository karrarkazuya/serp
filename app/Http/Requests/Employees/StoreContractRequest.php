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
        // `hr_departments.company_id` is nullable on purpose — shared departments
        // belong to every company. Accept null or one of the actor's active companies.
        $deptRule         = Rule::exists('hr_departments', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id');
            if (!empty($activeCompanyIds)) {
                $q->orWhereIn('company_id', $activeCompanyIds);
            }
        });
        // hr_jobs are company-scoped.
        $jobRule          = Rule::exists('hr_jobs', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });

        return [
            'name'                => 'required|string|max:255',
            'company_id'          => ['nullable', $companyRule],
            'department_id'       => ['nullable', $deptRule],
            'job_id'              => ['nullable', $jobRule],
            'resource_calendar_id' => ['nullable', Rule::exists('hr_resource_calendars', 'id')->where(function ($q) use ($activeCompanyIds) {
                $q->whereNull('company_id');
                if (!empty($activeCompanyIds)) {
                    $q->orWhereIn('company_id', $activeCompanyIds);
                }
            })],
            'date_start'          => 'nullable|date',
            'date_end'            => 'nullable|date|after_or_equal:date_start',
            'trial_date_start'    => 'nullable|date',
            'trial_date_end'      => 'nullable|date|after_or_equal:trial_date_start',
            'state'               => 'nullable|in:draft,open,close,cancelled',
            'contract_type'       => 'nullable|in:full_time,part_time,temporary,internship,contractor',
            'wage'                => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|max:10',
            'notes'               => 'nullable|string',
            'image'               => 'nullable|file|max:5120|mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp',
        ];
    }
}
