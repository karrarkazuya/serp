<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool { return $this->user()->hasPermission('inventory.config'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        // `removal_strategy`, `barcode`, `posx/y/z` were validated here but
        // no form sends them and no Location-level code reads them — see the
        // Location model for the full rationale. Dropped from validation so
        // the contract matches the form actually rendered.
        return [
            'company_id'       => ['nullable', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'parent_id'        => ['nullable', $this->inventoryLocationRule($activeCompanyIds)],
            'name'             => ['required', 'string', 'max:255'],
            'usage'            => ['required', Rule::in(['supplier', 'view', 'internal', 'customer', 'inventory', 'production', 'transit'])],
            'scrap_location'   => ['boolean'],
            'return_location'  => ['boolean'],
            'notes'            => ['nullable', 'string', 'max:255'],
        ];
    }
}
