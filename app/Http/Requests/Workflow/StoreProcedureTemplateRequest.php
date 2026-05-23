<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.config.write');
    }

    public function rules(): array
    {
        return [
            'name'                 => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            'default_group_id'     => 'nullable|exists:workflow_groups,id',
            'creator_see_tasks'    => 'boolean',
            'enabled'              => 'boolean',
            'departments'          => 'nullable|array',
            'departments.*'        => 'exists:hr_departments,id',
        ];
    }
}
