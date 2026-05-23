<?php

namespace App\Http\Requests\Accounting;

class UpdateDocumentRequest extends StoreDocumentRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('accounting.write');
    }
}
