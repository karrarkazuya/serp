<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreProcedureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.procedures.create');
    }

    public function rules(): array
    {
        return [
            'procedure_template_id' => 'required|exists:workflow_procedure_templates,id',
            'name'                  => 'required|string|max:255',
            'description'           => 'nullable|string|max:5000',
        ];
    }
}
