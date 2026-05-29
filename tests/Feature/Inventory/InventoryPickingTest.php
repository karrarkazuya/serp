<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductCategory;
use App\Models\Inventory\PutawayRule;
use App\Models\Inventory\Quant;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\PickingService;
use App\Services\Inventory\WarehouseService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryPickingTest extends TestCase
{
    use RefreshDatabase;

    private PickingService $pickingService;
    private WarehouseService $warehouseService;
    private User $admin;
    private Company $company;
    private Product $product;
    private Uom $uom;
    private Location $srcLocation;
    private Location $destLocation;
    private OperationType $operationType;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->pickingService   = app(PickingService::class);
        $this->warehouseService = app(WarehouseService::class);
        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('Warehouse Co');
        $this->uom     = Uom::where('name', 'Units')->firstOrFail();

        $this->actingAs($this->admin);

        $this->warehouseService->create([
            'company_id' => $this->company->id,
            'name'       => 'Main Warehouse',
            'short_name' => 'MW',
            'active'     => true,
        ]);

        $this->srcLocation  = Location::where('company_id', $this->company->id)->where('name', 'Stock')->firstOrFail();
        $this->destLocation = Location::where('usage', 'customer')->whereNull('company_id')->firstOrFail();
        $this->operationType = OperationType::where('company_id', $this->company->id)->where('code', 'outgoing')->firstOrFail();

        $this->product = Product::create([
            'name'         => 'Transfer Product',
            'company_id'   => $this->company->id,
            'uom_id'       => $this->uom->id,
            'uom_po_id'    => $this->uom->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);
    }

    // ── State machine ─────────────────────────────────────────────────────────

    public function test_picking_is_created_in_draft_state(): void
    {
        $picking = $this->createPicking();

        $this->assertTrue($picking->isDraft());
        $this->assertStringStartsWith('MW/OUT/', $picking->name);
    }

    public function test_picking_can_be_confirmed(): void
    {
        $picking = $this->createPicking();
        $picking = $this->pickingService->confirm($picking);

        $this->assertTrue($picking->isConfirmed());
        $this->assertSame('confirmed', $picking->moves()->first()->state);
    }

    public function test_confirming_already_confirmed_picking_is_idempotent(): void
    {
        $picking = $this->createPicking();
        $picking = $this->pickingService->confirm($picking);
        $picking = $this->pickingService->confirm($picking);

        $this->assertTrue($picking->isConfirmed());
    }

    public function test_validate_done_picking_throws_exception(): void
    {
        $this->seedStock(50);
        $picking = $this->createPicking(qty: 10);
        $this->pickingService->confirm($picking);
        $result  = $this->pickingService->validate($picking->fresh());
        $done    = $result['picking'];

        $this->expectException(\RuntimeException::class);
        $this->pickingService->validate($done);
    }

    public function test_nothing_to_validate_throws_exception(): void
    {
        $picking = $this->createPicking(qty: 5);
        $this->pickingService->confirm($picking);

        $move = $picking->moves()->first();
        $this->expectException(\RuntimeException::class);
        $this->pickingService->validate($picking->fresh(), [$move->id => 0]);
    }

    // ── Availability reservation ──────────────────────────────────────────────

    public function test_check_availability_marks_assigned_when_stock_available(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 10);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->checkAvailability($picking);

        $this->assertTrue($picking->isAssigned());
        $this->assertSame(10.0, (float) $picking->moves()->first()->reserved_qty);
    }

    public function test_check_availability_stays_confirmed_when_no_stock(): void
    {
        $picking = $this->createPicking(qty: 5);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->checkAvailability($picking);

        $this->assertFalse($picking->isAssigned());
    }

    public function test_check_availability_partial_stock_sets_partially_available_state(): void
    {
        $this->seedStock(3); // only 3 available, need 10

        $picking = $this->createPicking(qty: 10);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->checkAvailability($picking);

        // Only 3 reserved out of 10 → partially_available move
        $move = $picking->moves()->first();
        $this->assertSame('partially_available', $move->state);
        $this->assertSame(3.0, (float) $move->reserved_qty);
    }

    public function test_cancel_releases_quant_reservation(): void
    {
        $this->seedStock(100);

        $picking = $this->createPicking(qty: 30);
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);

        // Verify 30 are reserved
        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->srcLocation->id)
            ->first();
        $this->assertSame(30.0, (float) $quant->reserved_quantity);

        $this->pickingService->cancel($picking->fresh());

        $quant->refresh();
        $this->assertSame(0.0, (float) $quant->reserved_quantity);
    }

    // ── Validation + quant moves ──────────────────────────────────────────────

    public function test_validate_deducts_stock_and_marks_done(): void
    {
        $this->seedStock(100);

        $picking = $this->createPicking(qty: 25);
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $this->assertTrue($picking->isDone());
        $this->assertNotNull($picking->date_done);

        $srcQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->srcLocation->id)
            ->first();
        $this->assertSame(75.0, (float) $srcQuant->quantity);
    }

    public function test_validate_increases_quant_at_destination(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 20);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $this->assertTrue($picking->isDone());
        $destQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->destLocation->id)
            ->first();
        $this->assertSame(20.0, (float) $destQuant->quantity);
    }

    public function test_validate_without_prior_check_availability_still_processes(): void
    {
        $this->seedStock(40);

        $picking = $this->createPicking(qty: 15);
        $this->pickingService->confirm($picking);
        // Skip checkAvailability — validate directly
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $this->assertTrue($picking->isDone());
        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->srcLocation->id)
            ->first();
        $this->assertSame(25.0, (float) $quant->quantity);
    }

    // ── Backorder ─────────────────────────────────────────────────────────────

    public function test_validate_with_partial_qty_creates_backorder(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 30);
        $this->pickingService->confirm($picking);

        $move = $picking->moves()->first();
        $result = $this->pickingService->validate($picking->fresh(), [$move->id => 20]);

        $this->assertNotNull($result['backorder'], 'A backorder should be created for the remaining 10 units');
        $this->assertTrue($result['picking']->isDone());

        $backorder = $result['backorder'];
        $this->assertTrue($backorder->isConfirmed());
        $this->assertSame(10.0, (float) $backorder->moves()->first()->product_qty);
    }

    public function test_validate_with_no_backorder_flag_skips_backorder(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 30);
        $this->pickingService->confirm($picking);

        $move   = $picking->moves()->first();
        $result = $this->pickingService->validate($picking->fresh(), [$move->id => 20], false);

        $this->assertNull($result['backorder']);
        $this->assertTrue($result['picking']->isDone());
    }

    public function test_backorder_inherits_operation_type_and_locations(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 30);
        $this->pickingService->confirm($picking);
        $move    = $picking->moves()->first();
        $result  = $this->pickingService->validate($picking->fresh(), [$move->id => 10]);

        $backorder = $result['backorder'];
        $this->assertSame($picking->operation_type_id, $backorder->operation_type_id);
        $this->assertSame($picking->location_src_id,   $backorder->location_src_id);
        $this->assertSame($picking->location_dest_id,  $backorder->location_dest_id);
        $this->assertSame($picking->company_id,        $backorder->company_id);
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function test_picking_can_be_cancelled(): void
    {
        $picking = $this->createPicking();
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->cancel($picking->fresh());

        $this->assertTrue($picking->isCancelled());
        $this->assertSame('cancelled', $picking->moves()->first()->state);
    }

    public function test_done_picking_cannot_be_cancelled(): void
    {
        $this->seedStock(10);

        $picking = $this->createPicking(qty: 5);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $this->expectException(\RuntimeException::class);
        $this->pickingService->cancel($picking);
    }

    // ── Return picking ────────────────────────────────────────────────────────

    public function test_return_picking_has_swapped_src_and_dest(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 20);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $return = $this->pickingService->createReturn($picking);

        $this->assertSame($picking->location_dest_id, $return->location_src_id);
        $this->assertSame($picking->location_src_id,  $return->location_dest_id);
    }

    public function test_partial_return_creates_picking_with_specified_qty(): void
    {
        $this->seedStock(50);

        $picking = $this->createPicking(qty: 20);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $move   = $picking->moves()->first();
        $return = $this->pickingService->createReturn($picking, [$move->id => 8]);

        $this->assertSame(8.0, (float) $return->moves()->first()->product_qty);
    }

    // ── HTTP layer ────────────────────────────────────────────────────────────

    public function test_admin_can_view_transfer_index(): void
    {
        $picking = $this->createPicking();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.transfers.index'))
            ->assertOk()
            ->assertSee($picking->name);
    }

    public function test_admin_can_view_transfer_show_page(): void
    {
        $picking = $this->createPicking();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.transfers.show', $picking))
            ->assertOk()
            ->assertSee($picking->name);
    }

    public function test_confirm_via_http_changes_state(): void
    {
        $picking = $this->createPicking();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.transfers.confirm', $picking))
            ->assertRedirect();

        $this->assertTrue($picking->fresh()->isConfirmed());
    }

    public function test_check_availability_via_http(): void
    {
        $this->seedStock(50);
        $picking = $this->createPicking(qty: 10);
        $this->pickingService->confirm($picking);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.transfers.check-availability', $picking))
            ->assertRedirect();

        $this->assertTrue($picking->fresh()->isAssigned());
    }

    public function test_validate_via_http_marks_done(): void
    {
        $this->seedStock(50);
        $picking = $this->createPicking(qty: 15);
        $this->pickingService->confirm($picking);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.transfers.validate', $picking))
            ->assertRedirect();

        $this->assertTrue($picking->fresh()->isDone());
    }

    public function test_cancel_via_http_cancels_picking(): void
    {
        $picking = $this->createPicking();
        $this->pickingService->confirm($picking);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.transfers.cancel', $picking))
            ->assertRedirect();

        $this->assertTrue($picking->fresh()->isCancelled());
    }

    public function test_delete_draft_picking_via_http(): void
    {
        $picking = $this->createPicking();
        $id = $picking->id;

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->delete(route('inventory.transfers.delete', $picking))
            ->assertRedirect(route('inventory.transfers.index'));

        $this->assertSoftDeleted('inventory_pickings', ['id' => $id]);
    }

    public function test_cannot_delete_done_picking_via_http(): void
    {
        $this->seedStock(20);
        $picking = $this->createPicking(qty: 5);
        $this->pickingService->confirm($picking);
        $this->pickingService->validate($picking->fresh());

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->delete(route('inventory.transfers.delete', $picking->fresh()))
            ->assertForbidden();
    }

    // ── Company isolation ─────────────────────────────────────────────────────

    public function test_company_isolation_prevents_cross_company_transfer_access(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'active' => true]);
        $otherPickingId = Picking::create([
            'company_id'        => $otherCompany->id,
            'operation_type_id' => $this->operationType->id,
            'name'              => 'OTHER/001',
            'state'             => 'draft',
            'location_src_id'   => $this->srcLocation->id,
            'location_dest_id'  => $this->destLocation->id,
            'scheduled_date'    => now(),
            'active'            => true,
            'created_by'        => $this->admin->id,
            'updated_by'        => $this->admin->id,
        ])->id;

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.transfers.show', $otherPickingId))
            ->assertForbidden();
    }

    // ── UoM conversion (Odoo parity Phase 1) ──────────────────────────────────

    /**
     * Quants are stored in product reference UoM (`kg` here). When a transfer
     * is entered in a smaller UoM (`g`, ratio 0.001), the service must convert
     * before touching the quant — otherwise transferring 500 g of a kg-tracked
     * product would only remove 500 kg from the source (1000× over-subtract,
     * driving stock negative and silently triggering the insufficient-on-hand
     * guard for legitimate movements).
     */
    public function test_validate_converts_smaller_uom_qty_into_product_reference_uom(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = Product::create([
            'name'         => 'Bulk Powder',
            'company_id'   => $this->company->id,
            'uom_id'       => $kg->id,
            'uom_po_id'    => $kg->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->srcLocation->id,
            'quantity'    => 10.0, // 10 kg on hand
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        // Transfer 500 g (= 0.5 kg)
        $picking = $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->operationType->id,
                'location_src_id'   => $this->srcLocation->id,
                'location_dest_id'  => $this->destLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 500.0, 'name' => $product->name]]
        );
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->validate($picking->fresh())['picking'];

        $this->assertTrue($picking->isDone());
        $srcQuant = Quant::where('product_id', $product->id)->where('location_id', $this->srcLocation->id)->first();
        // 10 kg − 0.5 kg = 9.5 kg (NOT 10 − 500 = −490)
        $this->assertEqualsWithDelta(9.5, (float) $srcQuant->quantity, 0.0001);
    }

    public function test_check_availability_converts_smaller_uom_for_reservation(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = Product::create([
            'name' => 'Bulk Powder', 'company_id' => $this->company->id,
            'uom_id' => $kg->id, 'uom_po_id' => $kg->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->srcLocation->id,
            'quantity'    => 2.0, // 2 kg on hand
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        // Reserve 1500 g (= 1.5 kg)
        $picking = $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->operationType->id,
                'location_src_id'   => $this->srcLocation->id,
                'location_dest_id'  => $this->destLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 1500.0, 'name' => $product->name]]
        );
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->checkAvailability($picking);

        $this->assertTrue($picking->isAssigned());
        // Quant reserved_quantity is in product UoM (kg): 1500 g → 1.5 kg
        $quant = Quant::where('product_id', $product->id)->where('location_id', $this->srcLocation->id)->first();
        $this->assertEqualsWithDelta(1.5, (float) $quant->reserved_quantity, 0.0001);
        // move.reserved_qty is in move UoM (g) — should display the original 1500 g
        $this->assertEqualsWithDelta(1500.0, (float) $picking->moves()->first()->reserved_qty, 0.0001);
    }

    public function test_cancel_releases_full_quant_reservation_after_uom_conversion(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $g  = Uom::where('name', 'g')->firstOrFail();
        $product = Product::create([
            'name' => 'Bulk Powder', 'company_id' => $this->company->id,
            'uom_id' => $kg->id, 'uom_po_id' => $kg->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        Quant::create([
            'company_id' => $this->company->id, 'product_id' => $product->id,
            'location_id' => $this->srcLocation->id, 'quantity' => 5.0, 'in_date' => now(),
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $picking = $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->operationType->id,
                'location_src_id'   => $this->srcLocation->id,
                'location_dest_id'  => $this->destLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $product->id, 'uom_id' => $g->id, 'product_qty' => 2500.0, 'name' => $product->name]]
        );
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);
        $this->pickingService->cancel($picking->fresh());

        $quant = Quant::where('product_id', $product->id)->where('location_id', $this->srcLocation->id)->first();
        // Without UoM conversion in releaseMoveReservation, this would still
        // show 2.5 − 2500 = −2497.5 kg "reserved" (or 0 floored, but with
        // 2497.5 kg of phantom reservation leaked across the system).
        $this->assertEqualsWithDelta(0.0, (float) $quant->reserved_quantity, 0.0001);
    }

    public function test_cross_category_uom_rejected_by_form_validation(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $cm = Uom::where('name', 'cm')->firstOrFail(); // length category, not weight
        $product = Product::create([
            'name' => 'Bulk Powder', 'company_id' => $this->company->id,
            'uom_id' => $kg->id, 'uom_po_id' => $kg->id,
            'product_type' => 'storable', 'tracking' => 'none', 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.transfers.store'), [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->operationType->id,
                'location_src_id'   => $this->srcLocation->id,
                'location_dest_id'  => $this->destLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'moves'             => [[
                    'product_id'  => $product->id,
                    'uom_id'      => $cm->id, // mismatched category
                    'product_qty' => 1.0,
                ]],
            ])
            ->assertSessionHasErrors(['moves.0.uom_id']);
    }

    public function test_uom_model_convertqty_throws_on_cross_category(): void
    {
        $kg = Uom::where('name', 'kg')->firstOrFail();
        $cm = Uom::where('name', 'cm')->firstOrFail();
        $this->expectException(\RuntimeException::class);
        $kg->convertQty(1.0, $cm);
    }

    // ── Putaway rules (Odoo parity Phase 2) ───────────────────────────────────

    /**
     * Receiving "Bulk Powder" at the warehouse Stock should automatically
     * redirect to its designated bin when a product-specific putaway rule
     * matches. The picking-level dest stays at Stock so the receipt header
     * still reads as a warehouse-level transfer; only the move row carries
     * the refined location.
     */
    public function test_addmove_redirects_dest_via_product_specific_putaway_rule(): void
    {
        $bin = Location::create([
            'company_id' => $this->company->id,
            'name'       => 'Aisle A / Shelf 3',
            'parent_id'  => $this->srcLocation->id,
            'usage'      => 'internal',
            'active'     => true,
        ]);

        PutawayRule::create([
            'company_id'        => $this->company->id,
            'location_id'       => $this->srcLocation->id,
            'fixed_location_id' => $bin->id,
            'product_id'        => $this->product->id,
            'sequence'          => 10,
            'active'            => true,
        ]);

        // Receipt: supplier → Stock (should be redirected to the bin)
        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $incoming = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();

        $picking = $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $incoming->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->srcLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $this->product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $this->product->name]]
        );

        $move = $picking->fresh()->moves()->first();
        $this->assertSame($bin->id, $move->location_dest_id, 'Putaway rule should have redirected the move dest to the bin');
        $this->assertSame($this->srcLocation->id, $picking->fresh()->location_dest_id, 'Picking-level dest must stay at the warehouse-level location');
    }

    public function test_putaway_rule_prefers_product_match_over_category_match(): void
    {
        $cat = ProductCategory::create([
            'company_id' => $this->company->id,
            'name'       => 'Bulk Powders',
            'active'     => true,
        ]);
        $this->product->update(['category_id' => $cat->id]);

        $categoryBin = Location::create([
            'company_id' => $this->company->id, 'name' => 'Aisle A (category bin)',
            'parent_id' => $this->srcLocation->id, 'usage' => 'internal', 'active' => true,
        ]);
        $productBin = Location::create([
            'company_id' => $this->company->id, 'name' => 'Aisle B (product bin)',
            'parent_id' => $this->srcLocation->id, 'usage' => 'internal', 'active' => true,
        ]);

        // Category rule (less specific) — would match if no product rule
        PutawayRule::create([
            'company_id'          => $this->company->id,
            'location_id'         => $this->srcLocation->id,
            'fixed_location_id'   => $categoryBin->id,
            'product_category_id' => $cat->id,
            'sequence'            => 5, // even with lower sequence, product rule wins
            'active'              => true,
        ]);
        // Product rule (more specific) — should win
        PutawayRule::create([
            'company_id'        => $this->company->id,
            'location_id'       => $this->srcLocation->id,
            'fixed_location_id' => $productBin->id,
            'product_id'        => $this->product->id,
            'sequence'          => 100,
            'active'            => true,
        ]);

        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $incoming = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();
        $picking = $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $incoming->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->srcLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $this->product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $this->product->name]]
        );

        $move = $picking->fresh()->moves()->first();
        $this->assertSame($productBin->id, $move->location_dest_id);
    }

    public function test_putaway_rule_does_not_apply_to_non_internal_destinations(): void
    {
        $bin = Location::create([
            'company_id' => $this->company->id, 'name' => 'Bin A',
            'parent_id' => $this->srcLocation->id, 'usage' => 'internal', 'active' => true,
        ]);
        // Rule against the customer location — should never fire because
        // resolveFor() refuses to redirect non-internal destinations.
        PutawayRule::create([
            'company_id'        => $this->company->id,
            'location_id'       => $this->destLocation->id, // customer
            'fixed_location_id' => $bin->id,
            'product_id'        => $this->product->id,
            'sequence'          => 10,
            'active'            => true,
        ]);

        $picking = $this->createPicking(qty: 5); // outgoing → customer
        $move    = $picking->fresh()->moves()->first();
        $this->assertSame($this->destLocation->id, $move->location_dest_id);
    }

    public function test_inactive_putaway_rule_is_ignored(): void
    {
        $bin = Location::create([
            'company_id' => $this->company->id, 'name' => 'Bin A',
            'parent_id' => $this->srcLocation->id, 'usage' => 'internal', 'active' => true,
        ]);
        PutawayRule::create([
            'company_id'        => $this->company->id,
            'location_id'       => $this->srcLocation->id,
            'fixed_location_id' => $bin->id,
            'product_id'        => $this->product->id,
            'sequence'          => 10,
            'active'            => false, // archived
        ]);

        $supplier = Location::where('usage', 'supplier')->whereNull('company_id')->firstOrFail();
        $incoming = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();
        $picking = $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $incoming->id,
                'location_src_id'   => $supplier->id,
                'location_dest_id'  => $this->srcLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [['product_id' => $this->product->id, 'uom_id' => $this->uom->id, 'product_qty' => 5.0, 'name' => $this->product->name]]
        );

        $move = $picking->fresh()->moves()->first();
        $this->assertSame($this->srcLocation->id, $move->location_dest_id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createPicking(float $qty = 10): Picking
    {
        return $this->pickingService->create(
            [
                'company_id'        => $this->company->id,
                'operation_type_id' => $this->operationType->id,
                'location_src_id'   => $this->srcLocation->id,
                'location_dest_id'  => $this->destLocation->id,
                'scheduled_date'    => now()->toDateString(),
                'active'            => true,
            ],
            [
                [
                    'product_id'  => $this->product->id,
                    'uom_id'      => $this->uom->id,
                    'product_qty' => $qty,
                    'name'        => $this->product->name,
                ],
            ]
        );
    }

    private function seedStock(float $qty): void
    {
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $this->product->id,
            'location_id' => $this->srcLocation->id,
            'quantity'    => $qty,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);
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
