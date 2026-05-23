<?php

namespace App\Services\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\InventoryAdjustmentLine;
use App\Models\Inventory\Location;
use App\Models\Inventory\Move;
use App\Models\Inventory\Quant;
use App\Services\Chatter\ChatterService;

class AdjustmentService
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function create(array $data): InventoryAdjustment
    {
        $count = InventoryAdjustment::where('company_id', $data['company_id'])->count() + 1;
        $data['name']  = 'INV/' . date('Y') . '/' . str_pad($count, 5, '0', STR_PAD_LEFT);
        $data['state'] = 'draft';

        $adjustment = InventoryAdjustment::create($data);
        $this->chatterService->logCreated($adjustment, 'Physical Inventory');
        return $adjustment;
    }

    public function startCount(InventoryAdjustment $adjustment): InventoryAdjustment
    {
        if (!$adjustment->isDraft()) return $adjustment;

        // Load current theoretical quantities from quants for all internal locations of the company
        $internalLocationIds = Location::where('company_id', $adjustment->company_id)
            ->where('usage', 'internal')
            ->where('active', true)
            ->pluck('id');

        $quants = Quant::where('company_id', $adjustment->company_id)
            ->whereIn('location_id', $internalLocationIds)
            ->with('product')
            ->get();

        foreach ($quants as $quant) {
            InventoryAdjustmentLine::firstOrCreate(
                [
                    'adjustment_id' => $adjustment->id,
                    'product_id'    => $quant->product_id,
                    'location_id'   => $quant->location_id,
                    'lot_id'        => $quant->lot_id,
                ],
                [
                    'company_id'      => $adjustment->company_id,
                    'inventory_qty'   => $quant->quantity,
                    'theoretical_qty' => $quant->quantity,
                    'difference_qty'  => 0,
                    'created_by'      => auth()->id(),
                    'updated_by'      => auth()->id(),
                ]
            );
        }

        $adjustment->update(['state' => 'in_progress', 'updated_by' => auth()->id()]);
        $this->chatterService->log($adjustment, 'Physical inventory count started.', 'log');
        return $adjustment->fresh();
    }

    public function updateLine(InventoryAdjustmentLine $line, float $inventoryQty): void
    {
        $diff = $inventoryQty - (float) $line->theoretical_qty;
        $line->update([
            'inventory_qty'  => $inventoryQty,
            'difference_qty' => $diff,
            'updated_by'     => auth()->id(),
        ]);
    }

    public function validate(InventoryAdjustment $adjustment): InventoryAdjustment
    {
        if (!$adjustment->isInProgress()) {
            throw new \RuntimeException('Only in-progress adjustments can be validated.');
        }

        // Get the inventory adjustment virtual location
        $adjLocation = Location::where('usage', 'inventory')->whereNull('company_id')->first();
        if (!$adjLocation) {
            throw new \RuntimeException('Inventory Adjustments virtual location not found.');
        }

        foreach ($adjustment->lines()->with('product')->get() as $line) {
            $diff = (float) $line->inventory_qty - (float) $line->theoretical_qty;
            if (abs($diff) < 0.0001) continue;

            // Only storable products have physical stock to adjust
            if (!$line->product?->isStorable()) continue;

            // Lock the quant row to prevent concurrent adjustment races
            $quant = Quant::where('company_id', $adjustment->company_id)
                ->where('product_id', $line->product_id)
                ->where('location_id', $line->location_id)
                ->where('lot_id', $line->lot_id)
                ->lockForUpdate()
                ->first();

            if ($quant) {
                $quant->update(['quantity' => $line->inventory_qty, 'updated_by' => auth()->id()]);
            } else {
                Quant::create([
                    'company_id'  => $adjustment->company_id,
                    'product_id'  => $line->product_id,
                    'location_id' => $line->location_id,
                    'lot_id'      => $line->lot_id,
                    'quantity'    => $line->inventory_qty,
                    'in_date'     => now(),
                    'created_by'  => auth()->id(),
                    'updated_by'  => auth()->id(),
                ]);
            }

            // Create traceability move
            [$src, $dest] = $diff > 0
                ? [$adjLocation->id, $line->location_id]
                : [$line->location_id, $adjLocation->id];

            Move::create([
                'company_id'      => $adjustment->company_id,
                'product_id'      => $line->product_id,
                'uom_id'          => $line->product->uom_id,
                'location_src_id' => $src,
                'location_dest_id' => $dest,
                'name'            => 'Inventory Adjustment: ' . ($line->product->name ?? ''),
                'product_qty'     => abs($diff),
                'qty_done'        => abs($diff),
                'state'           => 'done',
                'date'            => $adjustment->date ?? now()->toDateString(),
                'created_by'      => auth()->id(),
                'updated_by'      => auth()->id(),
            ]);

            $line->update(['updated_by' => auth()->id()]);
        }

        $adjustment->update([
            'state'      => 'done',
            'date'       => $adjustment->date ?? now()->toDateString(),
            'updated_by' => auth()->id(),
        ]);

        $this->chatterService->log($adjustment, 'Physical inventory validated.', 'log');
        return $adjustment->fresh();
    }

    public function delete(InventoryAdjustment $adjustment): void
    {
        if ($adjustment->isDone()) {
            throw new \RuntimeException('Done adjustments cannot be deleted.');
        }
        $adjustment->lines()->delete();
        $this->chatterService->log($adjustment, 'Physical inventory deleted.', 'system');
        $adjustment->delete();
    }
}
