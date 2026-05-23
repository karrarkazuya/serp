<?php

namespace App\Http\Requests\Accounting;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyId        = $this->route('tax')?->company_id;

        $accountRule = Rule::exists('accounts', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId)->where('active', true);
        });

        return [
            'name'                => ['required', 'string', 'max:255'],
            'amount_type'         => ['required', Rule::in(['percent', 'fixed'])],
            'amount'              => ['required', 'numeric', 'min:0'],
            'type_tax_use'        => ['required', Rule::in(['sale', 'purchase', 'none'])],
            'account_id'          => ['nullable', $accountRule],
            'description'         => ['nullable', 'string', 'max:500'],
            'include_base_amount' => ['boolean'],
            'active'              => ['boolean'],
        ];
    }
}
