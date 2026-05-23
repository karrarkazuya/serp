<?php

namespace App\Http\Requests\Accounting;

use App\Models\Accounting\Account;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $accountInCompany = Rule::exists('accounts', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds) ? $q->whereRaw('1 = 0') : $q->whereIn('company_id', $activeCompanyIds);
        });

        $companyId = $this->input('company_id');
        $uniqueCode = Rule::unique('accounts', 'code')->where(fn ($q) => $q->where('company_id', $companyId));

        return [
            'company_id'    => ['required', $companyRule],
            'parent_id'     => ['nullable', $accountInCompany],
            'code'          => ['required', 'string', 'max:32', $uniqueCode],
            'name'          => ['required', 'string', 'max:255'],
            'account_type'  => ['required', 'string', Rule::in(array_keys(Account::TYPES))],
            'currency'      => ['nullable', 'string', 'max:10'],
            'reconcile'     => ['nullable', 'boolean'],
            'notes'         => ['nullable', 'string', 'max:10000'],
            'active'        => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'reconcile' => $this->boolean('reconcile'),
            'active'    => $this->has('active') ? $this->boolean('active') : true,
        ]);
    }
}
