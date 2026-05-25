<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Location;
use App\Models\Inventory\OperationType;
use App\Models\Inventory\Warehouse;
use App\Services\Chatter\ChatterService;

class WarehouseService
{
    public function __construct(private readonly ChatterService $chatterService) {}

    public function create(array $data): Warehouse
    {
        $warehouse = Warehouse::create($data);
        $this->setupWarehouseLocationsAndTypes($warehouse);
        $this->chatterService->logCreated($warehouse, 'Warehouse');
        return $warehouse->fresh();
    }

    public function update(Warehouse $warehouse, array $data): Warehouse
    {
        $changes = $this->detectChanges($warehouse, $data);
        $warehouse->update($data);
        if (!empty($changes)) {
            $this->chatterService->logUpdated($warehouse, $changes, 'Warehouse');
        }
        return $warehouse->fresh();
    }

    public function archive(Warehouse $warehouse): Warehouse
    {
        $warehouse->update(['active' => false]);
        $this->chatterService->logArchived($warehouse, 'Warehouse');
        return $warehouse;
    }

    public function unarchive(Warehouse $warehouse): Warehouse
    {
        $warehouse->update(['active' => true]);
        $this->chatterService->logUnarchived($warehouse, 'Warehouse');
        return $warehouse;
    }

    public function delete(Warehouse $warehouse): void
    {
        $this->chatterService->log($warehouse, 'Warehouse deleted.', 'system');
        $warehouse->delete();
    }

    public function setupWarehouseLocationsAndTypes(Warehouse $warehouse): void
    {
        $short     = strtoupper($warehouse->short_name);
        $companyId = $warehouse->company_id;

        // Odoo parity: only create the intermediate locations that the
        // warehouse's reception/delivery step setting actually uses. A
        // one-step warehouse doesn't need Input/Output/Packing — creating
        // them anyway clutters the location tree and lets users pick
        // intermediate locations that the operation flow never touches.
        $needsInput  = $warehouse->reception_steps !== 'one_step';   // two/three step receipts
        $needsQc     = $warehouse->reception_steps === 'three_steps';
        $needsOutput = $warehouse->delivery_steps  !== 'one_step';   // two/three step delivery
        $needsPack   = $warehouse->delivery_steps  === 'three_steps';

        // Parent view location for the warehouse
        $viewLoc = Location::create([
            'company_id' => $companyId,
            'name'       => $short,
            'usage'      => 'view',
            'active'     => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // Main stock location — always present
        $stockLoc = Location::create([
            'company_id' => $companyId,
            'parent_id'  => $viewLoc->id,
            'name'       => 'Stock',
            'usage'      => 'internal',
            'active'     => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        // Input location (2-step + 3-step reception)
        $inputLoc = $needsInput ? Location::create([
            'company_id' => $companyId,
            'parent_id'  => $viewLoc->id,
            'name'       => 'Input',
            'usage'      => 'internal',
            'active'     => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]) : null;

        // Quality Control location (3-step reception only)
        $qcLoc = $needsQc ? Location::create([
            'company_id' => $companyId,
            'parent_id'  => $viewLoc->id,
            'name'       => 'Quality Control',
            'usage'      => 'internal',
            'active'     => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]) : null;

        // Output location (2-step + 3-step delivery)
        $outputLoc = $needsOutput ? Location::create([
            'company_id' => $companyId,
            'parent_id'  => $viewLoc->id,
            'name'       => 'Output',
            'usage'      => 'internal',
            'active'     => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]) : null;

        // Packing zone (3-step delivery only)
        $packLoc = $needsPack ? Location::create([
            'company_id' => $companyId,
            'parent_id'  => $viewLoc->id,
            'name'       => 'Packing Zone',
            'usage'      => 'internal',
            'active'     => true,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]) : null;

        // Get global virtual locations
        $supplierLoc  = Location::where('usage', 'supplier')->whereNull('company_id')->first();
        $customerLoc  = Location::where('usage', 'customer')->whereNull('company_id')->first();

        // Create operation types
        $receiptType = OperationType::create([
            'company_id'              => $companyId,
            'warehouse_id'            => $warehouse->id,
            'default_location_src_id' => $supplierLoc?->id,
            'default_location_dest_id' => $stockLoc->id,
            'name'                    => $short . ': Receipts',
            'code'                    => 'incoming',
            'use_create_lots'         => true,
            'use_existing_lots'       => true,
            'sequence_prefix'         => $short . '/IN/',
            'active'                  => true,
            'created_by'              => auth()->id(),
            'updated_by'              => auth()->id(),
        ]);

        $deliveryType = OperationType::create([
            'company_id'              => $companyId,
            'warehouse_id'            => $warehouse->id,
            'default_location_src_id' => $stockLoc->id,
            'default_location_dest_id' => $customerLoc?->id,
            'name'                    => $short . ': Delivery Orders',
            'code'                    => 'outgoing',
            'use_create_lots'         => false,
            'use_existing_lots'       => true,
            'sequence_prefix'         => $short . '/OUT/',
            'active'                  => true,
            'created_by'              => auth()->id(),
            'updated_by'              => auth()->id(),
        ]);

        $internalType = OperationType::create([
            'company_id'              => $companyId,
            'warehouse_id'            => $warehouse->id,
            'default_location_src_id' => $stockLoc->id,
            'default_location_dest_id' => $stockLoc->id,
            'name'                    => $short . ': Internal Transfers',
            'code'                    => 'internal',
            'use_create_lots'         => false,
            'use_existing_lots'       => true,
            'sequence_prefix'         => $short . '/INT/',
            'active'                  => true,
            'created_by'              => auth()->id(),
            'updated_by'              => auth()->id(),
        ]);

        // Set return types (internal transfers return to themselves)
        $receiptType->update(['return_picking_type_id' => $deliveryType->id]);
        $deliveryType->update(['return_picking_type_id' => $receiptType->id]);
        $internalType->update(['return_picking_type_id' => $internalType->id]);

        // Update warehouse with location references (null when the step
        // setting doesn't use that intermediate location).
        $warehouse->update([
            'view_location_id'       => $viewLoc->id,
            'lot_stock_id'           => $stockLoc->id,
            'wh_input_stock_loc_id'  => $inputLoc?->id,
            'wh_qc_stock_loc_id'     => $qcLoc?->id,
            'wh_output_stock_loc_id' => $outputLoc?->id,
            'wh_pack_stock_loc_id'   => $packLoc?->id,
        ]);

        foreach (array_filter([$viewLoc, $stockLoc, $inputLoc, $qcLoc, $outputLoc, $packLoc]) as $loc) {
            $loc->refresh();
            $loc->updateCompleteName();
        }
    }

    private function detectChanges(Warehouse $warehouse, array $data): array
    {
        $changes = [];
        foreach ($warehouse->chatterTracked as $field => $definition) {
            if (!array_key_exists($field, $data)) continue;
            $old = (string) ($warehouse->{$field} ?? '');
            $new = (string) ($data[$field] ?? '');
            if ($old === $new) continue;
            $changes[] = ['field' => $field, 'label' => is_array($definition) ? $definition['label'] : $definition, 'from' => $old ?: '—', 'to' => $new ?: '—'];
        }
        return $changes;
    }
}
