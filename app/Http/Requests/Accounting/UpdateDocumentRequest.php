<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Validation\Rule;

class UpdateDocumentRequest extends StoreDocumentRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }

    /**
     * Force the request to validate against the existing document's company_id,
     * not whatever the user posts. company_id is immutable on update — without
     * this override, a user editing draft Invoice I (originally in Company A)
     * could submit company_id = B and have all downstream FK validation
     * (journal, account, partner, items.*.account, items.*.tax) re-target
     * B-scoped records. The service would then update the move with
     * company_id = B, breaking the audit trail.
     *
     * The controller also unsets company_id from $data as defense in depth.
     */
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $document = $this->route('invoice')
            ?? $this->route('bill')
            ?? $this->route('creditNote')
            ?? $this->route('refund');

        if ($document) {
            $this->merge(['company_id' => $document->company_id]);
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();

        // Pin the company_id rule so any attempt to post a different value
        // surfaces as a validation error (in addition to prepareForValidation
        // overwriting it before validation runs).
        $document = $this->route('invoice')
            ?? $this->route('bill')
            ?? $this->route('creditNote')
            ?? $this->route('refund');

        if ($document) {
            $rules['company_id'] = ['required', Rule::in([$document->company_id])];
        }

        return $rules;
    }
}
