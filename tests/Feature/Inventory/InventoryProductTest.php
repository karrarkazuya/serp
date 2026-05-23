<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Product;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Quant;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\ProductService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryProductTest extends TestCase
{
    use RefreshDatabase;

    private ProductService $productService;
    private User $admin;
    private Company $company;
    private Uom $uom;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->productService = app(ProductService::class);
        $this->admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('Test Inventory Co');
        $this->uom = Uom::where('name', 'Units')->firstOrFail();
    }

    public function test_product_can_be_created(): void
    {
        $product = $this->createProduct('Test Widget');

        $this->assertDatabaseHas('inventory_products', [
            'name'       => 'Test Widget',
            'company_id' => $this->company->id,
        ]);
        $this->assertSame('Test Widget', $product->name);
        $this->assertTrue($product->active);
    }

    public function test_product_can_be_updated(): void
    {
        $product = $this->createProduct('Old Name');

        $this->actingAs($this->admin);
        $updated = $this->productService->update($product, ['name' => 'New Name', 'sale_price' => 49.99]);

        $this->assertSame('New Name', $updated->name);
        $this->assertSame('49.9900', $updated->sale_price);
    }

    public function test_product_can_be_archived_and_unarchived(): void
    {
        $product = $this->createProduct('Archivable Widget');

        $this->actingAs($this->admin);
        $this->productService->archive($product);
        $this->assertFalse($product->fresh()->active);

        $this->productService->unarchive($product);
        $this->assertTrue($product->fresh()->active);
    }

    public function test_product_can_be_deleted(): void
    {
        $product = $this->createProduct('Deletable Widget');
        $id = $product->id;

        $this->actingAs($this->admin);
        $this->productService->delete($product);

        $this->assertSoftDeleted('inventory_products', ['id' => $id]);
    }

    public function test_admin_can_view_products_index(): void
    {
        $this->createProduct('Visible Product');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.index'))
            ->assertOk()
            ->assertSee('Visible Product');
    }

    public function test_admin_can_view_product_show_page(): void
    {
        $product = $this->createProduct('Show Page Product');

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.show', $product))
            ->assertOk()
            ->assertSee('Show Page Product');
    }

    public function test_admin_can_access_product_create_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.create'))
            ->assertOk();
    }

    public function test_admin_can_store_product_via_http(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.products.store'), [
                'name'         => 'HTTP Created Product',
                'company_id'   => (string) $this->company->id,
                'uom_id'       => (string) $this->uom->id,
                'uom_po_id'    => (string) $this->uom->id,
                'product_type' => 'storable',
                'tracking'     => 'none',
                'cost'         => '10.00',
                'sale_price'   => '20.00',
            ]);

        // Follow redirect to show page to see validation errors if any
        $response->assertRedirect();
        $this->assertDatabaseHas('inventory_products', [
            'name' => 'HTTP Created Product',
        ]);
    }

    public function test_user_without_permission_cannot_access_inventory_pages(): void
    {
        $user = User::where('email', 'user@example.com')->firstOrFail();
        $user->companies()->syncWithoutDetaching([$this->company->id]);

        $this->actingAs($user)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.products.index'))
            ->assertForbidden();
    }

    public function test_on_hand_quantity_counts_quants_in_internal_locations(): void
    {
        $product = $this->createProduct('Quant Product');

        // Simulate two quants for this product in internal locations
        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->createInternalLocation()->id,
            'quantity'    => 15,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        Quant::create([
            'company_id'  => $this->company->id,
            'product_id'  => $product->id,
            'location_id' => $this->createInternalLocation()->id,
            'quantity'    => 10,
            'in_date'     => now(),
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        $onHand = $product->quants()->sum('quantity');
        $this->assertSame(25.0, (float) $onHand);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createProduct(string $name): Product
    {
        $this->actingAs($this->admin);
        return $this->productService->create([
            'name'         => $name,
            'company_id'   => $this->company->id,
            'uom_id'       => $this->uom->id,
            'uom_po_id'    => $this->uom->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'cost'         => 5.00,
            'sale_price'   => 10.00,
            'active'       => true,
        ]);
    }

    private function createCompany(string $name): Company
    {
        $company = Company::create(['name' => $name, 'active' => true, 'currency' => 'USD']);
        $this->admin->companies()->syncWithoutDetaching([$company->id]);
        $this->admin->update(['company_id' => $company->id]);
        return $company;
    }

    private function createInternalLocation(): \App\Models\Inventory\Location
    {
        return \App\Models\Inventory\Location::create([
            'company_id'  => $this->company->id,
            'name'        => 'Test Stock ' . uniqid(),
            'usage'       => 'internal',
            'active'      => true,
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);
    }
}
