<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\WarehouseService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryHttpTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('HTTP Test Co');
    }

    // ── UoM seeding ──────────────────────────────────────────────────────────

    public function test_core_seeder_creates_default_uom_categories(): void
    {
        $names = \App\Models\Inventory\UomCategory::pluck('name')->all();

        $this->assertContains('Units', $names);
        $this->assertContains('Weight', $names);
        $this->assertContains('Volume', $names);
        $this->assertContains('Time', $names);
        $this->assertContains('Length', $names);
    }

    public function test_core_seeder_creates_reference_uoms(): void
    {
        $this->assertDatabaseHas('inventory_uoms', ['name' => 'Units', 'uom_type' => 'reference']);
        $this->assertDatabaseHas('inventory_uoms', ['name' => 'kg',    'uom_type' => 'reference']);
        $this->assertDatabaseHas('inventory_uoms', ['name' => 'L',     'uom_type' => 'reference']);
        $this->assertDatabaseHas('inventory_uoms', ['name' => 'm',     'uom_type' => 'reference']);
        $this->assertDatabaseHas('inventory_uoms', ['name' => 'Hours', 'uom_type' => 'reference']);
    }

    public function test_core_seeder_creates_global_virtual_locations(): void
    {
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Virtual Locations', 'company_id' => null, 'usage' => 'view']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Suppliers',             'company_id' => null, 'usage' => 'supplier']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Customers',             'company_id' => null, 'usage' => 'customer']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Inventory Adjustments', 'company_id' => null, 'usage' => 'inventory']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Scrap',                 'company_id' => null, 'usage' => 'scrap', 'scrap_location' => true]);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Production',            'company_id' => null, 'usage' => 'production']);
    }

    // ── Warehouse setup ───────────────────────────────────────────────────────

    public function test_warehouse_service_creates_locations_and_operation_types(): void
    {
        $this->actingAs($this->admin);
        $warehouseService = app(WarehouseService::class);

        $warehouse = $warehouseService->create([
            'company_id' => $this->company->id,
            'name'       => 'Main WH',
            'short_name' => 'MWH',
            'active'     => true,
        ]);

        $this->assertDatabaseHas('inventory_warehouses', ['name' => 'Main WH', 'company_id' => $this->company->id]);

        // Should have created 5 locations (view + stock + input + output + packing)
        $locations = Location::where('company_id', $this->company->id)->get();
        $this->assertGreaterThanOrEqual(5, $locations->count());
        $this->assertTrue($locations->contains('name', 'Stock'));

        // Should have 3 operation types (incoming, outgoing, internal)
        $opTypes = OperationType::where('company_id', $this->company->id)->get();
        $this->assertSame(3, $opTypes->count());
        $this->assertTrue($opTypes->contains('code', 'incoming'));
        $this->assertTrue($opTypes->contains('code', 'outgoing'));
        $this->assertTrue($opTypes->contains('code', 'internal'));

        // Warehouse should be linked to stock location
        $this->assertNotNull($warehouse->lot_stock_id);
    }

    // ── Dashboard ─────────────────────────────────────────────────────────────

    public function test_admin_can_view_inventory_dashboard(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.dashboard'))
            ->assertOk();
    }

    // ── Configuration pages ───────────────────────────────────────────────────

    public function test_admin_can_view_uoms_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.uoms.index'))
            ->assertOk()
            ->assertSee('Units');
    }

    public function test_admin_can_view_locations_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.locations.index'))
            ->assertOk();
    }

    public function test_admin_can_view_warehouses_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.warehouses.index'))
            ->assertOk();
    }

    public function test_admin_can_view_operation_types_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.operation-types.index'))
            ->assertOk();
    }

    public function test_admin_can_view_routes_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.routes.index'))
            ->assertOk();
    }

    public function test_admin_can_view_putaway_rules_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.putaway-rules.index'))
            ->assertOk();
    }

    public function test_admin_can_view_product_categories_config_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.product-categories.index'))
            ->assertOk();
    }

    // ── Permission gates ──────────────────────────────────────────────────────

    public function test_user_without_inventory_permission_cannot_see_dashboard(): void
    {
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        $this->actingAs($user)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.dashboard'))
            ->assertForbidden();
    }

    public function test_user_without_inventory_config_permission_cannot_access_config(): void
    {
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        $this->actingAs($user)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.warehouses.index'))
            ->assertForbidden();
    }

    public function test_company_scoped_locations_do_not_leak_to_other_companies(): void
    {
        $this->actingAs($this->admin);

        $otherCompany = Company::create(['name' => 'Other Inventory Co', 'active' => true]);
        Location::create([
            'company_id'  => $otherCompany->id,
            'name'        => 'Other Company Hidden Stock',
            'usage'       => 'internal',
            'active'      => true,
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.locations.index'))
            ->assertOk()
            ->assertDontSee('Other Company Hidden Stock');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createCompany(string $name): Company
    {
        $company = Company::create(['name' => $name, 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        $this->admin->update(['company_id' => $company->id]);
        return $company;
    }
}
