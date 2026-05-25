<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\Quant;
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
    // Helpers
    // ─────────────────────────────────────────────────────────────────────

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
