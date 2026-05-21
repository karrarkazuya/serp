<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.tickets.write');
    }

    public function rules(): array
    {
        return [
            'name'                       => 'required|string|max:255',
            'description'                => 'nullable|string|max:5000',
            'priority'                   => 'required|in:1,2,3',
            'assigned_to_department_id'  => 'nullable|exists:workflow_departments,id',
            'assigned_to_user_id'        => 'nullable|exists:workflow_users,user_id',
            'inputs'                     => 'nullable|array',
            'inputs.*.template_input_id' => 'required|exists:workflow_template_inputs,id',
            'inputs.*.value'             => 'nullable|max:5000',
        ];
    }
}
