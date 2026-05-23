<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCurrencyRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }

    public function rules(): array
    {
        return [
            'currency' => ['required', 'string', 'max:10'],
            'rate'     => ['required', 'numeric', 'gt:0'],
            'date'     => ['required', 'date'],
            'active'   => ['boolean'],
        ];
    }
}
