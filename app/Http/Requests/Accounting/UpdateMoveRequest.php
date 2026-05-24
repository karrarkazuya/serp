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
        // company_id is IMMUTABLE on update — pinned to the existing move's
        // company_id via `Rule::in([$move->company_id])`. The controller also
        // unsets `company_id` from $data as defense in depth. Without these,
        // a user editing draft Move M (originally in Company A) could submit
        // company_id = B and have all FK validation re-target B-scoped
        // accounts/journals/partners; the service would then update the move
        // with company_id = B, breaking audit-trail integrity ("this entry
        // was always in B").
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        $move        = $this->route('move');
        $companyId   = $move?->company_id;
        $companyRule = Rule::in([$companyId]);

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
            'move_type'   => ['nullable', 'string', Rule::in(['entry'])],
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
