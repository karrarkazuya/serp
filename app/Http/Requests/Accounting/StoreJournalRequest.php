<?php

namespace App\Http\Requests\Accounting;

use App\Models\Accounting\AccountJournal;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        $companyId = $this->input('company_id');
        $uniqueCode = Rule::unique('account_journals', 'code')->where(fn ($q) => $q->where('company_id', $companyId));

        $accountInCompany = Rule::exists('accounts', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId);
        });

        return [
            'company_id'           => ['required', $companyRule],
            'code'                 => ['required', 'string', 'max:16', $uniqueCode],
            'name'                 => ['required', 'string', 'max:128'],
            'type'                 => ['required', 'string', Rule::in(array_keys(AccountJournal::TYPES))],
            'currency'             => ['nullable', 'string', 'max:10'],
            'default_account_id'   => ['nullable', $accountInCompany],
            'suspense_account_id'  => ['nullable', $accountInCompany],
            'sequence_prefix'      => ['nullable', 'string', 'max:32'],
            'sequence_next_number' => ['nullable', 'integer', 'min:1'],
            'sequence_padding'     => ['nullable', 'integer', 'min:1', 'max:10'],
            'active'               => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'active'          => $this->has('active') ? $this->boolean('active') : true,
            'sequence_prefix' => $this->input('sequence_prefix', ''),
        ]);
    }
}
