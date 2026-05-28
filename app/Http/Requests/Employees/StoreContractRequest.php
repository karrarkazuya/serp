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
            // Contract dates bounded ±20 years past to +50 years future —
            // covers historical contract imports and long fixed-term contracts
            // that point to retirement. Year-9999 entries break tenure /
            // expiry-notification queries.
            'date_start'          => 'nullable|date|after_or_equal:-20 years|before_or_equal:+50 years',
            'date_end'            => 'nullable|date|after_or_equal:date_start|before_or_equal:+50 years',
            'trial_date_start'    => 'nullable|date|after_or_equal:-20 years|before_or_equal:+5 years',
            'trial_date_end'      => 'nullable|date|after_or_equal:trial_date_start|before_or_equal:+5 years',
            'state'               => 'nullable|in:draft,open,close,cancelled',
            'contract_type'       => 'nullable|in:full_time,part_time,temporary,internship,contractor',
            'wage'                => 'nullable|numeric|min:0',
            'currency'            => 'nullable|string|max:10',
            'notes'               => 'nullable|string',
            'image'               => 'nullable|file|max:5120|mimetypes:image/jpeg,image/png,image/gif,image/webp|mimes:jpg,jpeg,png,gif,webp',
        ];
    }
}
