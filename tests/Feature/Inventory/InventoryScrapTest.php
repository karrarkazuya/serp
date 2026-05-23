<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
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

    public function test_validate_scrap_decreases_source_quant(): void
    {
        $this->seedStock(30);

        $scrap = $this->createScrapOrder(8);
        $this->scrapService->validate($scrap);

        $srcQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->srcLocation->id)
            ->first();

        $this->assertSame(22.0, (float) $srcQuant->quantity);
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
        $scrap = $this->scrapService->validate($scrap);

        $this->assertDatabaseHas('inventory_moves', [
            'company_id'      => $this->company->id,
            'product_id'      => $this->product->id,
            'location_src_id' => $this->srcLocation->id,
            'state'           => 'done',
        ]);
    }

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

        $this->assertDatabaseMissing('inventory_scrap_orders', ['id' => $id]);
    }

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
