<?php

namespace Tests\Feature\Inventory;

use App\Models\Contacts\Contact;
use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\ProductSupplier;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\Route;
use App\Models\Inventory\RouteRule;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\PickingService;
use App\Services\Inventory\WarehouseService;
use Carbon\Carbon;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Round-3 Odoo-parity tests for the inventory module.
 *
 * Covers behaviors that were corrected to match Odoo:
 *  - Removal strategy on the product category drives reservation order
 *    (FIFO / LIFO / FEFO), not hard-coded FIFO.
 *  - Serial-tracked products require exactly 1 unit per move line.
 *  - Serial numbers are unique on-hand per product — receiving an already-on-hand
 *    serial is rejected.
 *  - Over-delivery from an INTERNAL source location is rejected.
 *  - Warehouse provisioning matches the chosen reception_steps / delivery_steps:
 *    only the locations the operation flow actually uses get created.
 */
class InventoryOdooParityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $company;
    private Uom $uom;
    private Warehouse $warehouse;
    private Location $stockLocation;
    private Location $customerLocation;
    private OperationType $deliveryOp;
    private OperationType $receiptOp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->company = Company::create([
            'name'     => 'Odoo Parity Co',
            'active'   => true,
            'currency' => 'USD',
        ]);
        $this->admin->companies()->syncWithoutDetaching([$this->company->id]);
        $this->actingAs($this->admin);

        $this->warehouse = app(WarehouseService::class)->create([
            'company_id'      => $this->company->id,
            'name'            => 'Main',
            'short_name'      => 'MAIN',
            'reception_steps' => 'one_step',
            'delivery_steps'  => 'one_step',
            'active'          => true,
        ]);

        $this->stockLocation    = Location::where('company_id', $this->company->id)->where('name', 'Stock')->firstOrFail();
        $this->customerLocation = Location::where('usage', 'customer')->whereNull('company_id')->firstOrFail();
        $this->deliveryOp       = OperationType::where('company_id', $this->company->id)->where('code', 'outgoing')->firstOrFail();
        $this->receiptOp        = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();

        $this->uom = Uom::where('name', 'Units')->firstOrFail();
    }

    // ─────────────────────────────────────────────────────────────────────
    // Removal strategy
    // ─────────────────────────────────────────────────────────────────────

    /** @test FIFO (default) consumes the OLDEST in_date first. */
    public function test_fifo_removal_consumes_oldest_in_date_first(): void
    {
        $product = $this->makeProduct(strategy: 'fifo');

        $this->makeQuant($product, qty: 10, inDate: '2026-01-05');  // older
        $this->makeQuant($product, qty: 10, inDate: '2026-06-05');  // newer

        $picking = $this->makeDeliveryPicking($product, qty: 8);
        app(PickingService::class)->checkAvailability($picking);

        // Old quant (Jan) should have 8 reserved, new quant (Jun) 0.
        $jan = Quant::where('product_id', $product->id)->whereDate('in_date', '2026-01-05')->first();
        $jun = Quant::where('product_id', $product->id)->whereDate('in_date', '2026-06-05')->first();
        $this->assertEqualsWithDelta(8.0, (float) $jan->reserved_quantity, 0.001, 'FIFO must reserve the oldest in_date first');
        $this->assertEqualsWithDelta(0.0, (float) $jun->reserved_quantity, 0.001);
    }

    /** @test LIFO consumes the NEWEST in_date first. */
    public function test_lifo_removal_consumes_newest_in_date_first(): void
    {
        $product = $this->makeProduct(strategy: 'lifo');

        $this->makeQuant($product, qty: 10, inDate: '2026-01-05');
        $this->makeQuant($product, qty: 10, inDate: '2026-06-05');

        $picking = $this->makeDeliveryPicking($product, qty: 8);
        app(PickingService::class)->checkAvailability($picking);

        $jan = Quant::where('product_id', $product->id)->whereDate('in_date', '2026-01-05')->first();
        $jun = Quant::where('product_id', $product->id)->whereDate('in_date', '2026-06-05')->first();
        $this->assertEqualsWithDelta(0.0, (float) $jan->reserved_quantity, 0.001);
        $this->assertEqualsWithDelta(8.0, (float) $jun->reserved_quantity, 0.001, 'LIFO must reserve the newest in_date first');
    }

    /**
     * FEFO falls back to lot.use_date (Best Before) when expiration_date is
     * null. Without this fallback, products tracked only by Best Before
     * (groceries, pharma) would never FEFO — they'd silently degrade to
     * FIFO and the oldest Best Before would not be consumed first.
     */
    public function test_fefo_uses_best_before_as_fallback_when_no_expiration_date(): void
    {
        $product = $this->makeProduct(strategy: 'fefo', tracking: 'lot');

        // LOT-A: only Best Before (Aug). LOT-B: only Best Before (Oct).
        // Expect A to reserve first.
        $lotA = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-A', 'expiration_date' => null, 'use_date' => '2026-08-01', 'active' => true]);
        $lotB = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-B', 'expiration_date' => null, 'use_date' => '2026-10-01', 'active' => true]);

        $this->makeQuant($product, qty: 10, lotId: $lotA->id);
        $this->makeQuant($product, qty: 10, lotId: $lotB->id);

        $picking = $this->makeDeliveryPicking($product, qty: 7);
        app(PickingService::class)->checkAvailability($picking);

        $aQuant = Quant::where('product_id', $product->id)->where('lot_id', $lotA->id)->first();
        $bQuant = Quant::where('product_id', $product->id)->where('lot_id', $lotB->id)->first();
        $this->assertEqualsWithDelta(7.0, (float) $aQuant->reserved_quantity, 0.001, 'Earlier Best Before must reserve first.');
        $this->assertEqualsWithDelta(0.0, (float) $bQuant->reserved_quantity, 0.001);
    }

    /**
     * When a lot has BOTH expiration_date and use_date, the hard expiration
     * wins (Odoo treats expiration_date as the removal-by-this-date hard
     * date; use_date is the soft Best Before). This locks in that priority.
     */
    public function test_fefo_prefers_expiration_date_over_use_date_when_both_set(): void
    {
        $product = $this->makeProduct(strategy: 'fefo', tracking: 'lot');

        // LOT-A: hard expiration Dec, soft Best Before Mar.
        // LOT-B: hard expiration Sep, soft Best Before Nov.
        // FEFO by hard expiration: B (Sep) before A (Dec).
        $lotA = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-A', 'expiration_date' => '2026-12-01', 'use_date' => '2026-03-01', 'active' => true]);
        $lotB = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-B', 'expiration_date' => '2026-09-01', 'use_date' => '2026-11-01', 'active' => true]);

        $this->makeQuant($product, qty: 5, lotId: $lotA->id);
        $this->makeQuant($product, qty: 5, lotId: $lotB->id);

        $picking = $this->makeDeliveryPicking($product, qty: 4);
        app(PickingService::class)->checkAvailability($picking);

        $aQuant = Quant::where('product_id', $product->id)->where('lot_id', $lotA->id)->first();
        $bQuant = Quant::where('product_id', $product->id)->where('lot_id', $lotB->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $aQuant->reserved_quantity, 0.001);
        $this->assertEqualsWithDelta(4.0, (float) $bQuant->reserved_quantity, 0.001, 'Hard expiration must take precedence over Best Before.');
    }

    /** @test FEFO consumes the EARLIEST expiration_date first (lots without expiry sort last). */
    public function test_fefo_removal_consumes_earliest_expiration_first(): void
    {
        $product = $this->makeProduct(strategy: 'fefo', tracking: 'lot');

        $lotEarly  = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-A', 'expiration_date' => '2026-08-01', 'active' => true]);
        $lotLate   = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-B', 'expiration_date' => '2027-08-01', 'active' => true]);
        $lotForever = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-C', 'expiration_date' => null, 'active' => true]);

        // Note: in_date intentionally REVERSED so FIFO would pick the wrong one,
        // proving FEFO uses expiration date not in_date.
        $this->makeQuant($product, qty: 10, inDate: '2026-07-01', lotId: $lotForever->id);
        $this->makeQuant($product, qty: 10, inDate: '2026-06-01', lotId: $lotLate->id);
        $this->makeQuant($product, qty: 10, inDate: '2026-05-01', lotId: $lotEarly->id);

        $picking = $this->makeDeliveryPicking($product, qty: 8);
        app(PickingService::class)->checkAvailability($picking);

        $earlyQuant = Quant::where('lot_id', $lotEarly->id)->first();
        $this->assertEqualsWithDelta(8.0, (float) $earlyQuant->reserved_quantity, 0.001,
            'FEFO must reserve the lot with the earliest expiration_date first');

        $lateQuant   = Quant::where('lot_id', $lotLate->id)->first();
        $foreverQuant = Quant::where('lot_id', $lotForever->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $lateQuant->reserved_quantity, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $foreverQuant->reserved_quantity, 0.001);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Serial number rules
    // ─────────────────────────────────────────────────────────────────────

    /** @test Validating a serial-tracked move with qty_done != 1 per line is rejected. */
    public function test_serial_tracked_move_requires_exactly_one_unit_per_line(): void
    {
        $product = $this->makeProduct(tracking: 'serial');
        $this->makeQuant($product, qty: 5);

        $picking = $this->makeDeliveryPicking($product, qty: 2);
        app(PickingService::class)->checkAvailability($picking);
        $move = $picking->moves()->first();

        // Manually craft a move line with qty_done = 2 for a serial product —
        // should be rejected on validate.
        $move->moveLines()->delete();
        \App\Models\Inventory\MoveLine::create([
            'company_id'       => $this->company->id,
            'move_id'          => $move->id,
            'picking_id'       => $picking->id,
            'product_id'       => $product->id,
            'uom_id'           => $this->uom->id,
            'location_id'      => $this->stockLocation->id,
            'location_dest_id' => $this->customerLocation->id,
            'lot_name'         => 'SN-EXPLOIT-1',
            'qty_done'         => 2,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/requires exactly 1 unit per serial/');
        app(PickingService::class)->validate($picking->fresh(), [$move->id => 2]);
    }

    /** @test Receiving a serial that already has on-hand stock for the same product is rejected. */
    public function test_serial_uniqueness_blocks_duplicate_on_hand_receive(): void
    {
        $product = $this->makeProduct(tracking: 'serial');
        $supplierLoc = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        // First receipt — receives serial SN-001 (one unit) into stock.
        $existingLot = Lot::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'name'       => 'SN-001',
            'active'     => true,
        ]);
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->stockLocation->id,
            'lot_id'      => $existingLot->id,
            'quantity'    => 1,
            'in_date'     => now(),
        ]);

        // Try a SECOND incoming receipt of the same serial.
        $picking = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplierLoc->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 1, 'name' => $product->name]],
        );
        app(PickingService::class)->confirm($picking);

        $move = $picking->moves()->first();
        \App\Models\Inventory\MoveLine::create([
            'company_id'       => $this->company->id,
            'move_id'          => $move->id,
            'picking_id'       => $picking->id,
            'product_id'       => $product->id,
            'uom_id'           => $this->uom->id,
            'location_id'      => $supplierLoc->id,
            'location_dest_id' => $this->stockLocation->id,
            'lot_name'         => 'SN-001',
            'qty_done'         => 1,
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/already on hand/i');
        app(PickingService::class)->validate($picking->fresh(), [$move->id => 1]);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Over-delivery guard (round 2 carry-over, kept here for parity)
    // ─────────────────────────────────────────────────────────────────────

    /** @test Over-delivery from an INTERNAL source location is rejected. */
    public function test_over_delivery_from_internal_source_is_rejected(): void
    {
        $product = $this->makeProduct();
        $this->makeQuant($product, qty: 5);  // only 5 on hand

        $picking = $this->makeDeliveryPicking($product, qty: 10);
        app(PickingService::class)->checkAvailability($picking);
        $move = $picking->moves()->first();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Insufficient stock/');
        app(PickingService::class)->validate($picking->fresh(), [$move->id => 1000]);
    }

    /** @test Receipt (supplier → stock) is NOT subject to the over-delivery guard. */
    public function test_receipt_does_not_check_source_on_hand(): void
    {
        $product = $this->makeProduct();
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $picking = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 50, 'name' => $product->name]],
        );
        app(PickingService::class)->checkAvailability($picking);
        $move = $picking->moves()->first();

        // Supplier is virtual — no on_hand check fires. Receipt succeeds.
        $result = app(PickingService::class)->validate($picking->fresh(), [$move->id => 50]);
        $this->assertSame('done', $result['picking']->state);
        $this->assertEqualsWithDelta(50.0, $product->getOnHandQty(), 0.001);
    }

    // ─────────────────────────────────────────────────────────────────────
    // Warehouse step provisioning
    // ─────────────────────────────────────────────────────────────────────

    /** @test Two-step warehouse provisions Input + Output only (no QC, no Packing). */
    public function test_two_step_warehouse_provisions_input_and_output_only(): void
    {
        $company = Company::create(['name' => 'Two Step Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);

        $wh = app(WarehouseService::class)->create([
            'company_id'      => $company->id,
            'name'            => 'Two-step WH',
            'short_name'      => 'TS',
            'reception_steps' => 'two_steps',
            'delivery_steps'  => 'two_steps',
            'active'          => true,
        ]);

        $names = Location::where('company_id', $company->id)->pluck('name')->all();
        $this->assertContains('Input',  $names);
        $this->assertContains('Output', $names);
        $this->assertNotContains('Quality Control', $names, 'QC only present in three-step receipts');
        $this->assertNotContains('Packing Zone',    $names, 'Packing Zone only present in three-step delivery');

        $this->assertNotNull($wh->wh_input_stock_loc_id);
        $this->assertNotNull($wh->wh_output_stock_loc_id);
        $this->assertNull($wh->wh_qc_stock_loc_id);
        $this->assertNull($wh->wh_pack_stock_loc_id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // UoM conversion — real-case round trips (Odoo parity Phase 1 audit)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Ship 750 g of a kg-tracked product; quants debit 0.75 kg and the move's
     * stored qty_done stays in g. Then process a partial return (300 g): the
     * return picking pre-fills qty_done = 0, the operator picks 300 g, and on
     * validate the dest quant must credit 0.30 kg back, not 300 kg.
     */
    public function test_kg_product_partial_g_delivery_then_partial_g_return_roundtrip(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = $this->makeProductWithUom($kg);
        $this->makeQuant($product, qty: 5.0); // 5 kg on hand

        $delivery = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->deliveryOp->id,
                'location_src_id'   => $this->stockLocation->id,
                'location_dest_id'  => $this->customerLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 750.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($delivery);
        $delivery = app(PickingService::class)->validate($delivery->fresh())['picking'];

        $srcQuant = Quant::where('product_id', $product->id)->where('location_id', $this->stockLocation->id)->first();
        $this->assertEqualsWithDelta(4.25, (float) $srcQuant->quantity, 0.0001, 'Source quant must lose 0.75 kg (not 750 kg)');
        $this->assertSame('done', $delivery->moves()->first()->state);

        // Return half: 300 g of the 750 g shipped
        $deliveryMove = $delivery->moves()->first();
        $return = app(PickingService::class)->createReturn($delivery, [$deliveryMove->id => 300.0]);
        $returnMove = $return->moves()->first();
        $this->assertSame($g->id, $returnMove->uom_id, 'Return move must inherit the original move\'s UoM');
        $this->assertEqualsWithDelta(300.0, (float) $returnMove->product_qty, 0.0001);

        app(PickingService::class)->confirm($return);
        app(PickingService::class)->validate($return->fresh());

        $srcQuant->refresh();
        $this->assertEqualsWithDelta(4.55, (float) $srcQuant->quantity, 0.0001, 'Return must credit 0.30 kg (not 300 kg)');
    }

    /**
     * Lot-tracked move (kg product) with detailed operations entered in g.
     * Stock on hand for lot LOT-A is 2 kg; operator enters 600 g done on the
     * line. Quant must drop to 1.4 kg (not crash because 600 > 2).
     */
    public function test_lot_tracked_move_with_g_line_against_kg_product(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = $this->makeProductWithUom($kg, tracking: 'lot');
        $lot = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-A', 'active' => true]);
        $this->makeQuant($product, qty: 2.0, lotId: $lot->id);

        $picking = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->deliveryOp->id,
                'location_src_id'   => $this->stockLocation->id,
                'location_dest_id'  => $this->customerLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 1000.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($picking);
        app(PickingService::class)->checkAvailability($picking);

        // The detailed-operation line is created by checkAvailability with
        // qty_done = reserved (in move UoM = g). Operator edits qty_done to
        // 600 g and validates.
        $move = $picking->fresh()->moves()->first();
        $line = $move->moveLines->first();
        $line->update(['qty_done' => 600.0]);

        app(PickingService::class)->validate($picking->fresh());

        $quant = Quant::where('product_id', $product->id)
            ->where('location_id', $this->stockLocation->id) // scope to source — dest receives +0.6 kg
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertEqualsWithDelta(1.4, (float) $quant->quantity, 0.0001, '2 kg − 600 g = 1.4 kg (not −598 kg)');
    }

    /**
     * Partial validate of a g-denominated delivery must leave a backorder
     * whose product_qty stays in the move's original UoM (g), not silently
     * converted to product UoM (kg). The operator should see g on the
     * backorder, matching the original transfer.
     */
    public function test_backorder_preserves_move_uom_when_qty_done_is_partial(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = $this->makeProductWithUom($kg);
        $this->makeQuant($product, qty: 10.0);

        $delivery = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->deliveryOp->id,
                'location_src_id'   => $this->stockLocation->id,
                'location_dest_id'  => $this->customerLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 5000.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($delivery);

        $moveId = $delivery->moves()->first()->id;
        $result = app(PickingService::class)->validate($delivery->fresh(), [$moveId => 2000.0]); // ship only 2 kg
        $backorder = $result['backorder'];
        $this->assertNotNull($backorder);

        $backorderMove = $backorder->moves()->first();
        $this->assertSame($g->id, $backorderMove->uom_id, 'Backorder must keep g, not silently switch to product UoM');
        $this->assertEqualsWithDelta(3000.0, (float) $backorderMove->product_qty, 0.0001, '5000 g − 2000 g = 3000 g remaining');
    }

    // ─────────────────────────────────────────────────────────────────────
    // PutawayRule — multi-tenant audit (Odoo parity Phase 2 audit)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * AUDIT: a multi-company user creates a PutawayRule in Company A whose
     * `fixed_location_id` lives in Company B. Later, a Company-A-only receipt
     * must NOT be silently redirected into Company B's bin — that would
     * cross-tenant leak stock. resolveFor must reject the cross-company
     * fixed_location.
     */
    public function test_putaway_rule_does_not_redirect_to_cross_tenant_fixed_location(): void
    {
        $companyB = Company::create(['name' => 'Other Tenant', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$companyB->id]);
        // Provision Company B's warehouse so its Stock location exists. We
        // don't keep the warehouse handle — only its auto-created Stock
        // location is the cross-tenant target we want to test.
        app(WarehouseService::class)->create([
            'company_id' => $companyB->id, 'name' => 'B Warehouse', 'short_name' => 'BW', 'active' => true,
        ]);
        $companyBBin = Location::where('company_id', $companyB->id)->where('name', 'Stock')->firstOrFail();

        $product = $this->makeProduct();
        // Rule in Company A pointing at Company B's bin. Use a product-specific
        // match so the rule WOULD fire if not for the cross-tenant guard —
        // otherwise the test passes by accident (no rule matches).
        PutawayRule::create([
            'company_id'        => $this->company->id,
            'location_id'       => $this->stockLocation->id,
            'fixed_location_id' => $companyBBin->id,
            'product_id'        => $product->id,
            'product_category_id' => null,
            'sequence'          => 10,
            'active'            => true,
        ]);

        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $picking  = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $product->name]]
        );

        $move = $picking->fresh()->moves()->first();
        $this->assertSame(
            $this->stockLocation->id,
            $move->location_dest_id,
            'Receipt must stay in Company A — putaway must refuse to redirect to a cross-tenant bin.'
        );
    }

    /**
     * Two moves in one picking, each with its own product-specific putaway
     * rule pointing at a different bin. addMove fires per-move, so each move
     * should land at its own bin independently.
     */
    public function test_multi_move_picking_routes_each_product_to_its_own_putaway_bin(): void
    {
        $binA = Location::create(['company_id' => $this->company->id, 'name' => 'Aisle A', 'parent_id' => $this->stockLocation->id, 'usage' => 'internal', 'active' => true]);
        $binB = Location::create(['company_id' => $this->company->id, 'name' => 'Aisle B', 'parent_id' => $this->stockLocation->id, 'usage' => 'internal', 'active' => true]);

        $productA = $this->makeProduct();
        $productB = $this->makeProduct();

        PutawayRule::create(['company_id' => $this->company->id, 'location_id' => $this->stockLocation->id, 'fixed_location_id' => $binA->id, 'product_id' => $productA->id, 'sequence' => 10, 'active' => true]);
        PutawayRule::create(['company_id' => $this->company->id, 'location_id' => $this->stockLocation->id, 'fixed_location_id' => $binB->id, 'product_id' => $productB->id, 'sequence' => 10, 'active' => true]);

        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $picking  = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [
                ['product_id' => $productA->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $productA->name],
                ['product_id' => $productB->id, 'uom_id' => $this->uom->id, 'product_qty' => 3.0, 'name' => $productB->name],
            ]
        );

        $moves = $picking->fresh()->moves()->orderBy('id')->get();
        $this->assertSame($binA->id, $moves[0]->location_dest_id);
        $this->assertSame($binB->id, $moves[1]->location_dest_id);
    }

    /**
     * Putaway rules with company_id = null are global — should match a picking
     * in any company. The matching fixed_location must also be visible to the
     * picking's company (null OR same company_id).
     */
    public function test_global_putaway_rule_with_shared_fixed_location_applies(): void
    {
        $sharedBin = Location::create(['company_id' => null, 'name' => 'Shared Holding Bin', 'usage' => 'internal', 'active' => true]);
        $product = $this->makeProduct();

        // Global rule (company_id null) keyed by the product's category so
        // resolveFor's category tier can match. A rule with both product_id
        // and category_id null is a no-op by design (Odoo doesn't expose
        // catchall putaway either).
        PutawayRule::create([
            'company_id'          => null,
            'location_id'         => $this->stockLocation->id,
            'fixed_location_id'   => $sharedBin->id,
            'product_id'          => null,
            'product_category_id' => $product->category_id,
            'sequence'            => 10,
            'active'              => true,
        ]);

        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $picking  = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $product->name]]
        );

        $move = $picking->fresh()->moves()->first();
        $this->assertSame($sharedBin->id, $move->location_dest_id);
    }

    // ─────────────────────────────────────────────────────────────────────
    // ProductSupplier — multi-tenant audit (Odoo parity Phase 3 audit)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * AUDIT: a multi-company user added a ProductSupplier whose `partner_id`
     * lives in Company B. Replenishment from Company A must NOT stamp the
     * Company-B contact as partner_id — that would link two tenants on a
     * single picking, surface Company B's contact in Company A's audit feed,
     * and break the company-scoped contact lookup downstream.
     */
    public function test_replenishment_does_not_stamp_cross_tenant_partner(): void
    {
        $companyB = Company::create(['name' => 'Vendor Tenant', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$companyB->id]);
        $vendorInB = Contact::create([
            'company_id'  => $companyB->id,
            'name'        => 'Cross-Tenant Vendor',
            'is_supplier' => true, 'active' => true,
            'created_by'  => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $product = $this->makeProduct();
        ProductSupplier::create([
            'product_id' => $product->id,
            'partner_id' => $vendorInB->id, // cross-tenant link slipped through a multi-company edit
            'min_qty'    => 1, 'price' => 10.0, 'delay' => 7,
            'active'     => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $rule = ReorderRule::create([
            'company_id'   => $this->company->id,
            'product_id'   => $product->id,
            'location_id'  => $this->stockLocation->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_min'      => 5, 'qty_max' => 50, 'qty_on_hand' => 0, 'qty_forecast' => 0, 'qty_multiple' => 1,
            'lead_days'    => 0,
            'active'       => true,
            'created_by'   => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule));

        $picking = Picking::where('company_id', $this->company->id)->where('origin', 'Replenishment')->first();
        $this->assertNotNull($picking);
        $this->assertNull(
            $picking->partner_id,
            'Replenishment must skip a supplier whose partner is in a different company.'
        );
    }

    /**
     * Combined audit: kg product, g delivery, partial return — the whole
     * round trip exercising UoM conversion through the create → confirm →
     * checkAvailability → validate → createReturn paths.
     */
    public function test_full_uom_lifecycle_simulation(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = $this->makeProductWithUom($kg);
        $this->makeQuant($product, qty: 10.0);

        $delivery = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->deliveryOp->id,
                'location_src_id'   => $this->stockLocation->id,
                'location_dest_id'  => $this->customerLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 4000.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($delivery);
        app(PickingService::class)->checkAvailability($delivery);

        $srcQuant = Quant::where('product_id', $product->id)->where('location_id', $this->stockLocation->id)->first();
        $this->assertEqualsWithDelta(4.0, (float) $srcQuant->reserved_quantity, 0.0001, 'Reserved must be 4 kg, not 4000 kg');

        $delivery = app(PickingService::class)->validate($delivery->fresh())['picking'];
        $srcQuant->refresh();
        $this->assertEqualsWithDelta(6.0, (float) $srcQuant->quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $srcQuant->reserved_quantity, 0.0001);

        // Partial return: 1500 g back
        $moveId = $delivery->moves()->first()->id;
        $return = app(PickingService::class)->createReturn($delivery, [$moveId => 1500.0]);
        app(PickingService::class)->confirm($return);
        app(PickingService::class)->validate($return->fresh());

        $srcQuant->refresh();
        $this->assertEqualsWithDelta(7.5, (float) $srcQuant->quantity, 0.0001, 'After return: 6 + 1.5 = 7.5 kg');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Push-rule chain engine (Odoo parity Phase 4)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * 1-step warehouse — receipt validate must NOT create a chain picking.
     * Regression guard: the chain engine only fires when push rules match the
     * destination, which only happens for multi-step warehouses.
     */
    public function test_one_step_receipt_does_not_create_chain_picking(): void
    {
        // Default warehouse from setUp() is 1-step (reception_steps='one_step').
        $product = $this->makeProduct();
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $picking = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($picking);
        $result = app(PickingService::class)->validate($picking->fresh());

        $this->assertEmpty($result['chains'] ?? [], '1-step receipt must not create chain pickings');
        // Only the original receipt should exist for this company.
        $this->assertSame(1, Picking::where('company_id', $this->company->id)->count());
    }

    /**
     * 2-step warehouse — validating a receipt at Input auto-creates an
     * Internal Transfer picking Input → Stock with the same product/qty
     * (in the same UoM), `origin_picking_id` pointing back to the receipt,
     * and state = 'confirmed' (ready for the warehouse worker to validate).
     */
    public function test_two_step_receipt_auto_creates_input_to_stock_chain(): void
    {
        $company = Company::create(['name' => '2-Step Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);

        app(WarehouseService::class)->create([
            'company_id'      => $company->id,
            'name'            => 'Two-Step WH',
            'short_name'      => 'TS',
            'reception_steps' => 'two_steps',
            'delivery_steps'  => 'one_step',
            'active'          => true,
        ]);
        $input    = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $stock    = Location::where('company_id', $company->id)->where('name', 'Stock')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();
        $internalOp = OperationType::where('company_id', $company->id)->where('code', 'internal')->firstOrFail();

        // Receipt's default dest should be Input now (was Stock pre-Phase 4).
        $this->assertSame($input->id, $receiptOp->default_location_dest_id);

        $product = Product::create([
            'name' => 'Chained Widget', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id, // 2-step: receipt → Input
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 10.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($receipt);
        $result = app(PickingService::class)->validate($receipt->fresh());

        $this->assertCount(1, $result['chains'] ?? []);
        $chain = $result['chains'][0];

        $this->assertSame($input->id, $chain->location_src_id, 'Chain picking must start at Input');
        $this->assertSame($stock->id, $chain->location_dest_id, 'Chain picking must end at Stock');
        $this->assertSame($internalOp->id, $chain->operation_type_id, 'Chain uses warehouse internal OpType');
        $this->assertSame($result['picking']->id, $chain->origin_picking_id, 'Chain origin links back to receipt');
        $this->assertSame('confirmed', $chain->state, 'Chain starts confirmed, ready for worker validate');

        $chainMove = $chain->moves()->first();
        $this->assertSame($product->id, $chainMove->product_id);
        $this->assertEqualsWithDelta(10.0, (float) $chainMove->product_qty, 0.0001);

        // Stock was deposited at Input by the receipt — chain picking can now
        // be checked-available and validated to complete the multi-step flow.
        $inputQty = (float) Quant::where('product_id', $product->id)->where('location_id', $input->id)->sum('quantity');
        $this->assertEqualsWithDelta(10.0, $inputQty, 0.0001);
    }

    /**
     * 3-step warehouse — receipt validate creates Input → QC. Validating that
     * creates QC → Stock. Each chain picking is independent (its own validate
     * triggers the next link), so the engine effectively cascades.
     */
    public function test_three_step_receipt_cascades_input_to_qc_to_stock(): void
    {
        $company = Company::create(['name' => '3-Step Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);

        app(WarehouseService::class)->create([
            'company_id'      => $company->id,
            'name'            => 'Three-Step WH',
            'short_name'      => 'TH',
            'reception_steps' => 'three_steps',
            'delivery_steps'  => 'one_step',
            'active'          => true,
        ]);
        $input = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $qc    = Location::where('company_id', $company->id)->where('name', 'Quality Control')->firstOrFail();
        $stock = Location::where('company_id', $company->id)->where('name', 'Stock')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();

        $product = Product::create([
            'name' => 'Cascading Widget', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        // Step 1: Receipt Supplier → Input
        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 7.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($receipt);
        $step1 = app(PickingService::class)->validate($receipt->fresh());

        $this->assertCount(1, $step1['chains']);
        $inputToQc = $step1['chains'][0];
        $this->assertSame($input->id, $inputToQc->location_src_id);
        $this->assertSame($qc->id, $inputToQc->location_dest_id);

        // Step 2: validate Input → QC, which auto-creates QC → Stock
        app(PickingService::class)->checkAvailability($inputToQc);
        $step2 = app(PickingService::class)->validate($inputToQc->fresh());

        $this->assertCount(1, $step2['chains']);
        $qcToStock = $step2['chains'][0];
        $this->assertSame($qc->id,    $qcToStock->location_src_id);
        $this->assertSame($stock->id, $qcToStock->location_dest_id);

        // Step 3: validate QC → Stock — should NOT trigger another chain
        app(PickingService::class)->checkAvailability($qcToStock);
        $step3 = app(PickingService::class)->validate($qcToStock->fresh());
        $this->assertEmpty($step3['chains'], 'Final chain link must not retrigger');

        // Final state: stock at Stock = 7 (after the full Input→QC→Stock flow)
        $stockQty = (float) Quant::where('product_id', $product->id)->where('location_id', $stock->id)->sum('quantity');
        $this->assertEqualsWithDelta(7.0, $stockQty, 0.0001);
    }

    /**
     * Chain engine must skip rules whose `operation_type_id` is in a
     * different company than the picking — defense-in-depth against a
     * multi-company user who configured a cross-tenant rule.
     */
    public function test_chain_engine_rejects_cross_tenant_op_type(): void
    {
        $companyB = Company::create(['name' => 'B Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$companyB->id]);
        app(WarehouseService::class)->create([
            'company_id' => $companyB->id, 'name' => 'B WH', 'short_name' => 'BW',
            'active' => true,
        ]);
        $internalB = OperationType::where('company_id', $companyB->id)->where('code', 'internal')->firstOrFail();

        // Rule in Company A pointing at Company B's internal OpType.
        $route = Route::create([
            'company_id' => $this->company->id, 'name' => 'Cross-tenant route', 'sequence' => 10,
            'product_selectable' => false, 'product_category_selectable' => false,
            'warehouse_selectable' => false, 'active' => true,
        ]);
        $internalBin = Location::create([
            'company_id' => $this->company->id, 'parent_id' => $this->stockLocation->id,
            'name' => 'Some bin', 'usage' => 'internal', 'active' => true,
        ]);
        RouteRule::create([
            'company_id'        => $this->company->id,
            'route_id'          => $route->id,
            'operation_type_id' => $internalB->id,         // cross-tenant
            'location_src_id'   => $this->stockLocation->id,
            'location_dest_id'  => $internalBin->id,
            'name'              => 'Bad rule',
            'action'            => 'push',
            'sequence'          => 10,
            'active'            => true,
        ]);

        $product = $this->makeProduct();
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $picking = app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->stockLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($picking);
        $result = app(PickingService::class)->validate($picking->fresh());

        $this->assertEmpty($result['chains'], 'Cross-tenant op_type must be rejected by the chain engine.');
    }

    public function test_chain_picking_inherits_origin_partner_id(): void
    {
        $company = Company::create(['name' => 'Vendor 2-Step Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        app(WarehouseService::class)->create([
            'company_id' => $company->id, 'name' => 'V WH', 'short_name' => 'VW',
            'reception_steps' => 'two_steps', 'delivery_steps' => 'one_step', 'active' => true,
        ]);
        $input    = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();
        $vendor = Contact::create([
            'company_id' => $company->id, 'name' => 'ACME', 'is_supplier' => true, 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $product = Product::create([
            'name' => 'Vendor Widget', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'partner_id'        => $vendor->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 3.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($receipt);
        $result = app(PickingService::class)->validate($receipt->fresh());

        $this->assertCount(1, $result['chains']);
        $this->assertSame($vendor->id, $result['chains'][0]->partner_id, 'Chain picking carries the vendor forward for traceability.');
    }

    /**
     * Phase 4 audit: archiving the parent Route at the UI must disable its
     * rules even if `RouteRule.active` is still true. The schema has no DB
     * cascade between the two, so the engine must enforce it.
     */
    public function test_chain_engine_skips_rules_whose_route_is_archived(): void
    {
        $company = Company::create(['name' => 'Archive Route Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        app(WarehouseService::class)->create([
            'company_id' => $company->id, 'name' => 'AR WH', 'short_name' => 'AR',
            'reception_steps' => 'two_steps', 'delivery_steps' => 'one_step', 'active' => true,
        ]);
        $input = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();

        // Archive the Route that WarehouseService just auto-created.
        Route::where('company_id', $company->id)->update(['active' => false]);

        $product = Product::create([
            'name' => 'Archived-route Widget', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($receipt);
        $result = app(PickingService::class)->validate($receipt->fresh());

        $this->assertEmpty($result['chains'], 'Archived Route must disable its rules.');
    }

    /**
     * Multi-product receipt landing at the same Input must produce ONE chain
     * picking with TWO moves — not one chain per move. Verifies the engine's
     * group-by-destination logic.
     */
    public function test_multi_product_receipt_creates_single_chain_with_multiple_moves(): void
    {
        $company = Company::create(['name' => 'Multi-product Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        app(WarehouseService::class)->create([
            'company_id' => $company->id, 'name' => 'MP WH', 'short_name' => 'MP',
            'reception_steps' => 'two_steps', 'delivery_steps' => 'one_step', 'active' => true,
        ]);
        $input = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();

        $productA = Product::create([
            'name' => 'A', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $productB = Product::create([
            'name' => 'B', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [
                ['product_id' => $productA->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => 'A'],
                ['product_id' => $productB->id, 'uom_id' => $this->uom->id, 'product_qty' => 3.0, 'name' => 'B'],
            ]
        );
        app(PickingService::class)->confirm($receipt);
        $result = app(PickingService::class)->validate($receipt->fresh());

        $this->assertCount(1, $result['chains'], 'Both products landing at Input share one chain picking.');
        $this->assertSame(2, $result['chains'][0]->moves()->count(), 'Chain picking carries both moves.');
    }

    /**
     * Partial validate: receipt has 10 ordered but only 7 received. Chain
     * picking should carry 7 (the actual qty_done), not 10. The backorder
     * for the remaining 3 will create its own chain when validated later.
     */
    public function test_partial_validate_creates_chain_with_qty_done_not_ordered_qty(): void
    {
        $company = Company::create(['name' => 'Partial Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        app(WarehouseService::class)->create([
            'company_id' => $company->id, 'name' => 'PR WH', 'short_name' => 'PR',
            'reception_steps' => 'two_steps', 'delivery_steps' => 'one_step', 'active' => true,
        ]);
        $input = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();

        $product = Product::create([
            'name' => 'PartialP', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();

        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 10.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($receipt);
        $moveId = $receipt->moves()->first()->id;
        $result = app(PickingService::class)->validate($receipt->fresh(), [$moveId => 7.0]); // only 7 of 10

        $this->assertCount(1, $result['chains']);
        $this->assertNotNull($result['backorder'], 'Partial validate must leave a backorder for the remainder.');

        $chainMove = $result['chains'][0]->moves()->first();
        $this->assertEqualsWithDelta(7.0, (float) $chainMove->product_qty, 0.0001, 'Chain carries qty_done (7), not ordered qty (10).');

        $backorderMove = $result['backorder']->moves()->first();
        $this->assertEqualsWithDelta(3.0, (float) $backorderMove->product_qty, 0.0001, 'Backorder holds the unshipped 3.');
    }

    /**
     * Phase 2 × Phase 4 composition: a 2-step receipt with a putaway rule
     * at Stock should produce a chain picking Input → Stock whose MOVE dest
     * is redirected to the putaway bin. The picking-level dest stays at
     * Stock (the warehouse level), per Phase 2.
     */
    public function test_chain_picking_move_dest_honors_putaway_rule(): void
    {
        $company = Company::create(['name' => 'Putaway × Chain Co', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        app(WarehouseService::class)->create([
            'company_id' => $company->id, 'name' => 'PC WH', 'short_name' => 'PC',
            'reception_steps' => 'two_steps', 'delivery_steps' => 'one_step', 'active' => true,
        ]);
        $input = Location::where('company_id', $company->id)->where('name', 'Input')->firstOrFail();
        $stock = Location::where('company_id', $company->id)->where('name', 'Stock')->firstOrFail();
        $receiptOp = OperationType::where('company_id', $company->id)->where('code', 'incoming')->firstOrFail();

        $bin = Location::create([
            'company_id' => $company->id, 'parent_id' => $stock->id,
            'name' => 'Shelf 7', 'usage' => 'internal', 'active' => true,
        ]);
        $product = Product::create([
            'name' => 'Putaway Widget', 'company_id' => $company->id,
            'uom_id' => $this->uom->id, 'uom_po_id' => $this->uom->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        \App\Models\Inventory\PutawayRule::create([
            'company_id'        => $company->id,
            'location_id'       => $stock->id,
            'fixed_location_id' => $bin->id,
            'product_id'        => $product->id,
            'sequence'          => 10,
            'active'            => true,
        ]);

        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $receipt = app(PickingService::class)->create(
            [
                'company_id'        => $company->id,
                'operation_type_id' => $receiptOp->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $input->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => 4.0, 'name' => $product->name]]
        );
        app(PickingService::class)->confirm($receipt);
        $result = app(PickingService::class)->validate($receipt->fresh());

        $chain = $result['chains'][0];
        $this->assertSame($stock->id, $chain->location_dest_id, 'Chain picking header stays at Stock (warehouse level).');

        $chainMove = $chain->moves()->first();
        $this->assertSame($bin->id, $chainMove->location_dest_id, 'Chain move dest is putaway-redirected to the bin.');
    }

    // ─────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeProductWithUom(Uom $uom, string $strategy = 'fifo', string $tracking = 'none'): Product
    {
        $category = ProductCategory::create([
            'name'             => 'Cat-' . uniqid(),
            'removal_strategy' => $strategy,
            'active'           => true,
        ]);
        return Product::create([
            'name'         => 'P-' . uniqid(),
            'company_id'   => $this->company->id,
            'category_id'  => $category->id,
            'uom_id'       => $uom->id,
            'uom_po_id'    => $uom->id,
            'product_type' => 'storable',
            'tracking'     => $tracking,
            'active'       => true,
        ]);
    }

    private function makeProduct(string $strategy = 'fifo', string $tracking = 'none'): Product
    {
        $category = ProductCategory::create([
            'name'             => 'Cat-' . uniqid(),
            'removal_strategy' => $strategy,
            'active'           => true,
        ]);
        return Product::create([
            'name'         => 'P-' . uniqid(),
            'company_id'   => $this->company->id,
            'category_id'  => $category->id,
            'uom_id'       => $this->uom->id,
            'uom_po_id'    => $this->uom->id,
            'product_type' => 'storable',
            'tracking'     => $tracking,
            'active'       => true,
        ]);
    }

    private function makeQuant(Product $product, float $qty, string $inDate = null, ?int $lotId = null): Quant
    {
        return Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->stockLocation->id,
            'lot_id'      => $lotId,
            'quantity'    => $qty,
            'in_date'     => $inDate ? Carbon::parse($inDate) : now(),
        ]);
    }

    private function makeDeliveryPicking(Product $product, float $qty): Picking
    {
        return app(PickingService::class)->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->deliveryOp->id,
                'location_src_id'   => $this->stockLocation->id,
                'location_dest_id'  => $this->customerLocation->id,
                'scheduled_date'    => now(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $this->uom->id, 'product_qty' => $qty, 'name' => $product->name]],
        );
    }
}
