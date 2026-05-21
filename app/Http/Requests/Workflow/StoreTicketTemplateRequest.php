<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.config.write');
    }

    public function rules(): array
    {
        return [
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:5000',
            'default_group_id'        => 'nullable|exists:workflow_groups,id',
            'default_department_id'   => 'nullable|exists:hr_departments,id',
            'resolve_max_duration'    => 'nullable|integer|min:1',
            'enabled'                 => 'boolean',
            'departments'             => 'nullable|array',
            'departments.*'           => 'exists:hr_departments,id',
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
