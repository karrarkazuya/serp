<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\InventoryAdjustment;
use App\Models\Inventory\Location;
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

    public function test_adjustment_is_created_in_draft_state(): void
    {
        $adj = $this->createAdjustment();

        $this->assertStringStartsWith('INV/', $adj->name);
        $this->assertSame('draft', $adj->state);
    }

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

    public function test_validate_positive_adjustment_increases_quant(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        $line = $adj->lines()->first();
        $this->adjustmentService->updateLine($line, 50);

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

        $line = $adj->lines()->first();
        $this->adjustmentService->updateLine($line, 25);

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

        $line = $adj->lines()->first();
        $this->adjustmentService->updateLine($line, 45); // +5

        $this->adjustmentService->validate($adj->fresh());

        $this->assertDatabaseHas('inventory_moves', [
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'product_qty' => 5,
            'state'      => 'done',
        ]);
    }

    public function test_validate_skips_lines_with_zero_difference(): void
    {
        $this->seedStock(40);
        $adj = $this->createAdjustment();
        $adj = $this->adjustmentService->startCount($adj);

        // No line update — difference is zero
        $adj = $this->adjustmentService->validate($adj->fresh());

        $this->assertSame('done', $adj->state);
        // No moves created for zero-difference lines
        $this->assertDatabaseMissing('inventory_moves', [
            'company_id' => $this->company->id,
        ]);
    }

    public function test_cannot_validate_draft_adjustment_directly(): void
    {
        $adj = $this->createAdjustment(); // stays draft

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
