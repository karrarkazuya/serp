<?php

namespace App\Http\Requests\Accounting;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCurrencyRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        return [
            'company_id' => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'currency'   => ['required', 'string', 'max:10'],
            'rate'       => ['required', 'numeric', 'gt:0'],
            'date'       => ['required', 'date'],
            'active'     => ['boolean'],
        ];
    }
}
