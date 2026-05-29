<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Move;
use App\Models\Inventory\MoveLine;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\Quant;
use App\Models\Inventory\RouteRule;
use App\Services\Chatter\ChatterService;

class PickingService
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function create(array $data, array $movesData = []): Picking
    {
        $operationType = OperationType::findOrFail($data['operation_type_id']);

        // Atomic read-then-increment under SELECT FOR UPDATE — see
        // OperationType::reserveNextSequenceName(). The old separate
        // nextSequenceName() + incrementSequence() raced against concurrent
        // creates and could collide on the UNIQUE(company_id, name) index.
        $data['name']  = $operationType->reserveNextSequenceName();
        $data['state'] = Picking::STATE_DRAFT;

        $picking = Picking::create($data);

        foreach ($movesData as $moveRow) {
            $this->addMove($picking, $moveRow);
        }

        $this->chatterService->logCreated($picking, __('inventory.chatter_label_transfer'));
        return $picking->fresh();
    }

    public function addMove(Picking $picking, array $moveData): Move
    {
        $moveData['company_id']       = $picking->company_id;
        $moveData['picking_id']       = $picking->id;
        $moveData['location_src_id']  = $moveData['location_src_id']  ?? $picking->location_src_id;
        $moveData['location_dest_id'] = $moveData['location_dest_id'] ?? $picking->location_dest_id;
        $moveData['state']            = 'draft';
        $moveData['name']             = $moveData['name'] ?? ($moveData['product_name'] ?? 'Move');
        $moveData['date']             = $moveData['date'] ?? now()->toDateString();
        $moveData['created_by']       = auth()->id();
        $moveData['updated_by']       = auth()->id();

        // Odoo parity: PutawayRule destination redirection. When a move lands
        // an internal location that has a matching putaway rule, redirect the
        // dest to the rule's fixed_location_id (e.g. "Stock" → "Stock/Aisle-A
        // /Shelf-3"). The picking-level dest stays untouched so the operator
        // can still see the warehouse-level destination on the header; only
        // the move row carries the refined target.
        if (!empty($moveData['product_id']) && !empty($moveData['location_dest_id'])) {
            $destLocation = Location::find($moveData['location_dest_id']);
            $product      = Product::with('category')->find($moveData['product_id']);
            if ($destLocation && $product) {
                $redirect = PutawayRule::resolveFor($product, $destLocation, $picking->company_id);
                if ($redirect) {
                    $moveData['location_dest_id'] = $redirect->id;
                }
            }
        }

        return Move::create($moveData);
    }

    public function confirm(Picking $picking): Picking
    {
        if (!$picking->isDraft()) return $picking;

        $picking->moves()->update(['state' => 'confirmed', 'updated_by' => auth()->id()]);
        $picking->update(['state' => Picking::STATE_CONFIRMED, 'updated_by' => auth()->id()]);

        $this->chatterService->log($picking, __('inventory.chatter_transfer_confirmed'), 'log');
        return $picking->fresh();
    }

    /**
     * Confirm (if still draft) then reserve stock against quants.
     * Matches Odoo's "Check Availability" which does action_confirm + action_assign in one shot.
     */
    public function checkAvailability(Picking $picking): Picking
    {
        // Lock the picking row first so concurrent checkAvailability calls
        // (or one racing with validate/cancel) see a serialized view of the
        // reservation state — otherwise both could release+re-reserve the
        // same quants and end up double-counting reserved_quantity.
        $picking = Picking::whereKey($picking->id)->lockForUpdate()->first() ?? $picking;

        if ($picking->isDone() || $picking->isCancelled()) return $picking;

        // Auto-confirm from draft — Odoo's "Check Availability" on a draft picking confirms first
        if ($picking->isDraft()) {
            $picking->moves()->update(['state' => 'confirmed', 'updated_by' => auth()->id()]);
            $picking->update(['state' => Picking::STATE_CONFIRMED, 'updated_by' => auth()->id()]);
            $picking->refresh();
        }

        // Release any existing reservations before re-computing
        $this->releasePickingReservations($picking);

        $allAvailable = true;

        foreach ($picking->moves()->with(['product.uom', 'uom'])->get() as $move) {
            // Non-storable products (consumable / service) don't consume physical stock
            if (!$move->product?->isStorable()) {
                $move->update(['reserved_qty' => $move->product_qty, 'state' => 'assigned', 'updated_by' => auth()->id()]);
                continue;
            }

            // Quants are always stored in the product's reference UoM (Odoo
            // convention — no uom_id column on inventory_quants). Reservation
            // math therefore lives in product UoM; we convert move.product_qty
            // (which is in move UoM, e.g. user picked a kg-tracked product and
            // entered 1.5 g for this transfer) and convert back to move UoM
            // before storing on move/line rows so the user sees their original
            // unit on the screen.
            $moveUom    = $move->uom;
            $productUom = $move->product->uom;
            $toReserve  = ($moveUom && $productUom)
                ? $moveUom->convertQty((float) $move->product_qty, $productUom)
                : (float) $move->product_qty;
            $reserved        = 0.0;
            $lotTracked      = $move->product?->requiresLotTracking();
            $lotReservations = [];

            // Clear stale detailed operations before re-reserving
            if ($lotTracked) {
                $move->moveLines()->delete();
            }

            // Odoo parity: removal_strategy comes from the product's category
            // (defaults to FIFO). Reservation order:
            //   fifo → earliest in_date first  (DEFAULT)
            //   lifo → latest in_date first
            //   fefo → earliest lot.expiration_date first (lots with no
            //          expiration sort last so they're not consumed before
            //          expiring stock)
            // Legacy `closest_location` rows still in the DB resolve to FIFO
            // — the dropdown no longer offers it (the picker had no warehouse-
            // coordinate model, so it always behaved as FIFO anyway). Reads
            // quants under lockForUpdate so concurrent moves can't both claim
            // the same available units.
            $strategy = $move->product?->category?->removal_strategy ?? 'fifo';
            if ($strategy === 'closest_location') {
                $strategy = 'fifo';
            }
            $quantQuery = Quant::where('inventory_quants.company_id', $move->company_id)
                ->where('inventory_quants.product_id', $move->product_id)
                ->where('inventory_quants.location_id', $move->location_src_id)
                ->lockForUpdate();

            if ($strategy === 'fefo') {
                // FEFO sort key is COALESCE(expiration_date, use_date) — the
                // hard expiration if set, falling back to Best Before. Lots
                // with neither sort last (the IS NULL bucket). Without the
                // use_date fallback, products tracked only by Best Before
                // (a real Odoo pattern for groceries / pharma) would never
                // FEFO at all and quietly consume in insertion order.
                $quantQuery->leftJoin('inventory_lots', 'inventory_quants.lot_id', '=', 'inventory_lots.id')
                    ->orderByRaw('COALESCE(inventory_lots.expiration_date, inventory_lots.use_date) IS NULL')
                    ->orderByRaw('COALESCE(inventory_lots.expiration_date, inventory_lots.use_date)')
                    ->orderBy('inventory_quants.in_date')
                    ->select('inventory_quants.*');
            } elseif ($strategy === 'lifo') {
                $quantQuery->orderByDesc('inventory_quants.in_date');
            } else {
                $quantQuery->orderBy('inventory_quants.in_date');
            }
            $quants = $quantQuery->get();

            foreach ($quants as $quant) {
                if ($reserved >= $toReserve) break;
                $available = max(0.0, $quant->quantity - $quant->reserved_quantity);
                $take      = min($toReserve - $reserved, $available);
                if ($take > 0) {
                    $quant->increment('reserved_quantity', $take);
                    $reserved += $take;
                    if ($lotTracked) {
                        $lotReservations[] = ['lot_id' => $quant->lot_id, 'qty' => $take];
                    }
                }
            }

            // Create detailed operation lines per lot (Odoo action_assign behaviour).
            // $res['qty'] is in product UoM (came directly from quant.quantity);
            // store on the line in the move's UoM so the user sees consistent
            // units across move and lines.
            foreach ($lotReservations as $res) {
                $lineQtyInMoveUom = ($moveUom && $productUom)
                    ? $productUom->convertQty($res['qty'], $moveUom)
                    : $res['qty'];
                MoveLine::create([
                    'company_id'       => $move->company_id,
                    'move_id'          => $move->id,
                    'picking_id'       => $move->picking_id,
                    'product_id'       => $move->product_id,
                    'uom_id'           => $move->uom_id,
                    'location_id'      => $move->location_src_id,
                    'location_dest_id' => $move->location_dest_id,
                    'lot_id'           => $res['lot_id'],
                    'reserved_qty'     => $lineQtyInMoveUom,
                    'qty_done'         => $lineQtyInMoveUom,
                    'date'             => now()->toDateString(),
                ]);
            }

            $reservedInMoveUom = ($moveUom && $productUom)
                ? $productUom->convertQty($reserved, $moveUom)
                : $reserved;

            $move->update([
                'reserved_qty' => $reservedInMoveUom,
                'state'        => $reserved >= $toReserve
                    ? 'assigned'
                    : ($reserved > 0 ? 'partially_available' : 'confirmed'),
                'updated_by'   => auth()->id(),
            ]);

            if ($reserved < $toReserve) $allAvailable = false;
        }

        $newState = $allAvailable ? Picking::STATE_ASSIGNED : Picking::STATE_CONFIRMED;
        $picking->update(['state' => $newState, 'updated_by' => auth()->id()]);
        return $picking->fresh();
    }

    public function validate(Picking $picking, array $doneQties = [], bool $createBackorder = true): array
    {
        // Row-lock the picking inside the surrounding DB::transaction so two
        // concurrent validate() calls can't both pass canValidate(), both run
        // the quant updates, and double-decrement stock. The per-quant
        // lockForUpdate inside updateQuant() only serializes one quant at a
        // time — without the parent lock, the whole loop runs twice.
        $picking = Picking::whereKey($picking->id)->lockForUpdate()->first() ?? $picking;

        if (!$picking->canValidate()) {
            throw new \RuntimeException(__('inventory.err_transfer_bad_state'));
        }

        $hasAnyDone    = false;
        $backorderItems = [];

        foreach ($picking->moves()->with(['product.uom', 'uom', 'moveLines.uom'])->get() as $move) {
            $qtyDone = isset($doneQties[$move->id]) ? (float) $doneQties[$move->id] : (float) $move->product_qty;

            // Enforce lot/serial tracking: require move lines with lot info before validating
            if ($qtyDone > 0 && $move->product?->requiresLotTracking()) {
                $opType = $picking->operationType;
                if ($opType && ($opType->use_create_lots || $opType->use_existing_lots)) {
                    if ($move->moveLines->count() === 0) {
                        throw new \RuntimeException(__('inventory.err_product_needs_lot', [
                            'name' => $move->product->name,
                        ]));
                    }
                    $isSerial = $move->product?->isTrackedBySerial();
                    foreach ($move->moveLines as $line) {
                        if (!$line->lot_id && !$line->lot_name) {
                            throw new \RuntimeException(__('inventory.err_lines_need_lot', [
                                'name' => $move->product->name,
                            ]));
                        }
                        // Odoo parity: serial-tracked products require exactly
                        // one unit per move line — each serial is a physical
                        // single-instance asset. Lot-tracked allows N per line.
                        if ($isSerial && abs((float) $line->qty_done - 1.0) > 0.0001) {
                            throw new \RuntimeException(__('inventory.err_serial_one_per_line', [
                                'name' => $move->product->name,
                                'qty'  => number_format((float) $line->qty_done, 4),
                            ]));
                        }
                    }
                    // Odoo parity: a serial number is a unique physical asset.
                    // The same serial cannot exist twice on hand at the same
                    // time within the same product. For an INCOMING move,
                    // check that none of the lot_names being created already
                    // exist with on-hand stock for this product.
                    if ($isSerial && $opType->code === 'incoming') {
                        $this->assertSerialsNotAlreadyOnHand($move);
                    }
                }
            }

            // Release quant reservation for this move regardless of qty_done
            $this->releaseMoveReservation($move);

            if ($qtyDone <= 0) {
                $backorderItems[] = ['move' => $move, 'remaining' => (float) $move->product_qty];
                $move->update(['qty_done' => 0, 'state' => 'cancelled', 'updated_by' => auth()->id()]);
                continue;
            }

            $hasAnyDone = true;

            // Only storable products affect stock quants
            if ($move->product?->isStorable()) {
                // R2 finding: refuse over-delivery from an INTERNAL source. A
                // user with inventory.write could otherwise inflate qty_done
                // (the field is user-controlled) and silently drive the source
                // quant negative — the "delivered" units would then be covered
                // up via a later inventory adjustment, hiding theft. Virtual
                // sources (supplier / customer / inventory) skip the check
                // because they intentionally don't carry stock.
                $srcLocation = $move->srcLocation ?? \App\Models\Inventory\Location::find($move->location_src_id);
                $srcIsInternal = $srcLocation && $srcLocation->usage === 'internal';

                // Quants are in product reference UoM — convert every move/line
                // qty out of its picker UoM before touching a quant. Without
                // this, transferring 1 kg of a g-tracked product subtracts only
                // 1 g from the source quant (1000× under-reported), silently
                // creating a stock surplus that adjustments would later cover.
                $productUom = $move->product->uom;
                $moveUom    = $move->uom;

                if ($move->moveLines->count() > 0) {
                    // Lot-tracked: move quants per move line
                    $productUomTotal = 0.0;
                    foreach ($move->moveLines as $line) {
                        $lineQty = (float) $line->qty_done;
                        if ($lineQty <= 0) continue;
                        $lot = $this->resolveOrCreateLot($line);

                        $lineUom = $line->uom ?? $moveUom;
                        $lineProductQty = ($lineUom && $productUom)
                            ? $lineUom->convertQty($lineQty, $productUom)
                            : $lineQty;

                        if ($srcIsInternal) {
                            $this->assertSufficientOnHand($move, $lot?->id, $lineProductQty);
                        }
                        $this->updateQuant($picking->company_id, $move->product_id, $move->location_src_id,  $lot?->id, -$lineProductQty);
                        $this->updateQuant($picking->company_id, $move->product_id, $move->location_dest_id, $lot?->id,  $lineProductQty);
                        $line->update(['lot_id' => $lot?->id, 'updated_by' => auth()->id()]);
                        $productUomTotal += $lineProductQty;
                    }
                    // Display move.qty_done in move UoM — convert the aggregated
                    // product-UoM total back. Summing line.qty_done directly
                    // would mix units if a line ever had a different UoM than
                    // its move (e.g. line in g, move in kg).
                    $qtyDone = ($moveUom && $productUom)
                        ? $productUom->convertQty($productUomTotal, $moveUom)
                        : $productUomTotal;
                } else {
                    // Simple (no lots) — convert move qty_done to product UoM
                    $moveProductQty = ($moveUom && $productUom)
                        ? $moveUom->convertQty($qtyDone, $productUom)
                        : $qtyDone;

                    if ($srcIsInternal) {
                        $this->assertSufficientOnHand($move, null, $moveProductQty);
                    }
                    $this->updateQuant($picking->company_id, $move->product_id, $move->location_src_id,  null, -$moveProductQty);
                    $this->updateQuant($picking->company_id, $move->product_id, $move->location_dest_id, null,  $moveProductQty);
                }
            }

            $remaining = (float) $move->product_qty - $qtyDone;
            if ($remaining > 0.001) {
                $backorderItems[] = ['move' => $move, 'remaining' => $remaining];
            }

            $move->update(['qty_done' => $qtyDone, 'state' => 'done', 'updated_by' => auth()->id()]);
        }

        if (!$hasAnyDone) {
            throw new \RuntimeException(__('inventory.err_nothing_to_validate'));
        }

        $picking->update([
            'state'      => Picking::STATE_DONE,
            'date_done'  => now(),
            'updated_by' => auth()->id(),
        ]);

        $backorder = null;
        if ($createBackorder && !empty($backorderItems)) {
            $backorder = $this->createBackorder($picking, $backorderItems);
        }

        $chains = $this->fireChainRulesFor($picking->fresh());

        $this->chatterService->log($picking, __('inventory.chatter_transfer_validated'), 'log');

        return ['picking' => $picking->fresh(), 'backorder' => $backorder, 'chains' => $chains];
    }

    public function cancel(Picking $picking): Picking
    {
        // Lock the picking row so a racing validate() can't decrement stock
        // after we've released reservations and decided to cancel.
        $picking = Picking::whereKey($picking->id)->lockForUpdate()->first() ?? $picking;

        if ($picking->isDone()) {
            throw new \RuntimeException(__('inventory.err_done_no_cancel'));
        }

        // Release all quant reservations before cancelling
        $this->releasePickingReservations($picking);

        $picking->moves()->update(['state' => 'cancelled', 'updated_by' => auth()->id()]);
        $picking->update(['state' => Picking::STATE_CANCELLED, 'updated_by' => auth()->id()]);

        $this->chatterService->log($picking, __('inventory.chatter_transfer_cancelled'), 'log');
        return $picking->fresh();
    }

    public function createReturn(Picking $picking, array $returnQties = []): Picking
    {
        $returnOpType = $picking->operationType->returnPickingType;
        if (!$returnOpType) {
            throw new \RuntimeException(__('inventory.err_no_return_optype'));
        }

        $returnData = [
            'company_id'         => $picking->company_id,
            'operation_type_id'  => $returnOpType->id,
            'partner_id'         => $picking->partner_id,
            'location_src_id'    => $picking->location_dest_id,
            'location_dest_id'   => $picking->location_src_id,
            'origin_picking_id'  => $picking->id,
            'origin'             => __('inventory.origin_return_of', ['ref' => $picking->name]),
            'scheduled_date'     => now(),
            'active'             => true,
            'created_by'         => auth()->id(),
            'updated_by'         => auth()->id(),
        ];

        $movesData = [];
        foreach ($picking->moves()->where('state', 'done')->get() as $move) {
            $qty = (float) ($returnQties[$move->id] ?? $move->qty_done);
            if ($qty <= 0) continue;
            $movesData[] = [
                'product_id'              => $move->product_id,
                'uom_id'                  => $move->uom_id,
                'location_src_id'         => $picking->location_dest_id,
                'location_dest_id'        => $picking->location_src_id,
                'name'                    => $move->name,
                'product_qty'             => $qty,
                'qty_done'                => 0,
                'state'                   => 'draft',
                'origin_returned_move_id' => $move->id,
                'date'                    => now()->toDateString(),
                'created_by'              => auth()->id(),
                'updated_by'              => auth()->id(),
            ];
        }

        return $this->create($returnData, $movesData);
    }

    public function addMoveLine(Move $move, array $data): MoveLine
    {
        $data['company_id']       = $move->company_id;
        $data['move_id']          = $move->id;
        $data['picking_id']       = $move->picking_id;
        $data['product_id']       = $move->product_id;
        $data['uom_id']           = $move->uom_id;
        $data['location_id']      = $data['location_id']       ?? $move->location_src_id;
        $data['location_dest_id'] = $data['location_dest_id']  ?? $move->location_dest_id;
        $data['created_by']       = auth()->id();
        $data['updated_by']       = auth()->id();
        return MoveLine::create($data);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function createBackorder(Picking $picking, array $backorderItems): Picking
    {
        $backorderData = [
            'company_id'        => $picking->company_id,
            'operation_type_id' => $picking->operation_type_id,
            'partner_id'        => $picking->partner_id,
            'location_src_id'   => $picking->location_src_id,
            'location_dest_id'  => $picking->location_dest_id,
            'origin_picking_id' => $picking->id,
            'origin'            => $picking->name,
            'note'              => $picking->note,
            'scheduled_date'    => $picking->scheduled_date,
            'active'            => true,
            'created_by'        => auth()->id(),
            'updated_by'        => auth()->id(),
        ];

        $movesData = [];
        foreach ($backorderItems as $item) {
            $move = $item['move'];
            $movesData[] = [
                'product_id'       => $move->product_id,
                'uom_id'           => $move->uom_id,
                'location_src_id'  => $move->location_src_id,
                'location_dest_id' => $move->location_dest_id,
                'name'             => $move->name,
                'product_qty'      => $item['remaining'],
                'qty_done'         => 0,
                'state'            => 'confirmed',
                'date'             => now()->toDateString(),
                'created_by'       => auth()->id(),
                'updated_by'       => auth()->id(),
            ];
        }

        $backorder = $this->create($backorderData, $movesData);

        // Backorders start confirmed immediately — matches Odoo's _create_backorder() behaviour
        $backorder->moves()->update(['state' => 'confirmed', 'updated_by' => auth()->id()]);
        $backorder->update(['state' => Picking::STATE_CONFIRMED, 'updated_by' => auth()->id()]);

        $this->chatterService->log($backorder, __('inventory.chatter_backorder_of', ['ref' => $picking->name]), 'log');
        return $backorder->fresh();
    }

    /**
     * Release quant reserved_quantity for all reserved moves in a picking.
     * Called before cancel, delete, or before re-computing availability.
     */
    public function releasePickingReservations(Picking $picking): void
    {
        foreach ($picking->moves()->where('reserved_qty', '>', 0)->with('product')->get() as $move) {
            $this->releaseMoveReservation($move);
        }
    }

    /**
     * Release the quant reservation for a single move and reset move.reserved_qty to 0.
     *
     * move.reserved_qty is stored in move UoM; quant.reserved_quantity is in
     * product reference UoM. Convert before distributing the release across
     * quants — otherwise releasing 1 kg of a g-tracked reservation would
     * underflow only 1 g back, leaking 999 g of "reserved" stock that no
     * subsequent action could free.
     */
    public function releaseMoveReservation(Move $move): void
    {
        if ((float) $move->reserved_qty <= 0 || !$move->product?->isStorable()) return;

        $move->loadMissing(['uom', 'product.uom']);
        $moveUom    = $move->uom;
        $productUom = $move->product?->uom;
        $toRelease  = ($moveUom && $productUom)
            ? $moveUom->convertQty((float) $move->reserved_qty, $productUom)
            : (float) $move->reserved_qty;

        // Distribute the release across quants for this product/location (highest reserved first)
        $quants = Quant::where('company_id', $move->company_id)
            ->where('product_id', $move->product_id)
            ->where('location_id', $move->location_src_id)
            ->where('reserved_quantity', '>', 0)
            ->lockForUpdate()
            ->orderByDesc('reserved_quantity')
            ->get();

        foreach ($quants as $quant) {
            if ($toRelease <= 0) break;
            $release = min($toRelease, (float) $quant->reserved_quantity);
            $quant->decrement('reserved_quantity', $release);
            $toRelease -= $release;
        }

        $move->update(['reserved_qty' => 0, 'updated_by' => auth()->id()]);
    }

    private function resolveOrCreateLot(MoveLine $line): ?Lot
    {
        if ($line->lot_id) return $line->lot;

        if ($line->lot_name) {
            // The (company_id, product_id, name) unique index guarantees no
            // duplicate rows even if two concurrent validate() calls race
            // here. firstOrCreate would throw QueryException on the loser
            // — catch it and re-read so a perfectly valid concurrent
            // receipt doesn't user-visibly fail.
            try {
                return Lot::firstOrCreate(
                    ['company_id' => $line->company_id, 'product_id' => $line->product_id, 'name' => $line->lot_name],
                    ['active' => true]
                );
            } catch (\Illuminate\Database\QueryException $e) {
                $existing = Lot::where('company_id', $line->company_id)
                    ->where('product_id', $line->product_id)
                    ->where('name', $line->lot_name)
                    ->first();
                if ($existing) return $existing;
                throw $e;
            }
        }

        return null;
    }

    /**
     * Odoo parity: refuse to receive a serial number that already has
     * on-hand stock for this product. A serial identifies a single physical
     * unit — duplicates would silently merge two distinct objects into a
     * single tracked record. Checks both the existing lot_id route (caller
     * supplied an existing Lot row by id) and the new lot_name route
     * (resolveOrCreateLot would find-or-create a lot with that name).
     *
     * Caller is already inside the validate() transaction.
     */
    private function assertSerialsNotAlreadyOnHand(Move $move): void
    {
        foreach ($move->moveLines as $line) {
            $lotName = $line->lot_id
                ? Lot::where('id', $line->lot_id)->value('name')
                : $line->lot_name;
            if (!$lotName) continue;

            $lot = Lot::where('company_id', $move->company_id)
                ->where('product_id', $move->product_id)
                ->where('name', $lotName)
                ->first();
            if (!$lot) continue;  // brand-new serial, fine

            $onHand = (float) Quant::where('company_id', $move->company_id)
                ->where('product_id', $move->product_id)
                ->where('lot_id', $lot->id)
                ->whereHas('location', fn ($q) => $q->where('usage', 'internal'))
                ->sum('quantity');
            if ($onHand > 0.0001) {
                throw new \RuntimeException(__('inventory.err_serial_already_on_hand', [
                    'name'    => $lotName,
                    'product' => $move->product?->name ?? '#' . $move->product_id,
                    'qty'     => number_format($onHand, 4),
                ]));
            }
        }
    }

    /**
     * R2 guard: refuse to move more units out of an internal location than
     * exist on hand. Without this check, a user with inventory.write could
     * inflate qty_done on a delivery picking and silently take the source
     * quant negative — "delivered" units could then be hidden via a later
     * inventory adjustment, covering up theft.
     *
     * `$qty` must be in the **product reference UoM** (matches the unit of
     * quant.quantity that we sum against). Callers are responsible for the
     * `move.uom → product.uom` conversion before calling.
     *
     * Caller must already be inside the validate() transaction so the
     * sum-then-act check is atomic against concurrent moves.
     */
    private function assertSufficientOnHand(Move $move, ?int $lotId, float $qty): void
    {
        $onHand = (float) Quant::where('company_id', $move->company_id)
            ->where('product_id', $move->product_id)
            ->where('location_id', $move->location_src_id)
            ->where('lot_id', $lotId)
            ->lockForUpdate()
            ->sum('quantity');

        if ($qty > $onHand + 0.0001) {
            $productName = $move->product?->name ?? "#{$move->product_id}";
            throw new \RuntimeException(__('inventory.err_insufficient_at_source', [
                'product'   => $productName,
                'on_hand'   => number_format($onHand, 4),
                'requested' => number_format($qty, 4),
            ]));
        }
    }

    /**
     * Odoo parity: push-rule chain engine. After a picking is validated, scan
     * the done moves' destination locations for active push RouteRules. For
     * each matched rule, auto-create a follow-up picking (Input → Stock,
     * QC → Stock, etc.) so the multi-step receipt flow continues without
     * the user re-creating a picking by hand.
     *
     * Defense-in-depth: company-scoped (a rule with `company_id = null` is
     * global; a tenant-specific rule must match the picking's company).
     * Rules whose `operation_type_id` or `location_dest_id` is null are
     * skipped — they're malformed for push usage. The created chain picking
     * carries `origin_picking_id = $origin->id` so the audit feed traces back
     * to the receipt that triggered it.
     *
     * Returns the chain pickings created — the caller folds them into the
     * validate() return so the UI can show "Auto-created N follow-up
     * transfers" if needed.
     *
     * @return list<Picking>
     */
    private function fireChainRulesFor(Picking $origin): array
    {
        if (!$origin->isDone()) return [];

        // Only "done" moves chain forward — cancelled or zero-qty moves
        // produced no stock movement, so there's nothing to push.
        $doneMoves = $origin->moves()->where('state', 'done')->with(['product', 'uom'])->get();
        if ($doneMoves->isEmpty()) return [];

        $chains = [];
        $byDest = $doneMoves->groupBy('location_dest_id');

        foreach ($byDest as $destLocationId => $moves) {
            $rules = RouteRule::where('inventory_route_rules.active', true)
                ->where('action', 'push')
                ->where('location_src_id', $destLocationId)
                ->where(function ($q) use ($origin) {
                    $q->whereNull('inventory_route_rules.company_id')
                      ->orWhere('inventory_route_rules.company_id', $origin->company_id);
                })
                ->whereNotNull('operation_type_id')
                ->whereNotNull('location_dest_id')
                // Route-level archive must disable its rules. The schema has
                // no DB-level link between route.active and rule.active, so
                // without this whereHas a user archiving a Route at the UI
                // would still see its rules silently fire on every receipt.
                ->whereHas('route', fn ($q) => $q->where('active', true))
                ->with(['operationType', 'destLocation'])
                ->orderBy('sequence')
                ->get();

            foreach ($rules as $rule) {
                $chain = $this->createChainPickingFromRule($origin, $rule, $moves);
                if ($chain) $chains[] = $chain;
            }
        }

        return $chains;
    }

    /**
     * Create a single chain picking for a matched push rule. The rule's
     * fields drive the new picking's op_type / src / dest; the parent's
     * moves carry forward as qty (in their original UoM) on the new moves.
     *
     * Returns null when the rule references a cross-tenant op_type or
     * dest_location — defense-in-depth against multi-company users who
     * configured a rule that points outside the picking's tenant.
     */
    private function createChainPickingFromRule(Picking $origin, RouteRule $rule, \Illuminate\Support\Collection $moves): ?Picking
    {
        $opType = $rule->operationType;
        $dest   = $rule->destLocation;
        if (!$opType || !$dest || !$dest->active) return null;

        // Op type and dest must be in the picking's company (or shared null).
        if ($opType->company_id !== null && (int) $opType->company_id !== (int) $origin->company_id) {
            return null;
        }
        if ($dest->company_id !== null && (int) $dest->company_id !== (int) $origin->company_id) {
            return null;
        }

        $movesData = [];
        foreach ($moves as $move) {
            $movesData[] = [
                'product_id'  => $move->product_id,
                'uom_id'      => $move->uom_id,
                'product_qty' => (float) $move->qty_done,
                'qty_done'    => 0,
                'state'       => 'draft',
                'name'        => $move->name ?: $move->product?->name ?? 'Move',
                'date'        => now()->toDateString(),
                'created_by'  => auth()->id(),
                'updated_by'  => auth()->id(),
            ];
        }

        if (empty($movesData)) return null;

        $pickingData = [
            'company_id'        => $origin->company_id,
            'operation_type_id' => $opType->id,
            'partner_id'        => $origin->partner_id,
            'location_src_id'   => $rule->location_src_id,
            'location_dest_id'  => $rule->location_dest_id,
            'origin_picking_id' => $origin->id,
            'origin'            => $origin->name,
            'scheduled_date'    => now()->addDays((int) ($rule->delay ?? 0)),
            'active'            => true,
            'created_by'        => auth()->id(),
            'updated_by'        => auth()->id(),
        ];

        $chain = $this->create($pickingData, $movesData);
        // Start the chain picking in 'confirmed' state — the worker shouldn't
        // need to manually click Confirm. The reservation step still needs
        // a manual "Check Availability" (or runs via the standard validate
        // flow), so the chain isn't auto-validated.
        $this->confirm($chain);

        $this->chatterService->log($chain, __('inventory.chatter_chain_of', ['ref' => $origin->name]), 'log');

        return $chain->fresh();
    }

    /**
     * Upsert a quant with a quantity delta. Uses lockForUpdate to prevent race conditions.
     */
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
