<?php

namespace App\Http\Requests\Workflow;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcedureTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.config.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        // `hr_departments.company_id` is nullable on purpose — shared departments
        // (cross-company HR units) belong to every company. Accept null or one of
        // the actor's active companies.
        $deptRule = Rule::exists('hr_departments', 'id')->where(function ($q) use ($activeCompanyIds) {
            $q->whereNull('company_id');
            if (!empty($activeCompanyIds)) {
                $q->orWhereIn('company_id', $activeCompanyIds);
            }
        });

        return [
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            'default_group_id'     => 'nullable|exists:workflow_groups,id,deleted_at,NULL',
            'creator_see_tasks'    => 'boolean',
            'enabled'              => 'boolean',
            'departments'          => 'nullable|array',
            'departments.*'        => $deptRule,
        ];
    }
}
