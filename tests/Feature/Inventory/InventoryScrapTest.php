<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\Lot;
use App\Models\Inventory\Move;
use App\Models\Inventory\Product;
use App\Models\Inventory\Quant;
use App\Models\Inventory\ScrapOrder;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\ScrapService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryScrapTest extends TestCase
{
    use RefreshDatabase;

    private ScrapService $scrapService;
    private User $admin;
    private Company $company;
    private Product $product;
    private Uom $uom;
    private Location $srcLocation;
    private Location $scrapLocation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->scrapService = app(ScrapService::class);
        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('Scrap Co');
        $this->uom     = Uom::where('name', 'Units')->firstOrFail();

        $this->actingAs($this->admin);

        $this->srcLocation = Location::create([
            'company_id'  => $this->company->id,
            'name'        => 'Scrap Test Stock',
            'usage'       => 'internal',
            'active'      => true,
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $this->scrapLocation = Location::where('usage', 'scrap')->whereNull('company_id')->firstOrFail();

        $this->product = Product::create([
            'name'         => 'Scrap Product',
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

    public function test_scrap_order_is_created_in_draft_state(): void
    {
        $scrap = $this->createScrapOrder(5);

        $this->assertDatabaseHas('inventory_scrap_orders', [
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'scrap_qty'  => 5,
            'state'      => 'draft',
        ]);
        $this->assertStringStartsWith('SP/', $scrap->name);
    }

    public function test_scrap_order_auto_names_sequentially(): void
    {
        $first  = $this->createScrapOrder(1);
        $second = $this->createScrapOrder(1);

        $this->assertNotSame($first->name, $second->name);
        $this->assertStringStartsWith('SP/', $first->name);
        $this->assertStringStartsWith('SP/', $second->name);
    }

    // ── Storable product scrapping ────────────────────────────────────────────

    public function test_validate_scrap_decreases_source_quant(): void
    {
        $this->seedStock(30);

        $scrap = $this->createScrapOrder(8);
        $this->scrapService->validate($scrap);

        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->srcLocation->id)
            ->first();

        $this->assertSame(22.0, (float) $quant->quantity);
    }

    public function test_validate_scrap_increases_scrap_location_quant(): void
    {
        $this->seedStock(20);

        $scrap = $this->createScrapOrder(6);
        $this->scrapService->validate($scrap);

        $scrapQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->scrapLocation->id)
            ->first();

        $this->assertSame(6.0, (float) $scrapQuant->quantity);
    }

    public function test_validate_scrap_marks_order_as_done(): void
    {
        $this->seedStock(10);

        $scrap = $this->createScrapOrder(3);
        $scrap = $this->scrapService->validate($scrap);

        $this->assertSame('done', $scrap->state);
        $this->assertNotNull($scrap->date_done);
    }

    public function test_validate_scrap_creates_traceability_move(): void
    {
        $this->seedStock(10);

        $scrap = $this->createScrapOrder(3);
        $this->scrapService->validate($scrap);

        $this->assertDatabaseHas('inventory_moves', [
            'company_id'       => $this->company->id,
            'product_id'       => $this->product->id,
            'location_src_id'  => $this->srcLocation->id,
            'location_dest_id' => $this->scrapLocation->id,
            'qty_done'         => 3,
            'state'            => 'done',
        ]);
    }

    public function test_scrap_move_is_linked_to_scrap_order(): void
    {
        $this->seedStock(15);
        $scrap = $this->createScrapOrder(5);
        $scrap = $this->scrapService->validate($scrap);

        $this->assertNotNull($scrap->move_id);
        $move = Move::find($scrap->move_id);
        $this->assertNotNull($move);
        $this->assertSame($this->product->id, $move->product_id);
    }

    // ── Insufficient stock ────────────────────────────────────────────────────

    public function test_validate_scrap_with_insufficient_stock_throws_exception(): void
    {
        $this->seedStock(5); // only 5 in stock

        $scrap = $this->createScrapOrder(10); // request 10

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/insufficient/i');
        $this->scrapService->validate($scrap);
    }

    public function test_validate_scrap_exact_stock_succeeds(): void
    {
        $this->seedStock(10);

        $scrap = $this->createScrapOrder(10); // exactly the available qty
        $scrap = $this->scrapService->validate($scrap);

        $this->assertSame('done', $scrap->state);

        $quant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->srcLocation->id)
            ->first();
        $this->assertSame(0.0, (float) $quant->quantity);
    }

    // ── Consumable product ────────────────────────────────────────────────────

    public function test_scrap_consumable_product_creates_move_but_no_quant_change(): void
    {
        $consumable = Product::create([
            'name'         => 'Scrap Consumable',
            'company_id'   => $this->company->id,
            'uom_id'       => $this->uom->id,
            'uom_po_id'    => $this->uom->id,
            'product_type' => 'consumable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);

        $scrap = ScrapOrder::create([
            'company_id'        => $this->company->id,
            'product_id'        => $consumable->id,
            'uom_id'            => $this->uom->id,
            'location_id'       => $this->srcLocation->id,
            'scrap_location_id' => $this->scrapLocation->id,
            'scrap_qty'         => 5,
            'state'             => 'draft',
            'name'              => 'SP/00001',
            'created_by'        => $this->admin->id,
            'updated_by'        => $this->admin->id,
        ]);

        $scrap = $this->scrapService->validate($scrap);

        $this->assertSame('done', $scrap->state);

        // Traceability move still created
        $this->assertDatabaseHas('inventory_moves', [
            'company_id'       => $this->company->id,
            'product_id'       => $consumable->id,
            'state'            => 'done',
        ]);

        // No quants created for consumable
        $this->assertDatabaseMissing('inventory_quants', [
            'company_id' => $this->company->id,
            'product_id' => $consumable->id,
        ]);
    }

    // ── Lot-tracked scrap ─────────────────────────────────────────────────────

    public function test_scrap_lot_tracked_product_deducts_correct_lot_quant(): void
    {
        $lotProduct = Product::create([
            'name'         => 'Lot Scrap Product',
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
            'name'       => 'SCRAP-LOT',
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $lotProduct->id,
            'location_id' => $this->srcLocation->id,
            'lot_id'      => $lot->id,
            'quantity'    => 20,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $scrap = ScrapOrder::create([
            'company_id'        => $this->company->id,
            'product_id'        => $lotProduct->id,
            'uom_id'            => $this->uom->id,
            'location_id'       => $this->srcLocation->id,
            'scrap_location_id' => $this->scrapLocation->id,
            'lot_id'            => $lot->id,
            'scrap_qty'         => 8,
            'state'             => 'draft',
            'name'              => 'SP/00002',
            'created_by'        => $this->admin->id,
            'updated_by'        => $this->admin->id,
        ]);

        $this->scrapService->validate($scrap);

        $remaining = Quant::where('company_id', $this->company->id)
            ->where('product_id', $lotProduct->id)
            ->where('location_id', $this->srcLocation->id)
            ->where('lot_id', $lot->id)
            ->first();

        $this->assertSame(12.0, (float) $remaining->quantity);

        $scrapQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $lotProduct->id)
            ->where('location_id', $this->scrapLocation->id)
            ->where('lot_id', $lot->id)
            ->first();
        $this->assertSame(8.0, (float) $scrapQuant->quantity);
    }

    // ── Deletion ──────────────────────────────────────────────────────────────

    public function test_done_scrap_order_cannot_be_deleted(): void
    {
        $this->seedStock(5);
        $scrap = $this->createScrapOrder(5);
        $scrap = $this->scrapService->validate($scrap);

        $this->expectException(\RuntimeException::class);
        $this->scrapService->delete($scrap);
    }

    public function test_draft_scrap_order_can_be_deleted(): void
    {
        $scrap = $this->createScrapOrder(2);
        $id    = $scrap->id;

        $this->scrapService->delete($scrap);

        $this->assertSoftDeleted('inventory_scrap_orders', ['id' => $id]);
    }

    // ── HTTP ──────────────────────────────────────────────────────────────────

    public function test_admin_can_view_scrap_index(): void
    {
        $this->createScrapOrder(1);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.scrap.index'))
            ->assertOk();
    }

    public function test_admin_can_access_scrap_create_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.scrap.create'))
            ->assertOk();
    }

    public function test_admin_can_view_scrap_show_page(): void
    {
        $scrap = $this->createScrapOrder(3);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.scrap.show', $scrap))
            ->assertOk()
            ->assertSee($scrap->name);
    }

    public function test_validate_scrap_via_http_marks_done(): void
    {
        $this->seedStock(25);
        $scrap = $this->createScrapOrder(10);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.scrap.validate', $scrap))
            ->assertRedirect();

        $this->assertSame('done', $scrap->fresh()->state);
    }

    public function test_validate_scrap_with_insufficient_stock_via_http_flashes_error(): void
    {
        $this->seedStock(3);
        $scrap = $this->createScrapOrder(10);

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.scrap.validate', $scrap));

        $response->assertRedirect();
        $this->assertNotNull(session('error'));
        $this->assertSame('draft', $scrap->fresh()->state);
    }

    public function test_delete_draft_scrap_via_http(): void
    {
        $scrap = $this->createScrapOrder(1);
        $id    = $scrap->id;

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->delete(route('inventory.scrap.delete', $scrap))
            ->assertRedirect();

        $this->assertSoftDeleted('inventory_scrap_orders', ['id' => $id]);
    }

    // ── Company isolation ─────────────────────────────────────────────────────

    public function test_company_isolation_prevents_cross_company_scrap_access(): void
    {
        $otherCompany = Company::create(['name' => 'Other Scrap Co', 'active' => true]);
        $otherScrap = ScrapOrder::create([
            'company_id'        => $otherCompany->id,
            'product_id'        => $this->product->id,
            'uom_id'            => $this->uom->id,
            'location_id'       => $this->srcLocation->id,
            'scrap_location_id' => $this->scrapLocation->id,
            'scrap_qty'         => 5,
            'state'             => 'draft',
            'name'              => 'SP/OTHER-001',
            'created_by'        => $this->admin->id,
            'updated_by'        => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.scrap.show', $otherScrap))
            ->assertForbidden();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createScrapOrder(float $qty): ScrapOrder
    {
        return $this->scrapService->create([
            'company_id'        => $this->company->id,
            'product_id'        => $this->product->id,
            'uom_id'            => $this->uom->id,
            'location_id'       => $this->srcLocation->id,
            'scrap_location_id' => $this->scrapLocation->id,
            'scrap_qty'         => $qty,
        ]);
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
