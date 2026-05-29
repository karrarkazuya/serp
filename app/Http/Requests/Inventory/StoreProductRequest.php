<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // Supplier contacts must stay in the actor's active companies —
        // without this, a Product in company A could be wired to a Company
        // B contact as supplier.
        //
        // Dropped from the schema: `uom_po_id`, `weight`, `volume`, `routes`.
        // The form no longer renders these (no consumer existed) — the
        // controller defaults uom_po_id to uom_id so the NOT NULL column
        // stays satisfied without user input.
        $partnerRule = $this->contactInActiveCompaniesRule($activeCompanyIds);

        return [
            'company_id'          => ['nullable', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'category_id'         => ['nullable', 'exists:inventory_product_categories,id'],
            'uom_id'              => ['required', 'exists:inventory_uoms,id'],
            'name'                => ['required', 'string', 'max:255'],
            'internal_reference'  => ['nullable', 'string', 'max:128'],
            'barcode'             => ['nullable', 'string', 'max:128'],
            'description'         => ['nullable', 'string'],
            'description_picking' => ['nullable', 'string'],
            'product_type'        => ['required', Rule::in(['storable', 'consumable', 'service'])],
            'tracking'            => ['required', Rule::in(['none', 'lot', 'serial'])],
            'has_expiration_date' => ['nullable', 'boolean'],
            'cost'                => ['nullable', 'numeric', 'min:0'],
            'sale_price'          => ['nullable', 'numeric', 'min:0'],
            'suppliers'           => ['nullable', 'array'],
            'suppliers.*.partner_id'   => ['nullable', $partnerRule],
            'suppliers.*.partner_name' => ['nullable', 'string', 'max:255'],
            'suppliers.*.min_qty'      => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.price'        => ['nullable', 'numeric', 'min:0'],
            'suppliers.*.delay'        => ['nullable', 'integer', 'min:0'],
            // Rule 10: never bare 'image' — SVG XSS. Explicit allowlist of
            // image MIME types/extensions only.
            'image'               => ['nullable', 'file', 'max:5120', 'mimetypes:image/jpeg,image/png,image/gif,image/webp', 'mimes:jpg,jpeg,png,gif,webp'],
        ];
    }
}
