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
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

    private function makeProductWithUom(Uom $uom, string $strategy = 'fifo', string $tracking = 'none'): Product
    {
        $category = ProductCategory::create([
            'name'             => 'Cat-' . uniqid(),
            'removal_strategy' => $strategy,
            'costing_method'   => 'standard_price',
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
            'costing_method'   => 'standard_price',
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
