<?php

namespace App\Http\Requests\Workflow;

use Illuminate\Foundation\Http\FormRequest;

class StoreTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('workflow.tickets.create');
    }

    public function rules(): array
    {
        return [
            'ticket_template_id' => 'required|exists:workflow_ticket_templates,id',
            'name'               => 'required|string|max:255',
            'description'        => 'nullable|string|max:5000',
            'priority'           => 'required|in:1,2,3',
        ];
    }
}
