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

    /**
     * MC-fix #8: `currency` is IMMUTABLE on update.
     *
     * Changing the currency of an existing rate silently rewrites every
     * historical FX lookup that referenced this row. A rate created for EUR
     * but later repointed to USD would retroactively change the base value
     * of every USD invoice that resolved against that date — without any
     * downstream signal. To "fix" a wrong currency, the user must delete
     * the row and create a new one (which the chatter trail captures).
     *
     * `company_id` is implicitly immutable too — it's not in the rule set
     * and the controller strips it from $data as defense in depth.
     *
     * MC-fix #4: `date` uniqueness check ignores the row being edited so a
     * rate can change its date without colliding with itself.
     */
    public function rules(): array
    {
        $rate = $this->route('currencyRate');

        return [
            'rate'   => ['required', 'numeric', 'gt:0'],
            'date'   => [
                'required',
                'date',
                $rate
                    ? Rule::unique('currency_rates', 'date')
                        ->ignore($rate->id)
                        ->where(fn ($q) => $q
                            ->where('company_id', $rate->company_id)
                            ->where('currency', $rate->currency)
                            ->whereNull('deleted_at'))
                    : 'nullable',
            ],
            'active' => ['boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'date.unique' => __('accounting.val_rate_duplicate'),
        ];
    }
}
