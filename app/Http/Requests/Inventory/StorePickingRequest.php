<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePickingRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // Every picking FK below must stay inside the actor's active companies —
        // without these, a user with inventory.create in company A could craft a
        // picking that siphons stock from / into company B's locations, products,
        // and operation types. Stock-theft via cross-tenant FK injection.
        $locationRule = $this->inventoryLocationRule($activeCompanyIds);
        $opTypeRule   = $this->companyScopedExists('inventory_operation_types', $activeCompanyIds);
        $productRule  = $this->inventoryProductRule($activeCompanyIds);
        $partnerRule  = $this->contactInActiveCompaniesRule($activeCompanyIds);

        return [
            'company_id'        => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'operation_type_id' => ['required', $opTypeRule],
            'partner_id'        => ['nullable', $partnerRule],
            'location_src_id'   => ['required', $locationRule],
            'location_dest_id'  => ['required', $locationRule],
            'scheduled_date'    => ['nullable', 'date'],
            'origin'            => ['nullable', 'string', 'max:128'],
            'note'              => ['nullable', 'string', 'max:512'],
            'moves'             => ['nullable', 'array'],
            'moves.*.product_id'  => ['required_with:moves', $productRule],
            'moves.*.uom_id'      => ['required_with:moves', 'exists:inventory_uoms,id'],
            'moves.*.product_qty' => ['required_with:moves', 'numeric', 'min:0.0001'],
            'moves.*.sequence'    => ['nullable', 'integer'],
        ];
    }
}
