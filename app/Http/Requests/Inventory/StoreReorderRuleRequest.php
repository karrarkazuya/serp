<?php

namespace App\Http\Requests\Inventory;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReorderRuleRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->hasPermission('inventory.create'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        return [
            'company_id'   => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'product_id'   => ['required', 'exists:inventory_products,id'],
            'location_id'  => ['required', 'exists:inventory_locations,id'],
            'warehouse_id' => ['nullable', 'exists:inventory_warehouses,id'],
            'route_id'     => ['nullable', 'exists:inventory_routes,id'],
            'qty_min'      => ['required', 'numeric', 'min:0'],
            'qty_max'      => ['required', 'numeric', 'gte:qty_min'],
            'qty_multiple' => ['nullable', 'numeric', 'min:1'],
            'lead_days'    => ['nullable', 'integer', 'min:0'],
        ];
    }
}
