<?php

namespace App\Http\Requests\Accounting;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTaxRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyId        = $this->input('company_id');

        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $accountRule = Rule::exists('accounts', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId)->where('active', true);
        });

        return [
            'company_id'          => ['required', $companyRule],
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
