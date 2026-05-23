<?php

namespace App\Http\Requests\Inventory;

use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePickingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.create');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        return [
            'company_id'        => ['required', Rule::exists('companies', 'id')->whereIn('id', $activeCompanyIds)],
            'operation_type_id' => ['required', 'exists:inventory_operation_types,id'],
            'partner_id'        => ['nullable', 'exists:contacts,id'],
            'location_src_id'   => ['required', 'exists:inventory_locations,id'],
            'location_dest_id'  => ['required', 'exists:inventory_locations,id'],
            'scheduled_date'    => ['nullable', 'date'],
            'origin'            => ['nullable', 'string', 'max:128'],
            'note'              => ['nullable', 'string', 'max:512'],
            'moves'             => ['nullable', 'array'],
            'moves.*.product_id'  => ['required_with:moves', 'exists:inventory_products,id'],
            'moves.*.uom_id'      => ['required_with:moves', 'exists:inventory_uoms,id'],
            'moves.*.product_qty' => ['required_with:moves', 'numeric', 'min:0.0001'],
            'moves.*.sequence'    => ['nullable', 'integer'],
        ];
    }
}
