<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLocationRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool { return $this->user()->hasPermission('inventory.config'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        // See StoreLocationRequest for the rationale on the dropped fields.
        return [
            'parent_id'        => ['nullable', $this->inventoryLocationRule($activeCompanyIds)],
            'name'             => ['required', 'string', 'max:255'],
            'usage'            => ['required', Rule::in(['supplier', 'view', 'internal', 'customer', 'inventory', 'production', 'transit'])],
            'scrap_location'   => ['boolean'],
            'return_location'  => ['boolean'],
            'notes'            => ['nullable', 'string', 'max:255'],
        ];
    }
}
