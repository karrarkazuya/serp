<?php

namespace App\Observers\Inventory;

use App\Models\Inventory\Move;
use RuntimeException;

/**
 * Done moves are immutable.
 *
 * The picking service drives the move state machine — `draft` → `confirmed` →
 * `assigned` → `done` (or `cancelled`). Once a move lands in `done` its
 * `qty_done`, locations, and product association are part of the inventory
 * ledger: a Quant was decremented at `location_src_id` and incremented at
 * `location_dest_id`, the `qty_done` units changed hands. Mutating any of
 * those fields after the fact would silently desynchronise the move from
 * the quant rows it already produced.
 *
 * Mirrors `AccountMoveLineObserver` in accounting — service paths still
 * transition state explicitly via DB::update, but a future controller, a
 * queued job, an Artisan tinker, or a migration patch that touches
 * `inventory_moves` directly would otherwise bypass the safeguard.
 *
 * Whitelisted fields that may change after `done`:
 *   - `state` itself (a cancel/reset path could legitimately move it off
 *     done — we don't currently support that, but blocking the column
 *     here would pre-emptively foreclose it)
 *   - `updated_by`/`updated_at` — observer/Eloquent bookkeeping
 *   - `deleted_at` — soft-delete is handled separately and is also blocked
 *     by `deleting()` below
 */
class MoveObserver
{
    private const PROTECTED_FIELDS = [
        'company_id', 'picking_id', 'product_id', 'uom_id',
        'location_src_id', 'location_dest_id',
        'origin_returned_move_id', 'name', 'origin',
        'product_qty', 'qty_done', 'reserved_qty',
        'sequence', 'date',
    ];

    public function updating(Move $move): void
    {
        // Look at the ORIGINAL state so a legitimate forward transition
        // (assigned → done during validate()) still passes — the dirty `state`
        // change is what flips the row to done, not a tamper.
        if ($move->getOriginal('state') !== 'done') {
            return;
        }

        $dirty = collect($move->getDirty())
            ->keys()
            ->intersect(self::PROTECTED_FIELDS)
            ->values();

        if ($dirty->isNotEmpty()) {
            throw new RuntimeException(__('inventory.err_done_move_immutable', [
                'id' => $move->id,
            ]));
        }
    }

    public function deleting(Move $move): void
    {
        // Soft-deleting a done move would orphan the quant changes it made.
        // Real removal is via cancel-then-delete on the parent picking, which
        // walks the moves to draft first (releaseMoveReservation + state flip).
        if ($move->state === 'done') {
            throw new RuntimeException(__('inventory.err_done_move_no_delete', [
                'id' => $move->id,
            ]));
        }
    }
}
