<?php

namespace App\Http\Requests\Inventory;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreScrapOrderRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->hasPermission('inventory.create'); }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();
        return [
            'company_id'        => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'product_id'        => ['required', 'exists:inventory_products,id'],
            'uom_id'            => ['required', 'exists:inventory_uoms,id'],
            'location_id'       => ['required', 'exists:inventory_locations,id'],
            'scrap_location_id' => ['required', 'exists:inventory_locations,id'],
            'lot_id'            => ['nullable', 'exists:inventory_lots,id'],
            'picking_id'        => ['nullable', 'exists:inventory_pickings,id'],
            'scrap_qty'         => ['required', 'numeric', 'min:0.0001'],
            'origin'            => ['nullable', 'string', 'max:128'],
        ];
    }
}
