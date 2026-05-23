<?php

namespace App\Http\Requests\Inventory;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        return [
            'company_id'          => ['nullable', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'category_id'         => ['nullable', 'exists:inventory_product_categories,id'],
            'uom_id'              => ['required', 'exists:inventory_uoms,id'],
            'uom_po_id'           => ['required', 'exists:inventory_uoms,id'],
            'name'                => ['required', 'string', 'max:255'],
            'internal_reference'  => ['nullable', 'string', 'max:128'],
            'barcode'             => ['nullable', 'string', 'max:128'],
            'description'         => ['nullable', 'string'],
            'description_picking' => ['nullable', 'string'],
            'product_type'        => ['required', Rule::in(['storable', 'consumable', 'service'])],
            'tracking'            => ['required', Rule::in(['none', 'lot', 'serial'])],
            'cost'                => ['nullable', 'numeric', 'min:0'],
            'sale_price'          => ['nullable', 'numeric', 'min:0'],
            'weight'              => ['nullable', 'numeric', 'min:0'],
            'volume'              => ['nullable', 'numeric', 'min:0'],
            'routes'              => ['nullable', 'array'],
            'routes.*'            => ['exists:inventory_routes,id'],
            'suppliers'           => ['nullable', 'array'],
            'suppliers.*.partner_id'   => ['nullable', 'exists:contacts,id'],
            'suppliers.*.partner_name' => ['nullable', 'string', 'max:255'],
            'suppliers.*.min_qty'      => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.price'        => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.delay'        => ['nullable', 'integer', 'min:0'],
        ];
    }
}
