<?php

namespace App\Observers\Inventory;

use App\Models\Inventory\Move;
use App\Models\Inventory\MoveLine;
use RuntimeException;

/**
 * Move lines on a done move are immutable.
 *
 * MoveLine is the per-lot detail row created when a tracked product moves —
 * each line names a (location_src, location_dest, lot, qty_done) tuple and is
 * what `PickingService::validate()` walked when it decremented/incremented the
 * Quant rows. After the parent Move flips to `done`, those quant changes are
 * committed; mutating the source MoveLine afterwards would desynchronise the
 * line from the quant numbers it produced.
 *
 * MoveLine has no `state` column of its own — the parent Move's state is the
 * truth, so we look at it. The picking service legitimately updates lines
 * during validate (sets `lot_id` after resolveOrCreateLot) BEFORE flipping
 * the parent move to `done`, so checking the parent's CURRENT state catches
 * post-validate mutations without blocking the validate pass itself.
 */
class MoveLineObserver
{
    private const PROTECTED_FIELDS = [
        'company_id', 'move_id', 'picking_id', 'product_id', 'uom_id',
        'location_id', 'location_dest_id', 'lot_id', 'lot_name',
        'reserved_qty', 'qty_done', 'date',
    ];

    public function updating(MoveLine $line): void
    {
        // The parent Move's CURRENT state is the gate. During validate the
        // parent is still `assigned`/`confirmed` when the service updates the
        // line — the parent doesn't flip to `done` until after the loop.
        $parentState = Move::whereKey($line->move_id)->value('state');
        if ($parentState !== 'done') {
            return;
        }

        $dirty = collect($line->getDirty())
            ->keys()
            ->intersect(self::PROTECTED_FIELDS)
            ->values();

        if ($dirty->isNotEmpty()) {
            throw new RuntimeException(__('inventory.err_done_moveline_immutable', [
                'id' => $line->id,
            ]));
        }
    }

    public function deleting(MoveLine $line): void
    {
        $parentState = Move::whereKey($line->move_id)->value('state');
        if ($parentState === 'done') {
            throw new RuntimeException(__('inventory.err_done_moveline_no_delete', [
                'id' => $line->id,
            ]));
        }
    }
}
