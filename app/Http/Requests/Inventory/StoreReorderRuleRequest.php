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
        // targets a Company B product/location/warehouse/route would silently
        // build picking flows that cross tenant boundaries the next time the
        // "Replenish" button runs.
        $productRule   = $this->inventoryProductRule($activeCompanyIds);
        $locationRule  = $this->inventoryLocationRule($activeCompanyIds);
        $warehouseRule = $this->companyScopedExists('inventory_warehouses', $activeCompanyIds);
        $routeRule     = $this->companyScopedExists('inventory_routes', $activeCompanyIds, allowNull: true);

        return [
            'company_id'   => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'product_id'   => ['required', $productRule],
            'location_id'  => ['required', $locationRule],
            'warehouse_id' => ['nullable', $warehouseRule],
            'route_id'     => ['nullable', $routeRule],
            'qty_min'      => ['required', 'numeric', 'min:0'],
            'qty_max'      => ['required', 'numeric', 'gte:qty_min'],
            'qty_multiple' => ['nullable', 'numeric', 'min:1'],
            'lead_days'    => ['nullable', 'integer', 'min:0'],
        ];
    }
}
