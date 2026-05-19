<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.config.write');
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'company_id' => 'nullable|exists:companies,id',
            'active'     => 'boolean',
        ];
    }
}
