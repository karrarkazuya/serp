<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Validation\Rule;

/**
 * Inherits StoreMoveRequest's rules + withValidator (allowed-currency gate
 * on header and per-line). Pins company_id to the existing move so a user
 * editing draft Move M (originally in Company A) cannot reroute downstream
 * FK validation to Company B's records by posting `company_id = B`.
 *
 * Previously this class was a near-duplicate of StoreMoveRequest WITHOUT
 * the withValidator currency check, meaning a draft move could be edited
 * out of its allowed currency set or even into a journal-pin violation
 * — see CurrencyAuditTest::test_update_move_blocks_disallowed_currency.
 */
class UpdateMoveRequest extends StoreMoveRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // company_id is IMMUTABLE on update — pinned to the existing move's
        // company_id via Rule::in([...]). The service layer also strips
        // company_id from $data as defense in depth.
        $move = $this->route('move');
        if ($move) {
            $rules['company_id'] = ['required', Rule::in([$move->company_id])];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        // Pin company_id BEFORE rules() resolves so all the FK exists-rules
        // (journal, account, partner, etc.) re-target the original company.
        $move = $this->route('move');
        if ($move) {
            $this->merge(['company_id' => $move->company_id]);
        }
    }
}
