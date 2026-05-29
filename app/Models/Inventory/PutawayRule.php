<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class PutawayRule extends Model
{
    protected $table = 'inventory_putaway_rules';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'sequence'            => 'Sequence',
        'product_id'          => ['label' => 'Product',            'table' => 'inventory_products',           'column' => 'name'],
        'product_category_id' => ['label' => 'Product Category',   'table' => 'inventory_product_categories', 'column' => 'name'],
        'location_id'         => ['label' => 'Source Location',    'table' => 'inventory_locations',          'column' => 'complete_name'],
        'fixed_location_id'   => ['label' => 'Fixed Location',     'table' => 'inventory_locations',          'column' => 'complete_name'],
        'active'              => 'Active',
    ];

    public array $sortable = [
        'sequence' => 'sequence',
        'product'  => 'product_id',
    ];

    public array $searchable = [
        'active'     => ['label' => 'Active',     'column' => 'active',     'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id', 'location_id', 'fixed_location_id', 'product_id', 'product_category_id',
        'sequence', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo         { return $this->belongsTo(Company::class); }
    public function location(): BelongsTo        { return $this->belongsTo(Location::class, 'location_id'); }
    public function fixedLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'fixed_location_id'); }
    public function product(): BelongsTo         { return $this->belongsTo(Product::class, 'product_id'); }
    public function productCategory(): BelongsTo { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
    public function creator(): BelongsTo         { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $q): Builder                   { return $q->where('active', true); }
    public function scopeForCompanies(Builder $q, array $ids): Builder { return $q->whereIn('company_id', $ids); }

    /**
     * Odoo parity: resolve the putaway target location for receiving
     * `$product` at `$destLocation`. Returns the `fixed_location_id` to
     * use as the actual destination, or `null` if no rule applies (caller
     * keeps the original $destLocation).
     *
     * Match priority — most specific wins. Within a tier, lower `sequence`
     * wins (Odoo convention). Company scope: a rule with `company_id = null`
     * is global and matches any company; a company-specific rule must match
     * the picking's `$companyId`.
     *
     *   tier 1: product_id == $product->id            (product-specific)
     *   tier 2: product_category_id == $product->category_id  (category)
     *
     * Skips: deliveries (dest is non-internal — there's no shelf to refine
     * into), products with no category for tier 2, inactive rules.
     */
    public static function resolveFor(Product $product, Location $destLocation, ?int $companyId): ?Location
    {
        if ($destLocation->usage !== 'internal') return null;

        $query = static::query()
            ->active()
            ->where('location_id', $destLocation->id)
            ->with('fixedLocation');

        // Global rules (company_id null) always apply; company-scoped rules
        // only apply when the company matches. A picking with a null company
        // can only match global rules (defensive — current schema makes
        // picking.company_id required, but the contract stays clean).
        if ($companyId !== null) {
            $query->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')->orWhere('company_id', $companyId);
            });
        } else {
            $query->whereNull('company_id');
        }

        $rules = $query->orderBy('sequence')->get();
        if ($rules->isEmpty()) return null;

        $productMatch = $rules->firstWhere('product_id', $product->id);
        if ($productMatch && static::fixedLocationAcceptableFor($productMatch, $companyId)) {
            return $productMatch->fixedLocation;
        }

        if ($product->category_id) {
            $categoryMatch = $rules->firstWhere('product_category_id', $product->category_id);
            if ($categoryMatch && static::fixedLocationAcceptableFor($categoryMatch, $companyId)) {
                return $categoryMatch->fixedLocation;
            }
        }

        return null;
    }

    /**
     * Defense-in-depth: the rule's `fixed_location_id` must belong to the
     * picking's company or be shared (null company_id). A multi-company user
     * could have configured a rule whose fixed_location lives in another
     * tenant — at form time both companies were active, but at runtime the
     * picking may belong to only one of them. Silently redirecting into the
     * other tenant's location would cross-tenant leak stock.
     *
     * Also archived locations are excluded — receiving into an archived bin
     * would be invisible in the standard active-location UI.
     */
    private static function fixedLocationAcceptableFor(self $rule, ?int $companyId): bool
    {
        $fixed = $rule->fixedLocation;
        if (!$fixed || !$fixed->active) return false;

        // Shared location (null company) is always acceptable.
        if ($fixed->company_id === null) return true;

        return $companyId !== null && (int) $fixed->company_id === (int) $companyId;
    }
}
