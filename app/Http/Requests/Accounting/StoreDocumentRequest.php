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
            'date' => ['required', 'date'],
            'invoice_date_due' => ['nullable', 'date'],
            'payment_term_id' => ['nullable', Rule::exists('accounting_payment_terms', 'id')->where('company_id', $companyId)->where('active', true)],
            'incoterm_id' => ['nullable', Rule::exists('accounting_incoterms', 'id')],
            'invoice_origin' => ['nullable', 'string', 'max:128'],
            'ref' => ['nullable', 'string', 'max:128'],
            'move_type' => ['required', Rule::in(['out_invoice', 'in_invoice'])],
            'currency' => ['nullable', 'string', 'max:10'],
            'narration' => ['nullable', 'string', 'max:10000'],
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
}
