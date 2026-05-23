<?php

namespace App\Http\Requests\Accounting;

use App\Models\Accounting\AccountMove;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $companyRule = Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds);

        $move      = $this->route('move');
        $companyId = $this->input('company_id', $move?->company_id);

        $journalRule = Rule::exists('account_journals', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId)->where('active', true);
        });

        $accountInCompany = Rule::exists('accounts', 'id')->where(function ($q) use ($companyId) {
            empty($companyId) ? $q->whereRaw('1 = 0') : $q->where('company_id', $companyId)->where('active', true);
        });

        $partnerInCompany = Rule::exists('contacts', 'id')->where(function ($q) use ($companyId, $activeCompanyIds) {
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
            'company_id'  => ['required', $companyRule],
            'journal_id'  => ['required', $journalRule],
            'partner_id'  => ['nullable', $partnerInCompany],
            'date'        => ['required', 'date'],
            'ref'         => ['nullable', 'string', 'max:128'],
            'move_type'   => ['nullable', 'string', Rule::in(array_keys(AccountMove::MOVE_TYPES))],
            'currency'    => ['nullable', 'string', 'max:10'],
            'narration'   => ['nullable', 'string', 'max:10000'],

            'lines'                 => ['required', 'array', 'min:2'],
            'lines.*.account_id'    => ['required', $accountInCompany],
            'lines.*.partner_id'    => ['nullable', $partnerInCompany],
            'lines.*.name'          => ['required', 'string', 'max:255'],
            'lines.*.debit'         => ['nullable', 'numeric', 'min:0'],
            'lines.*.credit'        => ['nullable', 'numeric', 'min:0'],
            'lines.*.currency'      => ['nullable', 'string', 'max:10'],
            'lines.*.amount_currency' => ['nullable', 'numeric'],
            'lines.*.sequence'      => ['nullable', 'integer', 'min:0'],

            'action' => ['nullable', 'string', Rule::in(['save', 'post'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $lines = $this->input('lines', []);
        $cleaned = [];
        foreach ($lines as $line) {
            $hasContent = ($line['account_id'] ?? null) !== null
                || ($line['name'] ?? '') !== ''
                || (float) ($line['debit']  ?? 0) !== 0.0
                || (float) ($line['credit'] ?? 0) !== 0.0;
            if ($hasContent) {
                $cleaned[] = $line;
            }
        }
        $this->merge(['lines' => $cleaned]);
    }
}
