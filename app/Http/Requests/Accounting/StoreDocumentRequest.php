<?php

namespace App\Http\Requests\Accounting;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyId = $this->input('company_id');

        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);
        $journalRule = Rule::exists('account_journals', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId)->where('active', true);
        });
        $accountRule = Rule::exists('accounts', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId)->where('active', true);
        });
        $partnerRule = Rule::exists('contacts', 'id')->where(function ($q) use ($companyId, $activeCompanyIds) {
            $q->where(function ($inner) use ($companyId) {
                $inner->where('company_id', $companyId)->orWhereNull('company_id');
            });
            if (!empty($activeCompanyIds)) {
                $q->where(function ($inner) use ($activeCompanyIds) {
                    $inner->whereIn('company_id', $activeCompanyIds)->orWhereNull('company_id');
                });
            }
        });

        return [
            'company_id' => ['required', $companyRule],
            'journal_id' => ['required', $journalRule],
            'partner_id' => ['required', $partnerRule],
            'control_account_id' => ['required', $accountRule],
            // Odoo parity (O1): `date` is the accounting / posting date used by
            // period locks, tax periods, and ledger reports. `invoice_date` is
            // the commercial date that appears on the customer-facing PDF and
            // anchors the payment-term due-date computation. They commonly
            // differ (e.g. invoice dated Jan 15, posted Feb 1 in Jan's close).
            // Bound document dates. 20 years past covers historical imports;
            // 1 year future covers post-dated docs and timezone wiggle. Year
            // 9999 entries are almost always mistakes (or attacks) and skew
            // period reports, FX rate lookups, and aging buckets.
            'date'             => ['required', 'date', 'after_or_equal:-20 years', 'before_or_equal:+1 year'],
            'invoice_date'     => ['nullable', 'date', 'after_or_equal:-20 years', 'before_or_equal:+1 year'],
            'invoice_date_due' => ['nullable', 'date', 'before_or_equal:+20 years'],
            'payment_term_id' => ['nullable', Rule::exists('accounting_payment_terms', 'id')->where('company_id', $companyId)->where('active', true)],
            'incoterm_id' => ['nullable', Rule::exists('accounting_incoterms', 'id')],
            'invoice_origin' => ['nullable', 'string', 'max:128'],
            'ref' => ['nullable', 'string', 'max:128'],
            'move_type' => ['required', Rule::in(['out_invoice', 'in_invoice', 'out_refund', 'in_refund'])],
            'currency' => ['nullable', 'string', 'max:10'],
            'narration' => ['nullable', 'string', 'max:10000'],
            'name'   => ['nullable', 'string', 'max:191'],
            'action' => ['nullable', 'string', Rule::in(['save', 'post'])],

            'items' => ['required', 'array', 'min:1'],
            'items.*.account_id' => ['required', $accountRule],
            'items.*.product_id' => [
                'nullable',
                Rule::exists('inventory_products', 'id')->where('company_id', $companyId)->where('active', true),
            ],
            'items.*.uom_id' => [
                'nullable',
                Rule::exists('inventory_uoms', 'id')->where('active', true),
            ],
            'items.*.name' => ['nullable', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.price_unit' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.tax_ids' => ['nullable', 'array'],
            'items.*.tax_ids.*' => ['nullable', 'integer', Rule::exists('account_taxes', 'id')->where('company_id', $companyId)->where('active', true)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $items = [];
        foreach ($this->input('items', []) as $item) {
            $hasContent = ($item['account_id'] ?? null) !== null
                || trim((string) ($item['name'] ?? '')) !== ''
                || (float) ($item['quantity'] ?? 0) !== 0.0
                || (float) ($item['price_unit'] ?? 0) !== 0.0;

            if ($hasContent) {
                $items[] = $item;
            }
        }

        $this->merge(['items' => $items]);
    }

    /**
     * Cross-field checks that the per-field rules can't express on their own:
     * - `invoice_date_due` must be on or after `invoice_date` (and falls back
     *   to `date` when `invoice_date` is missing). Otherwise the invoice
     *   posts as already-overdue from day 1, which silently corrupts aging
     *   buckets and the partner ledger.
     * - `currency` must be one of the company's configured allowedCurrencies
     *   (when the company has restricted the list). Empty list = no restriction,
     *   matching the model's documented "all active currencies" behaviour.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $anchor = $this->input('invoice_date') ?: $this->input('date');
            $due    = $this->input('invoice_date_due');
            if ($anchor && $due && strtotime($due) < strtotime($anchor)) {
                $validator->errors()->add(
                    'invoice_date_due',
                    __('accounting.val_due_before_invoice')
                );
            }

            $currency  = $this->input('currency');
            $companyId = $this->input('company_id');
            if ($currency && $companyId) {
                $company = \App\Models\Settings\Company::find($companyId);
                if ($company) {
                    $allowed = $company->allowedCurrencies()->pluck('code')->all();
                    if (!empty($allowed) && !in_array($currency, $allowed, true)) {
                        $validator->errors()->add(
                            'currency',
                            __('accounting.val_currency_not_allowed')
                        );
                    }
                }
            }
        });
    }
}
