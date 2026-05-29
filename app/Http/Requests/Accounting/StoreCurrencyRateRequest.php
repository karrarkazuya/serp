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
        $companyId        = $this->input('company_id');

        return [
            'company_id' => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            // MC-fix #4: bind `currency` to the seeded `currencies` table
            // (code-keyed). Previously any free-form 10-char string was
            // accepted, so typos like `EURO` or `eur` saved rates that
            // `getExchangeRate` would never match. The exists() rule
            // forces the picker to a real ISO code.
            'currency'   => ['required', 'string', 'max:10', Rule::exists('currencies', 'code')],
            'rate'       => ['required', 'numeric', 'gt:0'],
            // MC-fix #4: matching uniqueness check at the request layer so
            // the user gets a friendly validation error rather than a raw
            // SQL constraint violation when colliding with an existing
            // (company, currency, date) row. The DB unique index is the
            // ultimate guarantee — this just produces a nicer message.
            'date'       => [
                'required',
                'date',
                Rule::unique('currency_rates', 'date')
                    ->where(fn ($q) => $q
                        ->where('company_id', $companyId)
                        ->where('currency', $this->input('currency'))
                        ->whereNull('deleted_at')),
            ],
            'active'     => ['boolean'],
        ];
    }

    /**
     * Human-readable error for the unique-on-date collision (otherwise
     * Laravel says "The date has already been taken" which is misleading —
     * the date is fine, the (currency, date) tuple is the conflict).
     */
    public function messages(): array
    {
        return [
            'date.unique'    => __('accounting.val_rate_duplicate'),
            'currency.exists' => __('accounting.val_currency_unknown'),
        ];
    }
}
