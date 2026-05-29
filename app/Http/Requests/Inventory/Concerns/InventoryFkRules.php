<?php

namespace App\Http\Requests\Inventory\Concerns;

use App\Models\Inventory\Product;
use App\Models\Inventory\Uom;
use Closure;
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

    /**
     * Closure rule: the chosen UoM (`$value`) must belong to the SAME UoM
     * category as the product picked on the same row. `$productKey` is the
     * dotted input path of the sibling product field — for a flat request
     * ("product_id" / "uom_id" siblings) pass `'product_id'`; for an array
     * of moves ("moves.0.uom_id" matched to "moves.0.product_id") pass
     * `'product_id'` and we derive the row prefix from `$attribute`.
     *
     * Odoo parity: you can transfer a product in any UoM **within the same
     * category** (kg ↔ g, doz ↔ unit), but never across categories (kg → cm).
     * Without this check, `PickingService::validate()` would call
     * `Uom::convertQty()` with mismatched categories — the model now throws
     * there too as a defense-in-depth, but the form layer should reject the
     * input before it ever reaches the service so the user sees a clean
     * field-level error instead of a generic 500.
     */
    protected function uomMatchingProductCategoryRule(string $productKey = 'product_id'): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($productKey) {
            if (!$value) return;

            // Derive sibling product field. If $attribute is "moves.0.uom_id"
            // and $productKey is "product_id", the sibling is "moves.0.product_id".
            $parts = explode('.', $attribute);
            array_pop($parts);
            $siblingPath = empty($parts) ? $productKey : implode('.', $parts) . '.' . $productKey;
            $productId   = request($siblingPath);
            if (!$productId) return;

            $product = Product::with('uom')->find($productId);
            $uom     = Uom::find($value);
            if (!$product || !$uom) return;
            if (!$product->uom) return; // misconfigured product — caught elsewhere

            if (!$uom->isSameCategoryAs($product->uom)) {
                $fail(__('inventory.err_uom_category_mismatch', [
                    'from' => $uom->name,
                    'to'   => $product->uom->name,
                ]));
            }
        };
    }
}
