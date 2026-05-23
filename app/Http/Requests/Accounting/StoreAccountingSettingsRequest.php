<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.lock');
    }

    public function rules(): array
    {
        return [
            'accounting_period_lock_date'      => ['nullable', 'date'],
            'accounting_fiscal_year_lock_date' => ['nullable', 'date'],
        ];
    }
}
