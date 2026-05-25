<?php

namespace App\Http\Requests\Employees;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApproveRejectRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Permission check is on the policy + route middleware; this just
        // validates the shape.
        return true;
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason'   => 'nullable|string|max:2000|required_if:decision,reject',
        ];
    }
}
