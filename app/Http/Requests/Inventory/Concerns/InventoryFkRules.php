<?php

namespace App\Http\Requests\Inventory\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Shared `exists(...)` rule builders that gate Inventory module FKs by the
 * actor's active companies. Inventory moves money/stock — every cross-tenant
 * FK is a real exploit (cross-tenant stock manipulation, false audit trails
 * for scrap/adjustment, rogue lots in dropdowns, etc.), so the gate must be
 * uniform across every request that accepts an inventory FK.
 *
 * Conventions:
 *   - Tables whose `company_id` is *nullable on purpose* (locations, sometimes
 *     products) pass `allowNull: true`. Rows with `company_id = null` are
 *     cross-company shared records (transit/supplier/customer locations,
 *     shared service products) and remain valid for everyone.
 *   - Empty $activeCompanyIds → deny all. This mirrors how list pages render
 *     nothing when the user has no allowed companies.
 */
trait InventoryFkRules
{
    protected function companyScopedExists(string $table, array $activeCompanyIds, bool $allowNull = false): Exists
    {
        return Rule::exists($table, 'id')->where(function ($q) use ($activeCompanyIds, $allowNull) {
            if (empty($activeCompanyIds)) {
                $q->whereRaw('1 = 0');
                return;
            }
            $q->whereIn('company_id', $activeCompanyIds);
            if ($allowNull) {
                $q->orWhereNull('company_id');
            }
        });
    }

    /**
     * Location FKs (`location_src_id`, `location_dest_id`, `scrap_location_id`,
     * `location_id`) — locations with `company_id = null` are intentionally
     * cross-company (supplier / customer / transit), so always allow null.
     */
    protected function inventoryLocationRule(array $activeCompanyIds): Exists
    {
        return $this->companyScopedExists('inventory_locations', $activeCompanyIds, allowNull: true);
    }

    /**
     * Product FKs — service products may have `company_id = null` (shared
     * services like "Consulting Hour"). Allow null.
     */
    protected function inventoryProductRule(array $activeCompanyIds): Exists
    {
        return $this->companyScopedExists('inventory_products', $activeCompanyIds, allowNull: true);
    }

    /**
     * Contact FKs (partner_id, supplier partner) — contacts always belong to
     * a single company, so no null allowance.
     */
    protected function contactInActiveCompaniesRule(array $activeCompanyIds): Exists
    {
        return Rule::exists('contacts', 'id')->where(function ($q) use ($activeCompanyIds) {
            empty($activeCompanyIds)
                ? $q->whereRaw('1 = 0')
                : $q->whereIn('company_id', $activeCompanyIds);
        });
    }
}
