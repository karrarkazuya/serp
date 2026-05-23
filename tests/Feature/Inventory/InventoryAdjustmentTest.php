<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Move;
use App\Models\Inventory\Product;
use App\Models\Inventory\Quant;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\AdjustmentService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    private AdjustmentService $adjustmentService;
    private User $admin;
    private Company $company;
    private Product $product;
    private Uom $uom;
    private Location $stockLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->adjustmentService = app(AdjustmentService::class);
        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('Adjustment Co');
        $this->uom     = Uom::where('name', 'Units')->firstOrFail();

        $this->actingAs($this->admin);

        $this->stockLocation = Location::create([
            'company_id'  => $this->company->id,
            'name'        => 'Adjustment Stock',
            'usage'       => 'internal',
            'active'      => true,
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $this->product = Product::create([
            'name'         => 'Adjustment Product',
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

    // ── Creation ──────────────────────────────────────────────────────────────

    public function test_adjustment_is_created_in_draft_state(): void
    {
        $adj = $this->createAdjustment();

        $this->assertStringStartsWith('INV/', $adj->name);
        $this->assertSame('draft', $adj->state);
    }

    public function test_adjustment_names_are_sequential(): void
    {
        $first  = $this->createAdjustment();
        $second = $this->createAdjustment();

        $this->assertNotSame($first->name, $second->name);
    }

    // ── Start count ───────────────────────────────────────────────────────────

    public function test_start_count_creates_lines_from_quants(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();

        $adj = $this->adjustmentService->startCount($adj);

        $this->assertSame('in_progress', $adj->state);
        $this->assertSame(1, $adj->lines()->count());

        $line = $adj->lines()->first();
        $this->assertSame(40.0, (float) $line->theoretical_qty);
        $this->assertSame(40.0, (float) $line->inventory_qty);
        $this->assertSame(0.0, (float) $line->difference_qty);
    }

    public function test_start_count_with_no_existing_quants_creates_no_lines(): void
    {
        $adj = $this->createAdjustment(); // no quants exist

        $adj = $this->adjustmentService->startCount($adj);

        $this->assertSame('in_progress', $adj->state);
        $this->assertSame(0, $adj->lines()->count());
    }

    public function test_start_count_is_idempotent_when_already_in_progress(): void
    {
        $this->seedStock(10);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        // Calling startCount again must not duplicate lines
        $adj = $this->adjustmentService->startCount($adj);

        $this->assertSame(1, $adj->lines()->count());
    }

    // ── Line updates ──────────────────────────────────────────────────────────

    public function test_update_line_changes_inventory_qty_and_difference(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $line = $adj->lines()->first();
        $this->adjustmentService->updateLine($line, 55);

        $line->refresh();
        $this->assertSame(55.0, (float) $line->inventory_qty);
        $this->assertSame(15.0, (float) $line->difference_qty);
    }

    public function test_update_line_records_negative_difference(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $line = $adj->lines()->first();
        $this->adjustmentService->updateLine($line, 30);

        $line->refresh();
        $this->assertSame(-10.0, (float) $line->difference_qty);
    }

    // ── Validate: positive adjustment ─────────────────────────────────────────

    public function test_validate_positive_adjustment_increases_quant(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $this->adjustmentService->updateLine($adj->lines()->first(), 50);
        $adj = $this->adjustmentService->validate($adj->fresh());

        $this->assertSame('done', $adj->state);
        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->stockLocation->id)
            ->first();
        $this->assertSame(50.0, (float) $quant->quantity);
    }

    public function test_validate_negative_adjustment_decreases_quant(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $this->adjustmentService->updateLine($adj->lines()->first(), 25);
        $adj = $this->adjustmentService->validate($adj->fresh());

        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->stockLocation->id)
            ->first();
        $this->assertSame(25.0, (float) $quant->quantity);
    }

    public function test_validate_creates_traceability_move_for_difference(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $this->adjustmentService->updateLine($adj->lines()->first(), 45); // +5
        $this->adjustmentService->validate($adj->fresh());

        $this->assertDatabaseHas('inventory_moves', [
            'company_id'  => $this->company->id,
            'product_id'  => $this->product->id,
            'product_qty' => 5,
            'state'       => 'done',
        ]);
    }

    public function test_validate_skips_lines_with_zero_difference(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);
        // No line update — difference stays zero
        $adj = $this->adjustmentService->validate($adj->fresh());

        $this->assertSame('done', $adj->state);
        $this->assertDatabaseMissing('inventory_moves', ['company_id' => $this->company->id]);
    }

    // ── Validate: special cases ───────────────────────────────────────────────

    public function test_validate_creates_quant_for_product_not_previously_on_hand(): void
    {
        // No existing quant — adjustment starts fresh
        $adj  = $this->createAdjustment();
        $adj  = $this->adjustmentService->startCount($adj); // 0 lines since no quants

        // Manually add a line simulating a counted-but-missing product
        \App\Models\Inventory\InventoryAdjustmentLine::create([
            'adjustment_id'  => $adj->id,
            'company_id'     => $this->company->id,
            'product_id'     => $this->product->id,
            'location_id'    => $this->stockLocation->id,
            'theoretical_qty' => 0,
            'inventory_qty'  => 25,
            'difference_qty' => 25,
            'created_by'     => $this->admin->id,
            'updated_by'     => $this->admin->id,
        ]);

        $this->adjustmentService->validate($adj->fresh());

        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->stockLocation->id)
            ->first();
        $this->assertNotNull($quant);
        $this->assertSame(25.0, (float) $quant->quantity);
    }

    public function test_validate_multi_product_adjustment(): void
    {
        $productB = Product::create([
            'name'         => 'Adj Product B',
            'company_id'   => $this->company->id,
            'uom_id'       => $this->uom->id,
            'uom_po_id'    => $this->uom->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);

        $this->seedStock(100);
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $productB->id,
            'location_id' => $this->stockLocation->id,
            'quantity'    => 50,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $this->assertSame(2, $adj->lines()->count(), 'Should create one line per product with existing quant');

        $lineA = $adj->lines()->where('product_id', $this->product->id)->first();
        $lineB = $adj->lines()->where('product_id', $productB->id)->first();

        $this->adjustmentService->updateLine($lineA, 90);  // −10
        $this->adjustmentService->updateLine($lineB, 60);  // +10

        $this->adjustmentService->validate($adj->fresh());

        $quantA = Quant::where('company_id', $this->company->id)->where('product_id', $this->product->id)->first();
        $quantB = Quant::where('company_id', $this->company->id)->where('product_id', $productB->id)->first();

        $this->assertSame(90.0, (float) $quantA->quantity);
        $this->assertSame(60.0, (float) $quantB->quantity);

        // 2 traceability moves — one per adjusted product
        $this->assertSame(
            2,
            Move::where('company_id', $this->company->id)
                ->where('name', 'like', 'Inventory Adjustment:%')
                ->count()
        );
    }

    public function test_validate_lot_tracked_adjustment(): void
    {
        $lotProduct = Product::create([
            'name'         => 'Lot Adj Product',
            'company_id'   => $this->company->id,
            'uom_id'       => $this->uom->id,
            'uom_po_id'    => $this->uom->id,
            'product_type' => 'storable',
            'tracking'     => 'lot',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);

        $lot = Lot::create([
            'company_id' => $this->company->id,
            'product_id' => $lotProduct->id,
            'name'       => 'ADJ-LOT',
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $lotProduct->id,
            'location_id' => $this->stockLocation->id,
            'lot_id'      => $lot->id,
            'quantity'    => 30,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $line = $adj->lines()->where('product_id', $lotProduct->id)->first();
        $this->assertNotNull($line);
        $this->assertSame($lot->id, $line->lot_id);

        $this->adjustmentService->updateLine($line, 35); // +5
        $this->adjustmentService->validate($adj->fresh());

        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $lotProduct->id)
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertSame(35.0, (float) $quant->quantity);
    }

    // ── Guards ────────────────────────────────────────────────────────────────

    public function test_cannot_validate_draft_adjustment_directly(): void
    {
        $adj = $this->createAdjustment();

        $this->expectException(\RuntimeException::class);
        $this->adjustmentService->validate($adj);
    }

    public function test_done_adjustment_cannot_be_deleted(): void
    {
        $this->seedStock(10);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);
        $adj = $this->adjustmentService->validate($adj->fresh());

        $this->expectException(\RuntimeException::class);
        $this->adjustmentService->delete($adj);
    }

    public function test_draft_adjustment_can_be_deleted(): void
    {
        $adj = $this->createAdjustment();
        $id  = $adj->id;

        $this->adjustmentService->delete($adj);

        $this->assertSoftDeleted('inventory_adjustments', ['id' => $id]);
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    public function test_admin_can_view_adjustments_index(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.adjustments.index'))
            ->assertOk();
    }

    public function test_admin_can_view_adjustment_show_page(): void
    {
        $adj = $this->createAdjustment();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.adjustments.show', $adj))
            ->assertOk()
            ->assertSee($adj->name);
    }

    public function test_admin_can_access_adjustment_create_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.adjustments.create'))
            ->assertOk();
    }

    public function test_start_count_via_http_changes_state(): void
    {
        $adj = $this->createAdjustment();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.adjustments.start', $adj))
            ->assertRedirect();

        $this->assertSame('in_progress', $adj->fresh()->state);
    }

    public function test_validate_adjustment_via_http(): void
    {
        $this->seedStock(20);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.adjustments.validate', $adj))
            ->assertRedirect();

        $this->assertSame('done', $adj->fresh()->state);
    }

    public function test_delete_draft_adjustment_via_http(): void
    {
        $adj = $this->createAdjustment();
        $id  = $adj->id;

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->delete(route('inventory.adjustments.delete', $adj))
            ->assertRedirect();

        $this->assertSoftDeleted('inventory_adjustments', ['id' => $id]);
    }

    // ── Company isolation ─────────────────────────────────────────────────────

    public function test_company_isolation_on_adjustments(): void
    {
        $otherCompany = Company::create(['name' => 'Other Adj Co', 'active' => true]);
        $otherAdj = InventoryAdjustment::create([
            'company_id' => $otherCompany->id,
            'name'       => 'INV/2025/OTHER',
            'state'      => 'draft',
            'date'       => now()->toDateString(),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.adjustments.show', $otherAdj))
            ->assertForbidden();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createAdjustment(): InventoryAdjustment
    {
        return $this->adjustmentService->create([
            'company_id' => $this->company->id,
            'date'       => now()->toDateString(),
        ]);
    }

    private function seedStock(float $qty): void
    {
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $this->product->id,
            'location_id' => $this->stockLocation->id,
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
