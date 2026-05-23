<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\Quant;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\PickingService;
use App\Services\Inventory\WarehouseService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for lot/serial tracking throughout the inventory flow.
 *
 * Covers:
 * - Lot creation on receipt validate
 * - FIFO reservation (oldest lot consumed first)
 * - Validation guards: no move lines, missing lot name
 * - Lot-tracked delivery: reserve → validate deducts correct quant
 * - Serial-number specifics (1 unit per line)
 * - Lot model helpers (isExpired, getOnHandQty)
 * - Full HTTP CRUD for Lot records
 * - Company isolation
 */
class InventoryLotTest extends TestCase
{
    use RefreshDatabase;

    private PickingService   $pickingService;
    private User             $admin;
    private Company          $company;
    private Uom              $units;
    private Location         $stockLoc;
    private Location         $supplierLoc;
    private Location         $customerLoc;
    private OperationType    $receiptOp;
    private OperationType    $deliveryOp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->pickingService = app(PickingService::class);

        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('Lot Test Co');
        $this->units   = Uom::where('name', 'Units')->firstOrFail();

        $this->actingAs($this->admin);

        app(WarehouseService::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Lot Warehouse',
            'short_name' => 'LW',
            'active'     => true,
        ]);

        $this->stockLoc    = Location::where('company_id', $this->company->id)->where('name', 'Stock')->firstOrFail();
        $this->supplierLoc = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $this->customerLoc = Location::where('usage', 'customer')->whereNull('company_id')->firstOrFail();
        $this->receiptOp   = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();
        $this->deliveryOp  = OperationType::where('company_id', $this->company->id)->where('code', 'outgoing')->firstOrFail();
    }

    // ── Receipt: lot creation ──────────────────────────────────────────────────

    public function test_lot_tracked_receipt_creates_lot_record_and_quant(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        $picking = $this->createReceipt($product, 50);
        $this->pickingService->confirm($picking);

        $move = $picking->moves()->first();
        $this->pickingService->addMoveLine($move, [
            'lot_name'    => 'LOT-2025-001',
            'qty_done'    => 50,
            'reserved_qty' => 0,
            'date'        => now()->toDateString(),
        ]);

        $result = $this->pickingService->validate($picking->fresh());
        $this->assertTrue($result['picking']->isDone());

        // Lot record must have been created
        $lot = Lot::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('name', 'LOT-2025-001')
            ->first();
        $this->assertNotNull($lot, 'Lot record should be auto-created during validate');

        // Quant at stock must exist with that lot and qty 50
        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('location_id', $this->stockLoc->id)
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertNotNull($quant);
        $this->assertSame(50.0, (float) $quant->quantity);
    }

    public function test_serial_tracked_receipt_creates_one_quant_per_serial(): void
    {
        $product = $this->makeProduct(tracking: 'serial');

        $picking = $this->createReceipt($product, 2);
        $this->pickingService->confirm($picking);

        $move = $picking->moves()->first();
        $this->pickingService->addMoveLine($move, [
            'lot_name'    => 'SN-001',
            'qty_done'    => 1,
            'reserved_qty' => 0,
            'date'        => now()->toDateString(),
        ]);
        $this->pickingService->addMoveLine($move, [
            'lot_name'    => 'SN-002',
            'qty_done'    => 1,
            'reserved_qty' => 0,
            'date'        => now()->toDateString(),
        ]);

        $this->pickingService->validate($picking->fresh());

        // Two separate quants, one per serial
        $quants = Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('location_id', $this->stockLoc->id)
            ->get();

        $this->assertCount(2, $quants);
        $this->assertSame(1.0, (float) $quants[0]->quantity);
        $this->assertSame(1.0, (float) $quants[1]->quantity);
    }

    public function test_receipt_with_existing_lot_adds_to_existing_quant(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        // Pre-existing lot and quant
        $lot = Lot::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'name'       => 'LOT-EXISTING',
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->stockLoc->id,
            'lot_id'      => $lot->id,
            'quantity'    => 30,
            'in_date'     => now()->subDays(10),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $picking = $this->createReceipt($product, 20);
        $this->pickingService->confirm($picking);

        $move = $picking->moves()->first();
        $this->pickingService->addMoveLine($move, [
            'lot_id'   => $lot->id,
            'qty_done' => 20,
            'reserved_qty' => 0,
            'date'     => now()->toDateString(),
        ]);

        $this->pickingService->validate($picking->fresh());

        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('location_id', $this->stockLoc->id)
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertSame(50.0, (float) $quant->quantity);
    }

    // ── Delivery: lot reservation and consumption ─────────────────────────────

    public function test_lot_tracked_delivery_reserves_from_lot_quant(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot     = $this->seedLotStock($product, 40, 'LOT-D001');

        $picking = $this->createDelivery($product, 15);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->checkAvailability($picking);

        $this->assertTrue($picking->isAssigned());

        // checkAvailability should have created a MoveLine with lot_id
        $move = $picking->moves()->first();
        $this->assertSame(1, $move->moveLines()->count());
        $this->assertSame($lot->id, $move->moveLines()->first()->lot_id);
    }

    public function test_lot_tracked_delivery_validate_deducts_lot_quant(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot     = $this->seedLotStock($product, 40, 'LOT-D002');

        $picking = $this->createDelivery($product, 15);
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);

        $result = $this->pickingService->validate($picking->fresh());
        $this->assertTrue($result['picking']->isDone());

        // Source quant should be reduced
        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('location_id', $this->stockLoc->id)
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertSame(25.0, (float) $quant->quantity);

        // Destination quant should be created with lot
        $destQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('location_id', $this->customerLoc->id)
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertNotNull($destQuant);
        $this->assertSame(15.0, (float) $destQuant->quantity);
    }

    // ── Validation guards ─────────────────────────────────────────────────────

    public function test_validate_lot_product_without_move_lines_throws(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        $picking = $this->createReceipt($product, 10);
        $this->pickingService->confirm($picking);
        // Intentionally do NOT add any move lines

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/lot\/serial/i');
        $this->pickingService->validate($picking->fresh());
    }

    public function test_validate_lot_product_with_move_line_missing_lot_name_throws(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        $picking = $this->createReceipt($product, 10);
        $this->pickingService->confirm($picking);

        $move = $picking->moves()->first();
        // Add a line with no lot_id and no lot_name
        $this->pickingService->addMoveLine($move, [
            'qty_done'    => 10,
            'reserved_qty' => 0,
            'date'        => now()->toDateString(),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/lot\/serial/i');
        $this->pickingService->validate($picking->fresh());
    }

    // ── FIFO reservation ──────────────────────────────────────────────────────

    public function test_fifo_reservation_consumes_oldest_lot_first(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        // Two lots: OLD received 10 days ago, NEW received today
        $lotOld = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-OLD', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $lotNew = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-NEW', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        Quant::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'location_id' => $this->stockLoc->id, 'lot_id' => $lotOld->id, 'quantity' => 20, 'in_date' => now()->subDays(10), 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        Quant::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'location_id' => $this->stockLoc->id, 'lot_id' => $lotNew->id, 'quantity' => 20, 'in_date' => now(),             'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $picking = $this->createDelivery($product, 15);
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);

        $move = $picking->moves()->first();
        $line = $move->moveLines()->first();

        // FIFO: oldest lot should be reserved first
        $this->assertSame($lotOld->id, $line->lot_id);
        $this->assertSame(15.0, (float) $line->reserved_qty);
    }

    public function test_fifo_spans_multiple_lots_when_single_lot_insufficient(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        $lotOld = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-A', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $lotNew = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'LOT-B', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        Quant::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'location_id' => $this->stockLoc->id, 'lot_id' => $lotOld->id, 'quantity' => 8, 'in_date' => now()->subDays(5), 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        Quant::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'location_id' => $this->stockLoc->id, 'lot_id' => $lotNew->id, 'quantity' => 12, 'in_date' => now(),            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $picking = $this->createDelivery($product, 15);
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);

        $move  = $picking->moves()->first();
        $lines = $move->moveLines()->orderBy('id')->get();

        $this->assertCount(2, $lines);
        $this->assertSame($lotOld->id, $lines[0]->lot_id);
        $this->assertSame(8.0, (float) $lines[0]->reserved_qty);
        $this->assertSame($lotNew->id, $lines[1]->lot_id);
        $this->assertSame(7.0, (float) $lines[1]->reserved_qty);
    }

    // ── Lot model helpers ─────────────────────────────────────────────────────

    public function test_lot_get_on_hand_qty_counts_internal_location_quants_only(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot     = $this->seedLotStock($product, 30, 'LOT-QTY');

        // Add a quant at customer location (non-internal) — should NOT be counted
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->customerLoc->id,
            'lot_id'      => $lot->id,
            'quantity'    => 10,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $this->assertSame(30.0, $lot->getOnHandQty());
    }

    public function test_lot_is_expired_returns_true_when_expiry_is_in_past(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot = Lot::create([
            'company_id'      => $this->company->id,
            'product_id'      => $product->id,
            'name'            => 'LOT-EXP',
            'expiration_date' => now()->subDays(1),
            'active'          => true,
            'created_by'      => $this->admin->id,
            'updated_by'      => $this->admin->id,
        ]);

        $this->assertTrue($lot->isExpired());
    }

    public function test_lot_is_not_expired_when_expiry_is_in_future(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot = Lot::create([
            'company_id'      => $this->company->id,
            'product_id'      => $product->id,
            'name'            => 'LOT-FRESH',
            'expiration_date' => now()->addDays(30),
            'active'          => true,
            'created_by'      => $this->admin->id,
            'updated_by'      => $this->admin->id,
        ]);

        $this->assertFalse($lot->isExpired());
    }

    public function test_lot_without_expiry_date_is_not_expired(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot = Lot::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'name'       => 'LOT-NO-EXP',
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->assertFalse($lot->isExpired());
    }

    // ── HTTP: Lot CRUD ────────────────────────────────────────────────────────

    public function test_admin_can_view_lots_index(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'VISIBLE-LOT', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.lots.index'))
            ->assertOk()
            ->assertSee('VISIBLE-LOT');
    }

    public function test_admin_can_view_lot_show_page(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'SHOW-LOT', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.lots.show', $lot))
            ->assertOk()
            ->assertSee('SHOW-LOT');
    }

    public function test_admin_can_create_lot_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.lots.create'))
            ->assertOk();
    }

    public function test_admin_can_store_lot_via_http(): void
    {
        $product = $this->makeProduct(tracking: 'lot');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.lots.store'), [
                'company_id' => $this->company->id,
                'product_id' => $product->id,
                'name'       => 'HTTP-LOT-001',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_lots', [
            'company_id' => $this->company->id,
            'name'       => 'HTTP-LOT-001',
        ]);
    }

    public function test_admin_can_update_lot_via_http(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'UPDATE-ME', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->put(route('inventory.lots.update', $lot), [
                'name' => 'UPDATED-LOT',
                'ref'  => 'REF-001',
            ])
            ->assertRedirect();

        $this->assertSame('UPDATED-LOT', $lot->fresh()->name);
    }

    public function test_admin_can_delete_lot_via_http(): void
    {
        $product = $this->makeProduct(tracking: 'lot');
        $lot = Lot::create(['company_id' => $this->company->id, 'product_id' => $product->id, 'name' => 'DELETE-ME', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $id = $lot->id;

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->delete(route('inventory.lots.delete', $lot))
            ->assertRedirect(route('inventory.lots.index'));

        $this->assertSoftDeleted('inventory_lots', ['id' => $id]);
    }

    // ── Company isolation ─────────────────────────────────────────────────────

    public function test_company_isolation_prevents_cross_company_lot_access(): void
    {
        $otherCompany = Company::create(['name' => 'Other Lot Co', 'active' => true]);
        $product = $this->makeProduct(tracking: 'lot');

        $otherLot = Lot::create([
            'company_id' => $otherCompany->id,
            'product_id' => $product->id,
            'name'       => 'OTHER-LOT',
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.lots.show', $otherLot))
            ->assertForbidden();
    }

    public function test_lots_index_does_not_show_other_company_lots(): void
    {
        $otherCompany = Company::create(['name' => 'Hidden Lot Co', 'active' => true]);
        $product = $this->makeProduct(tracking: 'lot');

        Lot::create(['company_id' => $otherCompany->id, 'product_id' => $product->id, 'name' => 'HIDDEN-LOT', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.lots.index'))
            ->assertOk()
            ->assertDontSee('HIDDEN-LOT');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeProduct(string $tracking = 'none'): Product
    {
        return Product::create([
            'name'         => 'Product-' . $tracking . '-' . uniqid(),
            'company_id'   => $this->company->id,
            'uom_id'       => $this->units->id,
            'uom_po_id'    => $this->units->id,
            'product_type' => 'storable',
            'tracking'     => $tracking,
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);
    }

    private function seedLotStock(Product $product, float $qty, string $lotName): Lot
    {
        $lot = Lot::create([
            'company_id' => $this->company->id,
            'product_id' => $product->id,
            'name'       => $lotName,
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->stockLoc->id,
            'lot_id'      => $lot->id,
            'quantity'    => $qty,
            'in_date'     => now()->subDays(2),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        return $lot;
    }

    private function createReceipt(Product $product, float $qty): Picking
    {
        return $this->pickingService->create([
            'company_id'        => $this->company->id,
            'operation_type_id' => $this->receiptOp->id,
            'location_src_id'   => $this->supplierLoc->id,
            'location_dest_id'  => $this->stockLoc->id,
            'scheduled_date'    => now()->toDateString(),
            'active'            => true,
        ], [[
            'product_id'  => $product->id,
            'uom_id'      => $this->units->id,
            'product_qty' => $qty,
            'name'        => $product->name,
        ]]);
    }

    private function createDelivery(Product $product, float $qty): Picking
    {
        return $this->pickingService->create([
            'company_id'        => $this->company->id,
            'operation_type_id' => $this->deliveryOp->id,
            'location_src_id'   => $this->stockLoc->id,
            'location_dest_id'  => $this->customerLoc->id,
            'scheduled_date'    => now()->toDateString(),
            'active'            => true,
        ], [[
            'product_id'  => $product->id,
            'uom_id'      => $this->units->id,
            'product_qty' => $qty,
            'name'        => $product->name,
        ]]);
    }

    private function createCompany(string $name): Company
    {
        $company = Company::create(['name' => $name, 'active' => true, 'currency' => 'USD']);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        $this->admin->update(['company_id' => $company->id]);
        return $company;
    }
}
