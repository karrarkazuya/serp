<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Move;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ScrapOrder;
use App\Services\Chatter\ChatterService;

class ScrapService
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function create(array $data): ScrapOrder
    {
        // Serialize concurrent creates per company so two callers don't both
        // compute count+1 and hand out the same name. Locking the latest row
        // for this company forces the second writer to wait for the first
        // commit. Caller (controller) already wraps this in DB::transaction.
        ScrapOrder::where('company_id', $data['company_id'])
            ->lockForUpdate()
            ->orderByDesc('id')
            ->limit(1)
            ->get();

        $count = ScrapOrder::where('company_id', $data['company_id'])->count() + 1;
        $data['name']  = 'SP/' . str_pad($count, 5, '0', STR_PAD_LEFT);
        $data['state'] = 'draft';

        $scrap = ScrapOrder::create($data);
        $this->chatterService->logCreated($scrap, 'Scrap Order');
        return $scrap;
    }

    public function validate(ScrapOrder $scrap): ScrapOrder
    {
        if ($scrap->isDone()) return $scrap;

        $scrap->load('product');
        $productName = $scrap->product?->name ?? '';
        $qty         = (float) $scrap->scrap_qty;

        // Only storable products affect physical stock
        if ($scrap->product?->isStorable()) {
            // Odoo: cannot scrap more than available on hand — block if insufficient stock
            $onHand = Quant::where('company_id', $scrap->company_id)
                ->where('product_id', $scrap->product_id)
                ->where('location_id', $scrap->location_id)
                ->where('lot_id', $scrap->lot_id)
                ->lockForUpdate()
                ->sum('quantity');

            if ($qty > (float) $onHand + 0.0001) {
                throw new \RuntimeException(
                    "Insufficient stock for \"{$productName}\". " .
                    "Available: " . number_format($onHand, 4) . ", requested: " . number_format($qty, 4) . "."
                );
            }

            $this->updateQuant($scrap->company_id, $scrap->product_id, $scrap->location_id,       $scrap->lot_id, -$qty);
            $this->updateQuant($scrap->company_id, $scrap->product_id, $scrap->scrap_location_id, $scrap->lot_id,  $qty);
        }

        // Create stock move for traceability regardless of product type
        $move = Move::create([
            'company_id'       => $scrap->company_id,
            'product_id'       => $scrap->product_id,
            'uom_id'           => $scrap->uom_id,
            'location_src_id'  => $scrap->location_id,
            'location_dest_id' => $scrap->scrap_location_id,
            'name'             => 'Scrap: ' . $productName,
            'product_qty'      => $qty,
            'qty_done'         => $qty,
            'state'            => 'done',
            'date'             => now()->toDateString(),
            'created_by'       => auth()->id(),
            'updated_by'       => auth()->id(),
        ]);

        $scrap->update([
            'state'      => 'done',
            'move_id'    => $move->id,
            'date_done'  => now()->toDateString(),
            'updated_by' => auth()->id(),
        ]);

        $this->chatterService->log($scrap, 'Scrap order validated.', 'log');
        return $scrap->fresh();
    }

    public function delete(ScrapOrder $scrap): void
    {
        if ($scrap->isDone()) {
            throw new \RuntimeException('Done scrap orders cannot be deleted.');
        }
        $this->chatterService->log($scrap, 'Scrap order deleted.', 'system');
        $scrap->delete();
    }

    private function updateQuant(int $companyId, int $productId, int $locationId, ?int $lotId, float $qtyDelta): void
    {
        $quant = Quant::where('company_id', $companyId)
            ->where('product_id', $productId)
            ->where('location_id', $locationId)
            ->where('lot_id', $lotId)
            ->lockForUpdate()
            ->first();

        if ($quant) {
            $quant->increment('quantity', $qtyDelta);
        } else {
            Quant::create([
                'company_id'  => $companyId,
                'product_id'  => $productId,
                'location_id' => $locationId,
                'lot_id'      => $lotId,
                'quantity'    => $qtyDelta,
                'in_date'     => now(),
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ]);
        }
    }
}
