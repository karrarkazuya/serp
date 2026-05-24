<?php

namespace App\Http\Requests\Workflow;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.tickets.write');
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
            'name'                       => 'required|string|max:255',
            'description'                => 'nullable|string|max:5000',
            'priority'                   => 'required|in:1,2,3',
            'assigned_to_department_id'  => ['nullable', $deptRule],
            'assigned_to_user_id'        => 'nullable|exists:workflow_users,user_id',
            'inputs'                     => 'nullable|array',
            'inputs.*.template_input_id' => 'required|exists:workflow_template_inputs,id,deleted_at,NULL',
            'inputs.*.value'             => 'nullable|max:5000',
        ];
    }
}
