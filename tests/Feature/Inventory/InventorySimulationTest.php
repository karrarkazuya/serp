<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\Location;
use App\Models\Inventory\Move;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ScrapOrder;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\AdjustmentService;
use App\Services\Inventory\PickingService;
use App\Services\Inventory\ProductService;
use App\Services\Inventory\ScrapService;
use App\Services\Inventory\WarehouseService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Full end-to-end inventory simulation.
 *
 * Each test runs an independent business cycle and compares every quant,
 * move count, and picking state against hard-coded expected values.
 *
 * Scenario shared across tests
 * ─────────────────────────────
 * Company    : Simulation Corp
 * Warehouse  : SIM  (Stock, Shelf B internal locations)
 * Products   : Widget Pro (storable/none), Widget Lite (storable/none),
 *              Supply Kit (storable/none)
 */
class InventorySimulationTest extends TestCase
{
    use RefreshDatabase;

    // ── Services ──────────────────────────────────────────────────────────────
    private WarehouseService  $warehouseSvc;
    private PickingService    $pickingSvc;
    private ScrapService      $scrapSvc;
    private AdjustmentService $adjustmentSvc;

    // ── Actors ────────────────────────────────────────────────────────────────
    private User    $admin;
    private Company $company;

    // ── Products ──────────────────────────────────────────────────────────────
    private Product $widgetPro;
    private Product $widgetLite;
    private Product $supplyKit;

    // ── Locations ─────────────────────────────────────────────────────────────
    private Location $stockLoc;
    private Location $shelfLoc;
    private Location $supplierLoc;
    private Location $customerLoc;
    private Location $scrapLoc;

    // ── Operation types ───────────────────────────────────────────────────────
    private OperationType $receiptOp;
    private OperationType $deliveryOp;
    private OperationType $internalOp;

    private Uom $units;

    // ── Quant snapshots ───────────────────────────────────────────────────────
    // Used to compare state before and after individual steps within a test.
    private array $snapshot = [];

    // ─────────────────────────────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);

        $this->warehouseSvc  = app(WarehouseService::class);
        $this->pickingSvc    = app(PickingService::class);
        $this->scrapSvc      = app(ScrapService::class);
        $this->adjustmentSvc = app(AdjustmentService::class);

        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = Company::create(['name' => 'Simulation Corp', 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$this->company->id]);
        $this->admin->update(['company_id' => $this->company->id]);
        $this->actingAs($this->admin);

        $this->units = Uom::where('name', 'Units')->firstOrFail();

        // Warehouse: creates Stock, Input, Output, Packing Zone + 3 operation types
        $this->warehouseSvc->create([
            'company_id' => $this->company->id,
            'name'       => 'SIM Warehouse',
            'short_name' => 'SIM',
            'active'     => true,
        ]);

        $this->stockLoc    = Location::where('company_id', $this->company->id)->where('name', 'Stock')->firstOrFail();
        $this->supplierLoc = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $this->customerLoc = Location::where('usage', 'customer')->whereNull('company_id')->firstOrFail();
        $this->scrapLoc    = Location::where('scrap_location', true)->whereNull('company_id')->firstOrFail();

        // Extra internal shelf inside the warehouse view
        $viewLoc = $this->stockLoc->parent;
        $this->shelfLoc = Location::create([
            'company_id' => $this->company->id,
            'parent_id'  => $viewLoc?->id,
            'name'       => 'Shelf B',
            'usage'      => 'internal',
            'active'     => true,
        ]);

        $this->receiptOp  = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();
        $this->deliveryOp = OperationType::where('company_id', $this->company->id)->where('code', 'outgoing')->firstOrFail();
        $this->internalOp = OperationType::where('company_id', $this->company->id)->where('code', 'internal')->firstOrFail();

        $productSvc         = app(ProductService::class);
        $this->widgetPro    = $productSvc->create(['company_id' => $this->company->id, 'uom_id' => $this->units->id, 'uom_po_id' => $this->units->id, 'name' => 'Widget Pro',  'product_type' => 'storable', 'tracking' => 'none', 'cost' => 50.00, 'sale_price' => 100.00, 'active' => true]);
        $this->widgetLite   = $productSvc->create(['company_id' => $this->company->id, 'uom_id' => $this->units->id, 'uom_po_id' => $this->units->id, 'name' => 'Widget Lite', 'product_type' => 'storable', 'tracking' => 'none', 'cost' => 20.00, 'sale_price' => 40.00,  'active' => true]);
        $this->supplyKit    = $productSvc->create(['company_id' => $this->company->id, 'uom_id' => $this->units->id, 'uom_po_id' => $this->units->id, 'name' => 'Supply Kit',  'product_type' => 'storable', 'tracking' => 'none', 'cost' => 5.00,  'sale_price' => 10.00,  'active' => true]);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIMULATION 1 — Full Procurement → Sales → Restock → Internal Move →
    //                Scrap → Physical Adjustment cycle
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Cycle overview
     * ──────────────
     * Step 1 │ PO-001  │ Receive 100 Widget Pro + 80 Widget Lite + 300 Supply Kit
     * Step 2 │ SO-001  │ Deliver 25 Widget Pro + 40 Widget Lite to customer
     * Step 3 │ PO-002  │ Receive 50 more Widget Pro
     * Step 4 │ INT-001 │ Move 30 Widget Pro: Stock → Shelf B
     * Step 5 │ SCR-001 │ Scrap 5 Widget Pro from Shelf B (damaged)
     * Step 6 │ INV-001 │ Physical count: Widget Lite = 35 (book 40), Supply Kit = 280 (book 300)
     *
     * Expected final quants
     * ──────────────────────
     * Widget Pro  @ Stock      :  95
     * Widget Pro  @ Shelf B    :  25
     * Widget Pro  @ Customers  :  25
     * Widget Pro  @ Scrap      :   5
     * Widget Pro  total on-hand (internal): 120
     *
     * Widget Lite @ Stock      :  35
     * Widget Lite @ Customers  :  40
     *
     * Supply Kit  @ Stock      : 280
     *
     * Expected move count      :  10
     * Expected done pickings   :   4
     */
    public function test_full_procurement_sale_restock_transfer_scrap_adjustment_cycle(): void
    {
        // ── Step 1: PO-001 Receipt ────────────────────────────────────────────
        $po1 = $this->receipt('PO-001', [
            [$this->widgetPro,  100],
            [$this->widgetLite, 80],
            [$this->supplyKit,  300],
        ]);

        $this->assertPickingDone($po1, 'PO-001');
        $this->assertSame(3, $po1->moves()->where('state', 'done')->count(), 'PO-001 must have 3 done moves');
        $this->assertQ($this->widgetPro,  $this->stockLoc, 100, 'after PO-001');
        $this->assertQ($this->widgetLite, $this->stockLoc,  80, 'after PO-001');
        $this->assertQ($this->supplyKit,  $this->stockLoc, 300, 'after PO-001');

        // ── Step 2: SO-001 Delivery ───────────────────────────────────────────
        $so1 = $this->delivery('SO-001', [
            [$this->widgetPro,  25],
            [$this->widgetLite, 40],
        ]);

        $this->assertPickingDone($so1, 'SO-001');
        $this->assertSame(2, $so1->moves()->where('state', 'done')->count(), 'SO-001 must have 2 done moves');
        $this->assertQ($this->widgetPro,  $this->stockLoc,    75, 'stock after SO-001');
        $this->assertQ($this->widgetLite, $this->stockLoc,    40, 'stock after SO-001');
        $this->assertQ($this->widgetPro,  $this->customerLoc, 25, 'customer after SO-001');
        $this->assertQ($this->widgetLite, $this->customerLoc, 40, 'customer after SO-001');

        // ── Step 3: PO-002 Restock ────────────────────────────────────────────
        $po2 = $this->receipt('PO-002', [
            [$this->widgetPro, 50],
        ]);

        $this->assertPickingDone($po2, 'PO-002');
        $this->assertQ($this->widgetPro, $this->stockLoc, 125, 'stock after PO-002');

        // ── Step 4: INT-001 Internal Transfer ─────────────────────────────────
        $int1 = $this->internalTransfer('INT-001', $this->stockLoc, $this->shelfLoc, [
            [$this->widgetPro, 30],
        ]);

        $this->assertPickingDone($int1, 'INT-001');
        $this->assertQ($this->widgetPro, $this->stockLoc, 95, 'stock after INT-001');
        $this->assertQ($this->widgetPro, $this->shelfLoc, 30, 'shelf after INT-001');

        // ── Step 5: SCR-001 Scrap ─────────────────────────────────────────────
        $scr1 = $this->scrap($this->widgetPro, $this->shelfLoc, 5);

        $this->assertSame('done', $scr1->state, 'SCR-001 must be done');
        $this->assertNotNull($scr1->date_done, 'SCR-001 must have date_done');
        $this->assertQ($this->widgetPro, $this->shelfLoc, 25, 'shelf after scrap');
        $this->assertQ($this->widgetPro, $this->scrapLoc,  5, 'scrap loc after scrap');

        // Scrap creates 1 traceability Move
        $this->assertDatabaseHas('inventory_moves', [
            'company_id'       => $this->company->id,
            'product_id'       => $this->widgetPro->id,
            'location_src_id'  => $this->shelfLoc->id,
            'location_dest_id' => $this->scrapLoc->id,
            'qty_done'         => 5,
            'state'            => 'done',
        ]);

        // ── Step 6: INV-001 Physical Inventory Adjustment ─────────────────────
        $adj = $this->physicalCount([
            [$this->widgetLite, $this->stockLoc, 35],   // book = 40  → diff = −5
            [$this->supplyKit,  $this->stockLoc, 280],  // book = 300 → diff = −20
        ]);

        $this->assertSame('done', $adj->state, 'INV-001 must be done');
        $this->assertQ($this->widgetLite, $this->stockLoc, 35,  'stock after INV-001');
        $this->assertQ($this->supplyKit,  $this->stockLoc, 280, 'stock after INV-001');
        // Adjustment creates 1 move per non-zero-diff line (2 lines updated)
        $adjMoves = Move::where('company_id', $this->company->id)
            ->where('name', 'like', 'Inventory Adjustment:%')
            ->count();
        $this->assertSame(2, $adjMoves, 'INV-001 must produce 2 adjustment moves');

        // ── Step 7: Final balance ─────────────────────────────────────────────
        //
        // Widget Pro budget:
        //   Received   PO-001 + PO-002 = 100 + 50 = 150
        //   Delivered  SO-001          =  25
        //   Scrapped   SCR-001         =   5
        //   On-hand internal           = 150 − 25 − 5 = 120  (Stock 95 + Shelf 25)
        $this->assertInternalOnHand($this->widgetPro,  120, 'Widget Pro final on-hand');
        $this->assertQ($this->widgetPro, $this->stockLoc,    95, 'Widget Pro final stock');
        $this->assertQ($this->widgetPro, $this->shelfLoc,    25, 'Widget Pro final shelf');
        $this->assertQ($this->widgetPro, $this->customerLoc, 25, 'Widget Pro at customer');
        $this->assertQ($this->widgetPro, $this->scrapLoc,     5, 'Widget Pro at scrap');

        // Widget Lite budget:
        //   Received   PO-001 = 80
        //   Delivered  SO-001 = 40
        //   Adjusted   INV-001 = −5
        //   On-hand internal = 80 − 40 − 5 = 35
        $this->assertInternalOnHand($this->widgetLite, 35, 'Widget Lite final on-hand');
        $this->assertQ($this->widgetLite, $this->stockLoc,    35, 'Widget Lite final stock');
        $this->assertQ($this->widgetLite, $this->customerLoc, 40, 'Widget Lite at customer');

        // Supply Kit budget:
        //   Received PO-001 = 300
        //   Adjusted INV-001 = −20
        //   On-hand = 280
        $this->assertInternalOnHand($this->supplyKit, 280, 'Supply Kit final on-hand');
        $this->assertQ($this->supplyKit, $this->stockLoc, 280, 'Supply Kit final stock');

        // ── Step 8: Move and picking counts ───────────────────────────────────
        //   PO-001 : 3 moves   (widgetPro, widgetLite, supplyKit)
        //   SO-001 : 2 moves   (widgetPro, widgetLite)
        //   PO-002 : 1 move    (widgetPro)
        //   INT-001: 1 move    (widgetPro)
        //   SCR-001: 1 move    (traceability, created by ScrapService)
        //   INV-001: 2 moves   (widgetLite −5, supplyKit −20)
        //   Total  : 10 moves
        $totalMoves = Move::where('company_id', $this->company->id)->count();
        $this->assertSame(10, $totalMoves, "Expected 10 stock moves total, got $totalMoves");

        $donePicking = Picking::where('company_id', $this->company->id)->where('state', 'done')->count();
        $this->assertSame(4, $donePicking, 'Expected 4 done pickings (PO-001, SO-001, PO-002, INT-001)');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIMULATION 2 — Return flow: receipt → delivery → return delivery
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Receipt 50 Widget Pro, deliver 20, then customer returns 10.
     *
     * Expected final quants
     * ──────────────────────
     * Widget Pro @ Stock     : 40   (50 in − 20 out + 10 back)
     * Widget Pro @ Customers : 10   (20 out − 10 returned)
     */
    public function test_return_picking_reverses_quants_correctly(): void
    {
        $po = $this->receipt('PO-RETURN', [[$this->widgetPro, 50]]);
        $this->assertQ($this->widgetPro, $this->stockLoc, 50, 'after receipt');

        $so = $this->delivery('SO-RETURN', [[$this->widgetPro, 20]]);
        $this->assertPickingDone($so, 'SO-RETURN');
        $this->assertQ($this->widgetPro, $this->stockLoc,    30, 'stock after delivery');
        $this->assertQ($this->widgetPro, $this->customerLoc, 20, 'customer after delivery');

        // Return 10 of the 20 delivered
        $return = $this->pickingSvc->createReturn($so, [$so->moves()->first()->id => 10]);
        $this->pickingSvc->confirm($return);
        $return = $this->pickingSvc->validate($return->fresh())['picking'];

        $this->assertPickingDone($return, 'Return');
        $this->assertQ($this->widgetPro, $this->stockLoc,    40, 'stock after return');
        $this->assertQ($this->widgetPro, $this->customerLoc, 10, 'customer after return');

        // 5 pickings total (PO, SO, Return) but return has no move lines in picking path —
        // validate handles quants directly via moves
        $this->assertSame(3, Picking::where('company_id', $this->company->id)->where('state', 'done')->count());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIMULATION 3 — Company isolation: another company's stock is invisible
    // ═════════════════════════════════════════════════════════════════════════

    public function test_company_isolation_prevents_cross_company_stock_visibility(): void
    {
        // Receive stock for Simulation Corp
        $this->receipt('PO-ISO', [[$this->widgetPro, 100]]);
        $this->assertQ($this->widgetPro, $this->stockLoc, 100, 'Simulation Corp stock');

        // Create a second company with its own warehouse and the same product name
        $otherCompany = Company::create(['name' => 'Other Corp', 'active' => true]);
        $this->admin->companies()->syncWithoutDetaching([$otherCompany->id]);
        $this->actingAs($this->admin);

        $productSvc = app(ProductService::class);
        $otherProduct = $productSvc->create([
            'company_id'   => $otherCompany->id,
            'uom_id'       => $this->units->id,
            'uom_po_id'    => $this->units->id,
            'name'         => 'Widget Pro',   // same name, different company
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
        ]);

        $otherWarehouse = $this->warehouseSvc->create([
            'company_id' => $otherCompany->id,
            'name'       => 'Other Warehouse',
            'short_name' => 'OTH',
            'active'     => true,
        ]);

        $otherStock      = Location::where('company_id', $otherCompany->id)->where('name', 'Stock')->firstOrFail();
        $otherReceiptOp  = OperationType::where('company_id', $otherCompany->id)->where('code', 'incoming')->firstOrFail();

        $picking = $this->pickingSvc->create([
            'company_id'        => $otherCompany->id,
            'operation_type_id' => $otherReceiptOp->id,
            'location_src_id'   => $this->supplierLoc->id,
            'location_dest_id'  => $otherStock->id,
            'origin'            => 'PO-OTHER',
            'scheduled_date'    => now(),
            'active'            => true,
        ], [['product_id' => $otherProduct->id, 'uom_id' => $this->units->id, 'product_qty' => 200, 'name' => $otherProduct->name]]);
        $this->pickingSvc->confirm($picking);
        $this->pickingSvc->validate($picking->fresh());

        // Other Corp stock is 200; Simulation Corp stock is still 100
        $simQuant = Quant::where('company_id', $this->company->id)
            ->where('location_id', $this->stockLoc->id)
            ->sum('quantity');
        $otherQuant = Quant::where('company_id', $otherCompany->id)
            ->where('location_id', $otherStock->id)
            ->sum('quantity');

        $this->assertSame(100.0, (float) $simQuant,   'Simulation Corp must see only its own 100 units');
        $this->assertSame(200.0, (float) $otherQuant, 'Other Corp must see only its own 200 units');

        // Move counts are also isolated per company
        $simMoves   = Move::where('company_id', $this->company->id)->count();
        $otherMoves = Move::where('company_id', $otherCompany->id)->count();
        $this->assertSame(1, $simMoves,   'Simulation Corp should have 1 move');
        $this->assertSame(1, $otherMoves, 'Other Corp should have 1 move');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIMULATION 4 — Over-delivery guard: partial stock + adjust and redeliver
    // ═════════════════════════════════════════════════════════════════════════

    /**
     * Receive 30, attempt to deliver 50 (only 30 available),
     * cancel the pending delivery, restock 25 more, then redeliver 50.
     *
     * Final: Stock = 5, Customer = 50
     */
    public function test_cancel_and_redeliver_after_restock(): void
    {
        // Receive 30
        $this->receipt('PO-PARTIAL', [[$this->widgetPro, 30]]);
        $this->assertQ($this->widgetPro, $this->stockLoc, 30, 'after first receipt');

        // Try to deliver 50 — checkAvailability should mark it partially/confirmed, not assigned
        $so = $this->pickingSvc->create([
            'company_id'        => $this->company->id,
            'operation_type_id' => $this->deliveryOp->id,
            'location_src_id'   => $this->stockLoc->id,
            'location_dest_id'  => $this->customerLoc->id,
            'origin'            => 'SO-PARTIAL',
            'scheduled_date'    => now(),
            'active'            => true,
        ], [['product_id' => $this->widgetPro->id, 'uom_id' => $this->units->id, 'product_qty' => 50, 'name' => $this->widgetPro->name]]);

        $this->pickingSvc->confirm($so);
        $so = $this->pickingSvc->checkAvailability($so->fresh());

        // Only 30 available for a 50-unit demand → not fully assigned
        $this->assertFalse($so->isAssigned(), 'Should NOT be fully assigned when stock is short');
        $move = $so->moves()->first();
        $this->assertSame(30.0, (float) $move->reserved_qty, 'Only 30 should be reserved');

        // Cancel the under-stocked delivery
        $so = $this->pickingSvc->cancel($so->fresh());
        $this->assertTrue($so->isCancelled(), 'Delivery must be cancelled');

        // Restock 25 more → now 30 in stock (cancel does not touch quants for unvalidated pickings)
        $this->receipt('PO-RESTOCK', [[$this->widgetPro, 25]]);
        $this->assertQ($this->widgetPro, $this->stockLoc, 55, 'after restock (30 original + 25 new)');

        // Redeliver 50
        $so2 = $this->delivery('SO-REDELIVER', [[$this->widgetPro, 50]]);
        $this->assertPickingDone($so2, 'SO-REDELIVER');
        $this->assertQ($this->widgetPro, $this->stockLoc,     5, 'stock after redeliver');
        $this->assertQ($this->widgetPro, $this->customerLoc, 50, 'customer after redeliver');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIMULATION 5 — Scrap before any receipt produces expected negative quant
    // (edge-case: adjustments on empty stock)
    // ═════════════════════════════════════════════════════════════════════════

    public function test_physical_adjustment_on_zero_stock_creates_positive_quant(): void
    {
        // No receipts — start with zero stock
        $adj = $this->adjustmentSvc->create([
            'company_id' => $this->company->id,
            'date'       => now()->toDateString(),
        ]);
        $adj = $this->adjustmentSvc->startCount($adj);

        // No quants exist yet → no adjustment lines
        $this->assertSame(0, $adj->lines()->count(), 'No lines expected with zero stock');

        // Add a line manually (counted 20, theoretical 0)
        \App\Models\Inventory\InventoryAdjustmentLine::create([
            'adjustment_id'  => $adj->id,
            'company_id'     => $this->company->id,
            'product_id'     => $this->widgetPro->id,
            'location_id'    => $this->stockLoc->id,
            'theoretical_qty' => 0,
            'inventory_qty'  => 20,
            'difference_qty' => 20,
        ]);

        $adj = $this->adjustmentSvc->validate($adj->fresh());

        $this->assertSame('done', $adj->state);
        $this->assertQ($this->widgetPro, $this->stockLoc, 20.0, 'after positive adjustment from zero');

        // One traceability move must exist (inv-loc → stock, qty=20)
        $this->assertSame(1, Move::where('company_id', $this->company->id)->count());
    }

    // ═════════════════════════════════════════════════════════════════════════
    // SIMULATION 6 — Sequential auto-naming across multiple companies
    // ═════════════════════════════════════════════════════════════════════════

    public function test_sequence_numbers_are_per_company_and_increment_correctly(): void
    {
        // Three receipts for Simulation Corp
        $r1 = $this->receipt('PO-SEQ-A', [[$this->widgetPro, 1]]);
        $r2 = $this->receipt('PO-SEQ-B', [[$this->widgetLite, 1]]);
        $r3 = $this->receipt('PO-SEQ-C', [[$this->supplyKit, 1]]);

        // Names should follow SIM/IN/00001, 00002, 00003
        $this->assertSame('SIM/IN/00001', $r1->name);
        $this->assertSame('SIM/IN/00002', $r2->name);
        $this->assertSame('SIM/IN/00003', $r3->name);

        // A second company's receipts start from 00001 independently
        $otherCo = Company::create(['name' => 'Seq Corp', 'active' => true]);
        $this->admin->companies()->syncWithoutDetaching([$otherCo->id]);
        $this->warehouseSvc->create(['company_id' => $otherCo->id, 'name' => 'Seq WH', 'short_name' => 'SEQ', 'active' => true]);

        $otherReceiptOp = OperationType::where('company_id', $otherCo->id)->where('code', 'incoming')->firstOrFail();
        $otherStockLoc  = Location::where('company_id', $otherCo->id)->where('name', 'Stock')->firstOrFail();
        $otherProduct   = app(ProductService::class)->create([
            'company_id' => $otherCo->id, 'uom_id' => $this->units->id, 'uom_po_id' => $this->units->id,
            'name' => 'Other Widget', 'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
        ]);

        $r4 = $this->pickingSvc->create([
            'company_id'        => $otherCo->id,
            'operation_type_id' => $otherReceiptOp->id,
            'location_src_id'   => $this->supplierLoc->id,
            'location_dest_id'  => $otherStockLoc->id,
            'origin'            => 'PO-OTHER-1',
            'scheduled_date'    => now(),
            'active'            => true,
        ], [['product_id' => $otherProduct->id, 'uom_id' => $this->units->id, 'product_qty' => 10, 'name' => $otherProduct->name]]);

        $this->assertSame('SEQ/IN/00001', $r4->name, 'Other company receipt must start from 00001');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers — simulation actions
    // ═════════════════════════════════════════════════════════════════════════

    /** Create + confirm + validate a receipt (Suppliers → Stock). */
    private function receipt(string $origin, array $lines): Picking
    {
        $picking = $this->pickingSvc->create([
            'company_id'        => $this->company->id,
            'operation_type_id' => $this->receiptOp->id,
            'location_src_id'   => $this->supplierLoc->id,
            'location_dest_id'  => $this->stockLoc->id,
            'origin'            => $origin,
            'scheduled_date'    => now(),
            'active'            => true,
        ], $this->movesData($lines));

        $this->pickingSvc->confirm($picking);
        return $this->pickingSvc->validate($picking->fresh())['picking'];
    }

    /** Create + confirm + check availability + validate a delivery (Stock → Customers). */
    private function delivery(string $origin, array $lines): Picking
    {
        $picking = $this->pickingSvc->create([
            'company_id'        => $this->company->id,
            'operation_type_id' => $this->deliveryOp->id,
            'location_src_id'   => $this->stockLoc->id,
            'location_dest_id'  => $this->customerLoc->id,
            'origin'            => $origin,
            'scheduled_date'    => now(),
            'active'            => true,
        ], $this->movesData($lines));

        $this->pickingSvc->confirm($picking);
        $this->pickingSvc->checkAvailability($picking->fresh());
        return $this->pickingSvc->validate($picking->fresh())['picking'];
    }

    /** Create + confirm + check availability + validate an internal transfer. */
    private function internalTransfer(string $origin, Location $from, Location $to, array $lines): Picking
    {
        $picking = $this->pickingSvc->create([
            'company_id'        => $this->company->id,
            'operation_type_id' => $this->internalOp->id,
            'location_src_id'   => $from->id,
            'location_dest_id'  => $to->id,
            'origin'            => $origin,
            'scheduled_date'    => now(),
            'active'            => true,
        ], $this->movesData($lines));

        $this->pickingSvc->confirm($picking);
        $this->pickingSvc->checkAvailability($picking->fresh());
        return $this->pickingSvc->validate($picking->fresh())['picking'];
    }

    /** Create + validate a scrap order. */
    private function scrap(Product $product, Location $from, float $qty): ScrapOrder
    {
        $order = $this->scrapSvc->create([
            'company_id'        => $this->company->id,
            'product_id'        => $product->id,
            'uom_id'            => $this->units->id,
            'location_id'       => $from->id,
            'scrap_location_id' => $this->scrapLoc->id,
            'scrap_qty'         => $qty,
        ]);
        return $this->scrapSvc->validate($order);
    }

    /**
     * Create a physical inventory adjustment, run startCount, update the listed lines,
     * then validate.  Lines: [Product, Location, counted_qty]
     */
    private function physicalCount(array $lines): InventoryAdjustment
    {
        $adj = $this->adjustmentSvc->create([
            'company_id' => $this->company->id,
            'date'       => now()->toDateString(),
        ]);
        $adj = $this->adjustmentSvc->startCount($adj);

        foreach ($lines as [$product, $location, $counted]) {
            $line = $adj->lines()
                ->where('product_id', $product->id)
                ->where('location_id', $location->id)
                ->first();

            if ($line) {
                $this->adjustmentSvc->updateLine($line, (float) $counted);
            }
        }

        return $this->adjustmentSvc->validate($adj->fresh());
    }

    /** Convert [[Product, qty], ...] to the array format PickingService::create expects. */
    private function movesData(array $lines): array
    {
        return array_map(fn(array $row) => [
            'product_id'  => $row[0]->id,
            'uom_id'      => $this->units->id,
            'product_qty' => $row[1],
            'name'        => $row[0]->name,
        ], $lines);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // Helpers — assertions
    // ═════════════════════════════════════════════════════════════════════════

    private function assertPickingDone(Picking $picking, string $label): void
    {
        $this->assertTrue($picking->isDone(),        "$label: state must be 'done'");
        $this->assertNotNull($picking->date_done,    "$label: date_done must be set");
    }

    /** Assert the quant for a product at a specific location equals $expected. */
    private function assertQ(Product $product, Location $location, float $expected, string $label): void
    {
        $quant  = Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->where('location_id', $location->id)
            ->first();
        $actual = $quant ? (float) $quant->quantity : 0.0;

        $this->assertSame(
            $expected,
            $actual,
            sprintf('%s: %s @ %s — expected %.4f, got %.4f', $label, $product->name, $location->name, $expected, $actual)
        );
    }

    /** Assert the total on-hand quantity across all INTERNAL locations for a product. */
    private function assertInternalOnHand(Product $product, float $expected, string $label): void
    {
        $internalIds = Location::where('company_id', $this->company->id)
            ->where('usage', 'internal')
            ->pluck('id');

        $actual = (float) Quant::where('company_id', $this->company->id)
            ->where('product_id', $product->id)
            ->whereIn('location_id', $internalIds)
            ->sum('quantity');

        $this->assertSame(
            $expected,
            $actual,
            sprintf('%s: %s total on-hand — expected %.4f, got %.4f', $label, $product->name, $expected, $actual)
        );
    }
}
