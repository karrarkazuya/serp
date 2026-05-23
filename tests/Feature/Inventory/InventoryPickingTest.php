<?php

namespace Tests\Feature\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Picking;
use App\Models\Inventory\Product;
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
    }

    public function test_confirming_already_confirmed_picking_is_idempotent(): void
    {
        $picking = $this->createPicking();
        $picking = $this->pickingService->confirm($picking);
        $picking = $this->pickingService->confirm($picking); // second call

        $this->assertTrue($picking->isConfirmed());
    }

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

        // No stock → state stays confirmed (not assigned)
        $this->assertFalse($picking->isAssigned());
    }

    public function test_validate_deducts_stock_and_marks_done(): void
    {
        $this->seedStock(100);

        $picking = $this->createPicking(qty: 25);
        $this->pickingService->confirm($picking);
        $this->pickingService->checkAvailability($picking);
        $picking = $this->pickingService->validate($picking->fresh());

        $this->assertTrue($picking->isDone());
        $this->assertNotNull($picking->date_done);

        // Quant at src should have decreased by 25
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
        $picking = $this->pickingService->validate($picking->fresh());

        $this->assertTrue($picking->isDone());
        $destQuant = Quant::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('location_id', $this->destLocation->id)
            ->first();
        $this->assertSame(20.0, (float) $destQuant->quantity);
    }

    public function test_picking_can_be_cancelled(): void
    {
        $picking = $this->createPicking();
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->cancel($picking->fresh());

        $this->assertTrue($picking->isCancelled());
    }

    public function test_done_picking_cannot_be_cancelled(): void
    {
        $this->seedStock(10);

        $picking = $this->createPicking(qty: 5);
        $this->pickingService->confirm($picking);
        $picking = $this->pickingService->validate($picking->fresh());

        $this->expectException(\RuntimeException::class);
        $this->pickingService->cancel($picking);
    }

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
