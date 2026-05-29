<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReorderRuleRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool { return $this->user()->hasPermission('inventory.create'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // Reorder rules drive automatic replenishment pickings. A rule that
        // targets a Company B product/location/warehouse would silently build
        // picking flows that cross tenant boundaries the next time the
        // "Replenish" button runs.
        //
        // `route_id` was previously accepted here as a user-pickable field,
        // but nothing in the replenishment flow ever read it — the receipt
        // OperationType lookup is hardcoded. The form dropdown was removed
        // to stop offering users a control that did nothing. The column
        // stays in the DB so a future route-driven procurement pipeline can
        // adopt it without a schema change.
        $productRule   = $this->inventoryProductRule($activeCompanyIds);
        $locationRule  = $this->inventoryLocationRule($activeCompanyIds);
        $warehouseRule = $this->companyScopedExists('inventory_warehouses', $activeCompanyIds);

        return [
            'company_id'   => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'product_id'   => ['required', $productRule],
            'location_id'  => ['required', $locationRule],
            'warehouse_id' => ['nullable', $warehouseRule],
            'qty_min'      => ['required', 'numeric', 'min:0'],
            'qty_max'      => ['required', 'numeric', 'gte:qty_min'],
            'qty_multiple' => ['nullable', 'numeric', 'min:1'],
            'lead_days'    => ['nullable', 'integer', 'min:0'],
        ];
    }
}
