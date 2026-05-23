<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePickingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('inventory.write');
    }

    public function rules(): array
    {
        return [
            'partner_id'      => ['nullable', 'exists:contacts,id'],
            'location_src_id' => ['required', 'exists:inventory_locations,id'],
            'location_dest_id' => ['required', 'exists:inventory_locations,id'],
            'scheduled_date'  => ['nullable', 'date'],
            'origin'          => ['nullable', 'string', 'max:128'],
            'note'            => ['nullable', 'string', 'max:512'],
            'moves'           => ['nullable', 'array'],
            'moves.*.id'          => ['nullable', 'exists:inventory_moves,id'],
            'moves.*.product_id'  => ['required_with:moves', 'exists:inventory_products,id'],
            'moves.*.uom_id'      => ['required_with:moves', 'exists:inventory_uoms,id'],
            'moves.*.product_qty' => ['required_with:moves', 'numeric', 'min:0.0001'],
            'moves.*.qty_done'    => ['nullable', 'numeric', 'min:0'],
            'moves.*.sequence'    => ['nullable', 'integer'],
            'moves.*.delete'      => ['nullable', 'boolean'],
            // Move lines (for lot/serial tracked products)
            'move_lines'           => ['nullable', 'array'],
            'move_lines.*.move_id' => ['required_with:move_lines', 'exists:inventory_moves,id'],
            'move_lines.*.lot_id'  => ['nullable', 'exists:inventory_lots,id'],
            'move_lines.*.lot_name' => ['nullable', 'string', 'max:128'],
            'move_lines.*.qty_done' => ['required_with:move_lines', 'numeric', 'min:0'],
        ];
    }
}
