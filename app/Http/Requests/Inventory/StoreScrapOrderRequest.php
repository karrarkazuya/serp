<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScrapOrderRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool { return $this->user()->hasPermission('inventory.create'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // Scrap destroys stock value; cross-tenant FKs here would let a user in
        // company A scrap company B's products, lots, or stock at company B's
        // locations. Lock every FK to the actor's active companies.
        $locationRule = $this->inventoryLocationRule($activeCompanyIds);
        $productRule  = $this->inventoryProductRule($activeCompanyIds);
        $lotRule      = $this->companyScopedExists('inventory_lots', $activeCompanyIds);
        $pickingRule  = $this->companyScopedExists('inventory_pickings', $activeCompanyIds);

        return [
            'company_id'        => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'product_id'        => ['required', $productRule],
            'uom_id'            => ['required', 'exists:inventory_uoms,id'],
            'location_id'       => ['required', $locationRule],
            'scrap_location_id' => ['required', $locationRule],
            'lot_id'            => ['nullable', $lotRule],
            'picking_id'        => ['nullable', $pickingRule],
            'scrap_qty'         => ['required', 'numeric', 'min:0.0001'],
            'origin'            => ['nullable', 'string', 'max:128'],
        ];
    }
}
