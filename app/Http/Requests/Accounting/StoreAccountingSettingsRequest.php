<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAccountingSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.lock');
    }

    public function rules(): array
    {
        // FX gain/loss accounts must belong to the target company. When route
        // binding for {company} is missing (shouldn't happen via the normal
        // route), deny outright to avoid cross-tenant FK injection.
        $company = $this->route('company');
        $accountInCompany = $company
            ? Rule::exists('accounts', 'id')->where(fn ($q) => $q->where('company_id', $company->id)->where('active', true))
            : Rule::exists('accounts', 'id')->where(fn ($q) => $q->whereRaw('1 = 0'));

        return [
            // Existing lock-date fields (unchanged)
            'accounting_period_lock_date'      => ['nullable', 'date'],
            'accounting_fiscal_year_lock_date' => ['nullable', 'date'],

            // MC2 (Odoo parity): per-company FX gain/loss accounts. Required
            // only when cross-currency reconciliation actually fires; unset
            // means "no automatic FX adjustment is posted" — the reconcile
            // will error with a clear setup message. Both must live in the
            // same company so the adjustment move stays self-contained.
            'income_currency_exchange_account_id'  => ['nullable', $accountInCompany],
            'expense_currency_exchange_account_id' => ['nullable', $accountInCompany],

            // MC3: allowed currencies for invoices/bills/payments under this
            // company. Null = unchanged; empty array = "any active currency"
            // (M2M is treated as a positive-list only when non-empty).
            'allowed_currency_ids'   => ['nullable', 'array'],
            'allowed_currency_ids.*' => ['integer', 'exists:currencies,id'],
        ];
    }
}
