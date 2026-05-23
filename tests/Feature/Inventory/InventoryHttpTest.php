<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Product;
use App\Models\Inventory\Uom;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\ProductService;
use App\Services\Inventory\WarehouseService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * HTTP-layer tests covering:
 * - Core seeder assertions (UoMs, virtual locations)
 * - Warehouse setup via WarehouseService
 * - Dashboard and all configuration pages
 * - Warehouse CRUD via HTTP
 * - Products: create, show, edit, update via HTTP
 * - Lots index page
 * - Replenishment index page
 * - Stock on-hand page
 * - Permission gates
 * - Company isolation on locations list
 */
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
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Virtual Locations',   'company_id' => null, 'usage' => 'view']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Suppliers',            'company_id' => null, 'usage' => 'supplier']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Customers',            'company_id' => null, 'usage' => 'customer']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Inventory Adjustments','company_id' => null, 'usage' => 'inventory']);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Scrap',                'company_id' => null, 'usage' => 'scrap', 'scrap_location' => true]);
        $this->assertDatabaseHas('inventory_locations', ['name' => 'Production',           'company_id' => null, 'usage' => 'production']);
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

        $locations = Location::where('company_id', $this->company->id)->get();
        $this->assertGreaterThanOrEqual(5, $locations->count());
        $this->assertTrue($locations->contains('name', 'Stock'));
        $this->assertTrue($locations->contains('name', 'Input'));
        $this->assertTrue($locations->contains('name', 'Output'));

        $opTypes = OperationType::where('company_id', $this->company->id)->get();
        $this->assertSame(3, $opTypes->count());
        $this->assertTrue($opTypes->contains('code', 'incoming'));
        $this->assertTrue($opTypes->contains('code', 'outgoing'));
        $this->assertTrue($opTypes->contains('code', 'internal'));

        $this->assertNotNull($warehouse->lot_stock_id);
        $this->assertNotNull($warehouse->view_location_id);
    }

    public function test_receipt_and_delivery_operation_types_have_cross_return_types(): void
    {
        $this->actingAs($this->admin);
        app(WarehouseService::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Return WH',
            'short_name' => 'RWH',
            'active'     => true,
        ]);

        $receipt  = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->first();
        $delivery = OperationType::where('company_id', $this->company->id)->where('code', 'outgoing')->first();
        $internal = OperationType::where('company_id', $this->company->id)->where('code', 'internal')->first();

        $this->assertSame($delivery->id, $receipt->return_picking_type_id);
        $this->assertSame($receipt->id,  $delivery->return_picking_type_id);
        $this->assertSame($internal->id, $internal->return_picking_type_id);
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

    // ── Warehouse HTTP CRUD ───────────────────────────────────────────────────

    public function test_admin_can_view_warehouse_create_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.warehouses.create'))
            ->assertOk();
    }

    public function test_admin_can_create_warehouse_via_http(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.config.warehouses.store'), [
                'company_id'      => $this->company->id,
                'name'            => 'HTTP Warehouse',
                'short_name'      => 'HWH',
                'reception_steps' => 'one_step',
                'delivery_steps'  => 'one_step',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_warehouses', [
            'company_id' => $this->company->id,
            'name'       => 'HTTP Warehouse',
        ]);

        // Warehouse setup should also create locations
        $this->assertDatabaseHas('inventory_locations', [
            'company_id' => $this->company->id,
            'name'       => 'Stock',
        ]);
    }

    public function test_admin_can_view_warehouse_show_page(): void
    {
        $this->actingAs($this->admin);
        $warehouse = app(WarehouseService::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Show WH',
            'short_name' => 'SWH',
            'active'     => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.config.warehouses.show', $warehouse))
            ->assertOk()
            ->assertSee('Show WH');
    }

    public function test_admin_can_update_warehouse_via_http(): void
    {
        $this->actingAs($this->admin);
        $warehouse = app(WarehouseService::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Old WH Name',
            'short_name' => 'OWH',
            'active'     => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->put(route('inventory.config.warehouses.update', $warehouse), [
                'name'            => 'New WH Name',
                'reception_steps' => 'one_step',
                'delivery_steps'  => 'one_step',
            ])
            ->assertRedirect();

        $this->assertSame('New WH Name', $warehouse->fresh()->name);
    }

    public function test_admin_can_archive_and_unarchive_warehouse(): void
    {
        $this->actingAs($this->admin);
        $warehouse = app(WarehouseService::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Archive WH',
            'short_name' => 'AWH',
            'active'     => true,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->patch(route('inventory.config.warehouses.archive', $warehouse))
            ->assertRedirect();

        $this->assertFalse($warehouse->fresh()->active);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->patch(route('inventory.config.warehouses.unarchive', $warehouse))
            ->assertRedirect();

        $this->assertTrue($warehouse->fresh()->active);
    }

    // ── Products HTTP ─────────────────────────────────────────────────────────

    public function test_admin_can_view_products_index(): void
    {
        $uom = Uom::where('name', 'Units')->firstOrFail();
        $productSvc = app(ProductService::class);
        $this->actingAs($this->admin);
        $productSvc->create(['name' => 'Listed Product', 'company_id' => $this->company->id, 'uom_id' => $uom->id, 'uom_po_id' => $uom->id, 'product_type' => 'storable', 'tracking' => 'none', 'active' => true]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee('Listed Product');
    }

    public function test_admin_can_view_product_create_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.create'))
            ->assertOk();
    }

    public function test_admin_can_store_product_via_http(): void
    {
        $uom = Uom::where('name', 'Units')->firstOrFail();

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.products.store'), [
                'name'         => 'HTTP Created Product',
                'company_id'   => (string) $this->company->id,
                'uom_id'       => (string) $uom->id,
                'uom_po_id'    => (string) $uom->id,
                'product_type' => 'storable',
                'tracking'     => 'none',
                'cost'         => '10.00',
                'sale_price'   => '20.00',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('inventory_products', ['name' => 'HTTP Created Product']);
    }

    public function test_admin_can_view_product_edit_form(): void
    {
        $uom = Uom::where('name', 'Units')->firstOrFail();
        $this->actingAs($this->admin);
        $product = app(ProductService::class)->create(['name' => 'Edit Me', 'company_id' => $this->company->id, 'uom_id' => $uom->id, 'uom_po_id' => $uom->id, 'product_type' => 'storable', 'tracking' => 'none', 'active' => true]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.edit', $product))
            ->assertOk()
            ->assertSee('Edit Me');
    }

    public function test_admin_can_update_product_via_http(): void
    {
        $uom = Uom::where('name', 'Units')->firstOrFail();
        $this->actingAs($this->admin);
        $product = app(ProductService::class)->create(['name' => 'Before Update', 'company_id' => $this->company->id, 'uom_id' => $uom->id, 'uom_po_id' => $uom->id, 'product_type' => 'storable', 'tracking' => 'none', 'active' => true]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->put(route('inventory.products.update', $product), [
                'name'         => 'After Update',
                'company_id'   => (string) $this->company->id,
                'uom_id'       => (string) $uom->id,
                'uom_po_id'    => (string) $uom->id,
                'product_type' => 'storable',
                'tracking'     => 'none',
                'sale_price'   => '99.99',
            ])
            ->assertRedirect();

        $this->assertSame('After Update', $product->fresh()->name);
    }

    // ── Lots index ────────────────────────────────────────────────────────────

    public function test_admin_can_view_lots_index(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.lots.index'))
            ->assertOk();
    }

    // ── Replenishment index ───────────────────────────────────────────────────

    public function test_admin_can_view_replenishment_index(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.replenishment.index'))
            ->assertOk();
    }

    // ── Stock on-hand ─────────────────────────────────────────────────────────

    public function test_admin_can_view_stock_on_hand_page(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.reports.stock'))
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

    public function test_user_without_permission_cannot_access_products_index(): void
    {
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        $this->actingAs($user)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.index'))
            ->assertForbidden();
    }

    // ── Company isolation ─────────────────────────────────────────────────────

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

    public function test_products_index_does_not_show_other_company_products(): void
    {
        $uom = Uom::where('name', 'Units')->firstOrFail();
        $this->actingAs($this->admin);

        $otherCompany = Company::create(['name' => 'Other Products Co', 'active' => true]);
        Product::create([
            'name'         => 'Secret Other Product',
            'company_id'   => $otherCompany->id,
            'uom_id'       => $uom->id,
            'uom_po_id'    => $uom->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertDontSee('Secret Other Product');
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
