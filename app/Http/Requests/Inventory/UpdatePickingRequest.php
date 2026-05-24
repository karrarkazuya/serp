<?php

namespace App\Http\Requests\Inventory;

use App\Http\Requests\Inventory\Concerns\InventoryFkRules;
use App\Services\Company\CompanyContextService;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePickingRequest extends FormRequest
{
    use InventoryFkRules;

    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.write');
    }

    public function rules(): array
    {
        $activeCompanyIds = app(CompanyContextService::class)->getActiveCompanyIds();

        // See StorePickingRequest for the rationale on each scoped FK rule. Same
        // protections apply on update: a user editing an in-flight picking must
        // not be able to swap in a Company B location / product / partner / lot.
        $locationRule = $this->inventoryLocationRule($activeCompanyIds);
        $productRule  = $this->inventoryProductRule($activeCompanyIds);
        $lotRule      = $this->companyScopedExists('inventory_lots', $activeCompanyIds);
        $partnerRule  = $this->contactInActiveCompaniesRule($activeCompanyIds);

        return [
            'partner_id'      => ['nullable', $partnerRule],
            'location_src_id' => ['required', $locationRule],
            'location_dest_id' => ['required', $locationRule],
            'scheduled_date'  => ['nullable', 'date'],
            'origin'          => ['nullable', 'string', 'max:128'],
            'note'            => ['nullable', 'string', 'max:512'],
            'moves'           => ['nullable', 'array'],
            // moves.*.id is gated at the controller via $move->picking_id === $picking->id,
            // but we keep the integer+exists check for cheap input sanity.
            'moves.*.id'          => ['nullable', 'integer', 'exists:inventory_moves,id'],
            'moves.*.product_id'  => ['required_with:moves', $productRule],
            'moves.*.uom_id'      => ['required_with:moves', 'exists:inventory_uoms,id'],
            'moves.*.product_qty' => ['required_with:moves', 'numeric', 'min:0.0001'],
            'moves.*.qty_done'    => ['nullable', 'numeric', 'min:0'],
            'moves.*.sequence'    => ['nullable', 'integer'],
            'moves.*.delete'      => ['nullable', 'boolean'],
            // Move lines (for lot/serial tracked products)
            'move_lines'           => ['nullable', 'array'],
            'move_lines.*.move_id' => ['required_with:move_lines', 'integer', 'exists:inventory_moves,id'],
            'move_lines.*.lot_id'  => ['nullable', $lotRule],
            'move_lines.*.lot_name' => ['nullable', 'string', 'max:128'],
            'move_lines.*.qty_done' => ['required_with:move_lines', 'numeric', 'min:0'],
        ];
    }
}
