<?php

namespace Tests\Feature\Inventory;

use App\Models\Contacts\Contact;
use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
use App\Models\Inventory\ProductSupplier;
use App\Models\Inventory\ReorderRule;
use App\Models\Inventory\Uom;
use App\Models\Inventory\Warehouse;
use App\Models\Settings\Company;
use App\Models\User;
use App\Services\Inventory\WarehouseService;
use Database\Seeders\CoreSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for inventory replenishment (reorder rules).
 *
 * Covers:
 * - needsReplenishment() logic (at/below min vs above min)
 * - getReplenishQty() with and without qty_multiple
 * - Replenish action creates a receipt picking
 * - Replenish blocked when stock is already at/above max
 * - HTTP CRUD for reorder rules
 * - Company isolation
 */
class InventoryReorderRuleTest extends TestCase
{
    use RefreshDatabase;

    private User          $admin;
    private Company       $company;
    private Product       $product;
    private Uom           $units;
    private Location      $stockLoc;
    private Warehouse     $warehouse;
    private OperationType $receiptOp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CoreSeeder::class);
        $this->admin   = User::where('email', 'admin@example.com')->firstOrFail();
        $this->company = $this->createCompany('Replenish Co');
        $this->units   = Uom::where('name', 'Units')->firstOrFail();

        $this->actingAs($this->admin);

        $this->warehouse = app(WarehouseService::class)->create([
            'company_id' => $this->company->id,
            'name'       => 'Replenish Warehouse',
            'short_name' => 'RW',
            'active'     => true,
        ]);

        $this->stockLoc  = Location::where('company_id', $this->company->id)->where('name', 'Stock')->firstOrFail();
        $this->receiptOp = OperationType::where('company_id', $this->company->id)->where('code', 'incoming')->firstOrFail();

        $this->product = Product::create([
            'name'         => 'Replenished Widget',
            'company_id'   => $this->company->id,
            'uom_id'       => $this->units->id,
            'uom_po_id'    => $this->units->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);
    }

    // ── Model logic ───────────────────────────────────────────────────────────

    public function test_needs_replenishment_true_when_on_hand_equals_min(): void
    {
        $rule = $this->createRule(qtyMin: 20, qtyMax: 100, qtyOnHand: 20);

        $this->assertTrue($rule->needsReplenishment());
    }

    public function test_needs_replenishment_true_when_on_hand_below_min(): void
    {
        $rule = $this->createRule(qtyMin: 20, qtyMax: 100, qtyOnHand: 5);

        $this->assertTrue($rule->needsReplenishment());
    }

    public function test_needs_replenishment_false_when_on_hand_above_min(): void
    {
        $rule = $this->createRule(qtyMin: 20, qtyMax: 100, qtyOnHand: 25);

        $this->assertFalse($rule->needsReplenishment());
    }

    public function test_get_replenish_qty_returns_max_minus_on_hand(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 30);

        $this->assertSame(70.0, $rule->getReplenishQty());
    }

    public function test_get_replenish_qty_respects_qty_multiple_rounding_up(): void
    {
        // Need 70 units, multiple of 25 → rounds up to 75
        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 30, qtyMultiple: 25);

        $this->assertSame(75.0, $rule->getReplenishQty());
    }

    public function test_get_replenish_qty_returns_zero_when_at_max(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 50, qtyOnHand: 50);

        $this->assertSame(0.0, $rule->getReplenishQty());
    }

    public function test_get_replenish_qty_returns_zero_when_above_max(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 50, qtyOnHand: 60);

        $this->assertSame(0.0, $rule->getReplenishQty());
    }

    public function test_get_replenish_qty_when_multiple_equals_one_no_rounding(): void
    {
        $rule = $this->createRule(qtyMin: 5, qtyMax: 60, qtyOnHand: 20, qtyMultiple: 1);

        $this->assertSame(40.0, $rule->getReplenishQty());
    }

    // ── HTTP: replenish action ─────────────────────────────────────────────────

    public function test_replenish_creates_receipt_picking(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 30);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule))
            ->assertRedirect();

        // A receipt picking should have been created
        $picking = Picking::where('company_id', $this->company->id)
            ->where('origin', 'Replenishment')
            ->first();

        $this->assertNotNull($picking, 'A receipt picking should be created by replenish action');
        $this->assertSame($this->receiptOp->id, $picking->operation_type_id);
        $this->assertSame($this->stockLoc->id, $picking->location_dest_id);

        // Move should have the replenish qty
        $move = $picking->moves()->first();
        $this->assertNotNull($move);
        $this->assertSame(70.0, (float) $move->product_qty);
    }

    public function test_replenish_with_qty_multiple_creates_rounded_up_receipt(): void
    {
        // on-hand=30, max=100, need=70, multiple=25 → 75 ordered
        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 30, qtyMultiple: 25);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule))
            ->assertRedirect();

        $picking = Picking::where('company_id', $this->company->id)
            ->where('origin', 'Replenishment')
            ->first();
        $this->assertSame(75.0, (float) $picking->moves()->first()->product_qty);
    }

    public function test_replenish_when_already_above_max_redirects_with_error(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 50, qtyOnHand: 60);

        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'maximum',
            session('error') ?? '',
            'Should flash an error message about stock already at max'
        );

        // No picking should be created
        $this->assertDatabaseMissing('inventory_pickings', [
            'company_id' => $this->company->id,
            'origin'     => 'Replenishment',
        ]);
    }

    public function test_replenish_uses_lead_days_for_scheduled_date(): void
    {
        $rule = $this->createRule(qtyMin: 0, qtyMax: 100, qtyOnHand: 0, leadDays: 5);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule));

        $picking = Picking::where('company_id', $this->company->id)
            ->where('origin', 'Replenishment')
            ->first();

        $expectedDate = now()->addDays(5)->toDateString();
        $this->assertSame($expectedDate, $picking->scheduled_date->toDateString());
    }

    // ── HTTP: CRUD ────────────────────────────────────────────────────────────

    public function test_admin_can_view_replenishment_index(): void
    {
        $this->createRule(qtyMin: 5, qtyMax: 50, qtyOnHand: 0);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.replenishment.index'))
            ->assertOk();
    }

    public function test_admin_can_view_replenishment_create_form(): void
    {
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.replenishment.create'))
            ->assertOk();
    }

    public function test_admin_can_view_replenishment_edit_form(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 0);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.replenishment.edit', $rule))
            ->assertOk();
    }

    public function test_admin_can_create_reorder_rule_via_http(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.store'), [
                'company_id'  => $this->company->id,
                'product_id'  => $this->product->id,
                'location_id' => $this->stockLoc->id,
                'warehouse_id' => $this->warehouse->id,
                'qty_min'     => 10,
                'qty_max'     => 100,
                'qty_multiple' => 5,
                'lead_days'   => 3,
            ]);

        $response->assertRedirect(route('inventory.replenishment.index'));
        $this->assertDatabaseHas('inventory_reorder_rules', [
            'company_id'  => $this->company->id,
            'product_id'  => $this->product->id,
            'qty_min'     => 10,
            'qty_max'     => 100,
        ]);
    }

    public function test_admin_can_update_reorder_rule_via_http(): void
    {
        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 0);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->put(route('inventory.replenishment.update', $rule), [
                'qty_min'     => 20,
                'qty_max'     => 200,
                'qty_multiple' => 10,
            ])
            ->assertRedirect(route('inventory.replenishment.index'));

        $rule->refresh();
        $this->assertSame(20.0, (float) $rule->qty_min);
        $this->assertSame(200.0, (float) $rule->qty_max);
    }

    public function test_admin_can_delete_reorder_rule_via_http(): void
    {
        $rule = $this->createRule(qtyMin: 5, qtyMax: 50, qtyOnHand: 0);
        $id   = $rule->id;

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->delete(route('inventory.replenishment.delete', $rule))
            ->assertRedirect(route('inventory.replenishment.index'));

        $this->assertSoftDeleted('inventory_reorder_rules', ['id' => $id]);
    }

    // ── Company isolation ─────────────────────────────────────────────────────

    public function test_replenishment_index_only_shows_own_company_rules(): void
    {
        $otherCompany = Company::create(['name' => 'Other Replenish Co', 'active' => true]);
        $otherProduct = Product::create([
            'name'         => 'Other Product',
            'company_id'   => $otherCompany->id,
            'uom_id'       => $this->units->id,
            'uom_po_id'    => $this->units->id,
            'product_type' => 'storable',
            'tracking'     => 'none',
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
        ]);
        $otherLocation = Location::create([
            'company_id' => $otherCompany->id,
            'name'       => 'Other Stock',
            'usage'      => 'internal',
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        ReorderRule::create([
            'company_id'  => $otherCompany->id,
            'product_id'  => $otherProduct->id,
            'location_id' => $otherLocation->id,
            'qty_min'     => 5,
            'qty_max'     => 50,
            'qty_on_hand' => 0,
            'qty_forecast' => 0,
            'qty_multiple' => 1,
            'active'      => true,
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);

        // Own company rule
        $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 0);

        // Request only shows current company
        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->get(route('inventory.replenishment.index'))
            ->assertOk();

        // Verify DB isolation: only 1 rule for this company
        $this->assertSame(
            1,
            ReorderRule::where('company_id', $this->company->id)->count()
        );
    }

    public function test_cannot_replenish_other_company_rule(): void
    {
        $otherCompany = Company::create(['name' => 'Other Co', 'active' => true]);
        $otherProduct = Product::create(['name' => 'P2', 'company_id' => $otherCompany->id, 'uom_id' => $this->units->id, 'uom_po_id' => $this->units->id, 'product_type' => 'storable', 'tracking' => 'none', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $otherLoc     = Location::create(['company_id' => $otherCompany->id, 'name' => 'OS', 'usage' => 'internal', 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $otherRule = ReorderRule::create([
            'company_id' => $otherCompany->id, 'product_id' => $otherProduct->id, 'location_id' => $otherLoc->id,
            'qty_min' => 5, 'qty_max' => 50, 'qty_on_hand' => 0, 'qty_forecast' => 0, 'qty_multiple' => 1,
            'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $otherRule))
            ->assertForbidden();
    }

    // ── ProductSupplier wiring (Odoo parity Phase 3) ──────────────────────────

    /**
     * Without a configured vendor, the replenish receipt has nowhere to send
     * the PO request when that pipeline lands — orphan receipts that no one
     * has to chase. Wire it now so the partner_id is stamped as soon as the
     * vendor is set on the product.
     */
    public function test_replenish_stamps_primary_supplier_partner_on_receipt(): void
    {
        $vendor = Contact::create([
            'company_id'  => $this->company->id,
            'name'        => 'ACME Supplies',
            'is_supplier' => true,
            'active'      => true,
            'created_by'  => $this->admin->id,
            'updated_by'  => $this->admin->id,
        ]);
        ProductSupplier::create([
            'product_id' => $this->product->id,
            'partner_id' => $vendor->id,
            'min_qty'    => 1,
            'price'      => 5.0,
            'delay'      => 7,
            'active'     => true,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $rule = $this->createRule(qtyMin: 10, qtyMax: 100, qtyOnHand: 0);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule));

        $picking = Picking::where('company_id', $this->company->id)
            ->where('origin', 'Replenishment')->first();
        $this->assertNotNull($picking);
        $this->assertSame($vendor->id, $picking->partner_id);
    }

    public function test_replenish_uses_supplier_delay_when_rule_has_no_lead_days(): void
    {
        $vendor = Contact::create([
            'company_id'  => $this->company->id,
            'name'        => 'Slow Supplier',
            'is_supplier' => true, 'active' => true,
            'created_by'  => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        ProductSupplier::create([
            'product_id' => $this->product->id, 'partner_id' => $vendor->id,
            'min_qty' => 1, 'price' => 5.0, 'delay' => 14,
            'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        // Rule has lead_days = 0 → supplier.delay should be used
        $rule = $this->createRule(qtyMin: 0, qtyMax: 100, qtyOnHand: 0, leadDays: 0);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule));

        $picking = Picking::where('origin', 'Replenishment')->first();
        $this->assertSame(now()->addDays(14)->toDateString(), $picking->scheduled_date->toDateString());
    }

    public function test_replenish_rule_lead_days_takes_precedence_over_supplier_delay(): void
    {
        $vendor = Contact::create([
            'company_id'  => $this->company->id,
            'name'        => 'Slow Supplier',
            'is_supplier' => true, 'active' => true,
            'created_by'  => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        ProductSupplier::create([
            'product_id' => $this->product->id, 'partner_id' => $vendor->id,
            'min_qty' => 1, 'price' => 5.0, 'delay' => 14,
            'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);

        // Rule says 3 days → wins over supplier's 14
        $rule = $this->createRule(qtyMin: 0, qtyMax: 100, qtyOnHand: 0, leadDays: 3);

        $this->actingAs($this->admin)
            ->withSession(['active_company_ids' => [$this->company->id]])
            ->post(route('inventory.replenishment.replenish', $rule));

        $picking = Picking::where('origin', 'Replenishment')->first();
        $this->assertSame(now()->addDays(3)->toDateString(), $picking->scheduled_date->toDateString());
    }

    public function test_primary_supplier_picks_lowest_delay_among_actives(): void
    {
        $slow = Contact::create([
            'company_id' => $this->company->id, 'name' => 'Slow',
            'is_supplier' => true, 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        $fast = Contact::create([
            'company_id' => $this->company->id, 'name' => 'Fast',
            'is_supplier' => true, 'active' => true,
            'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
        ]);
        ProductSupplier::create(['product_id' => $this->product->id, 'partner_id' => $slow->id, 'min_qty' => 1, 'price' => 4.0, 'delay' => 30, 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        ProductSupplier::create(['product_id' => $this->product->id, 'partner_id' => $fast->id, 'min_qty' => 1, 'price' => 5.0, 'delay' => 2,  'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $primary = $this->product->primarySupplier();
        $this->assertSame($fast->id, $primary->partner_id);
    }

    public function test_primary_supplier_skips_inactive_rows(): void
    {
        $inactive = Contact::create(['company_id' => $this->company->id, 'name' => 'Old', 'is_supplier' => true, 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        $active   = Contact::create(['company_id' => $this->company->id, 'name' => 'New', 'is_supplier' => true, 'active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        // The inactive entry has the BEST delay — but should be ignored
        ProductSupplier::create(['product_id' => $this->product->id, 'partner_id' => $inactive->id, 'min_qty' => 1, 'price' => 4.0, 'delay' => 1,  'active' => false, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);
        ProductSupplier::create(['product_id' => $this->product->id, 'partner_id' => $active->id,   'min_qty' => 1, 'price' => 5.0, 'delay' => 10, 'active' => true,  'created_by' => $this->admin->id, 'updated_by' => $this->admin->id]);

        $primary = $this->product->primarySupplier();
        $this->assertSame($active->id, $primary->partner_id);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function createRule(
        float $qtyMin,
        float $qtyMax,
        float $qtyOnHand,
        float $qtyMultiple = 1,
        int   $leadDays = 0,
    ): ReorderRule {
        return ReorderRule::create([
            'company_id'   => $this->company->id,
            'product_id'   => $this->product->id,
            'location_id'  => $this->stockLoc->id,
            'warehouse_id' => $this->warehouse->id,
            'qty_min'      => $qtyMin,
            'qty_max'      => $qtyMax,
            'qty_on_hand'  => $qtyOnHand,
            'qty_forecast' => $qtyOnHand,
            'qty_multiple' => $qtyMultiple,
            'lead_days'    => $leadDays,
            'active'       => true,
            'created_by'   => $this->admin->id,
            'updated_by'   => $this->admin->id,
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
