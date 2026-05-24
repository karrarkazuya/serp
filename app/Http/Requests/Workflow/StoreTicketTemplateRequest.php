<?php

namespace App\Http\Requests\Workflow;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.config.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        // `hr_departments.company_id` is nullable on purpose — shared departments
        // belong to every company. Accept null or one of the actor's active companies.
        $deptRule = Rule::exists('hr_departments', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id');
            if (!empty($activeCompanyIds)) {
                $q->orWhereIn('company_id', $activeCompanyIds);
            }
        });

        return [
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:5000',
            'default_group_id'        => 'nullable|exists:workflow_groups,id,deleted_at,NULL',
            'default_department_id'   => ['nullable', $deptRule],
            'resolve_max_duration'    => 'nullable|integer|min:1',
            'enabled'                 => 'boolean',
            'departments'             => 'nullable|array',
            'departments.*'           => $deptRule,
            'inputs'                  => 'nullable|array',
            'inputs.*.id'             => 'nullable|integer',
            'inputs.*.name'           => 'required_with:inputs.*|string|max:255',
            'inputs.*.type'           => 'required_with:inputs.*|string|in:char,int,float,date,datetime,boolean,select,multiselect,textarea,file,label',
            'inputs.*.is_required'    => 'nullable|boolean',
            'inputs.*.sort_order'     => 'nullable|integer',
            'inputs.*.options'        => 'nullable|string',
        ];
    }
}
