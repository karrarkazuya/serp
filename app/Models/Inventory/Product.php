<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    protected $table = 'inventory_products';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'name'               => 'Name',
        'product_type'       => 'Product Type',
        'tracking'           => 'Tracking',
        'cost'               => 'Cost',
        'sale_price'         => 'Sale Price',
        'category_id'        => ['label' => 'Category', 'table' => 'inventory_product_categories', 'column' => 'name'],
        'uom_id'             => ['label' => 'Unit of Measure', 'table' => 'inventory_uoms', 'column' => 'name'],
        'internal_reference' => 'Internal Reference',
    ];

    public array $sortable = [
        'name'               => 'name',
        'internal_reference' => 'internal_reference',
        'product_type'       => 'product_type',
        'cost'               => 'cost',
        'sale_price'         => 'sale_price',
        'created_at'         => 'created_at',
    ];

    public array $searchable = [
        'name'               => ['label' => 'Name',               'column' => 'name',               'type' => 'string'],
        'internal_reference' => ['label' => 'Internal Reference', 'column' => 'internal_reference', 'type' => 'string'],
        'barcode'            => ['label' => 'Barcode',            'column' => 'barcode',            'type' => 'string'],
        'product_type'       => ['label' => 'Product Type',       'column' => 'product_type',       'type' => 'string'],
        'tracking'           => ['label' => 'Tracking',           'column' => 'tracking',           'type' => 'string'],
        'active'             => ['label' => 'Active',             'column' => 'active',             'type' => 'boolean'],
        'created_at'         => ['label' => 'Created on',         'column' => 'created_at',         'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id', 'category_id', 'uom_id', 'uom_po_id',
        'name', 'internal_reference', 'barcode', 'description', 'description_picking',
        'product_type', 'tracking', 'has_expiration_date',
        'cost', 'sale_price', 'weight', 'volume', 'image_uuid', 'active',
    ];

    protected $casts = [
        'cost'                => 'decimal:4',
        'sale_price'          => 'decimal:4',
        'weight'              => 'decimal:4',
        'volume'              => 'decimal:4',
        'has_expiration_date' => 'boolean',
        'active'              => 'boolean',
    ];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function category(): BelongsTo   { return $this->belongsTo(ProductCategory::class, 'category_id'); }
    public function uom(): BelongsTo        { return $this->belongsTo(Uom::class, 'uom_id'); }
    public function uomPo(): BelongsTo      { return $this->belongsTo(Uom::class, 'uom_po_id'); }
    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo    { return $this->belongsTo(User::class, 'updated_by'); }
    public function suppliers(): HasMany    { return $this->hasMany(ProductSupplier::class, 'product_id'); }
    public function lots(): HasMany         { return $this->hasMany(Lot::class, 'product_id'); }
    public function quants(): HasMany       { return $this->hasMany(Quant::class, 'product_id'); }

    public function routes(): BelongsToMany
    {
        return $this->belongsToMany(Route::class, 'inventory_product_routes', 'product_id', 'route_id');
    }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }

    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function isStorable(): bool    { return $this->product_type === 'storable'; }
    public function isConsumable(): bool  { return $this->product_type === 'consumable'; }
    public function isService(): bool     { return $this->product_type === 'service'; }
    public function isTrackedByLot(): bool    { return $this->tracking === 'lot'; }
    public function isTrackedBySerial(): bool { return $this->tracking === 'serial'; }
    public function requiresLotTracking(): bool { return $this->tracking !== 'none'; }

    // R5 / Rule 12: views must not render raw enum DB values via ucfirst.
    public const TYPE_LABELS = [
        'storable'   => 'Storable Product',
        'consumable' => 'Consumable',
        'service'    => 'Service',
    ];

    public const TRACKING_LABELS = [
        'none'   => 'No tracking',
        'lot'    => 'By Lot',
        'serial' => 'By Serial Number',
    ];

    public function getTypeLabelAttribute(): string
    {
        return self::TYPE_LABELS[$this->product_type] ?? $this->product_type;
    }

    public function getTrackingLabelAttribute(): string
    {
        return self::TRACKING_LABELS[$this->tracking] ?? $this->tracking;
    }

    public function getOnHandQty(): float
    {
        return (float) $this->quants()
            ->whereHas('location', fn($q) => $q->where('usage', 'internal'))
            ->sum('quantity');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image_uuid ? route('files.serve', $this->image_uuid) : null;
    }

    /**
     * Odoo parity: the "primary" purchase vendor — used by the replenishment
     * flow to stamp the receipt's partner_id and to derive lead time from
     * `supplier.delay` when the reorder rule has none of its own. Picks the
     * active supplier with the lowest `delay` (fastest fulfilment), then by
     * lowest `min_qty` (cheapest minimum order) as a tiebreaker, then by
     * insertion order. Returns null if no active supplier matches.
     *
     * `$companyId` (optional) scopes the choice to suppliers whose partner
     * contact lives in that company — Contacts are company-scoped, and a
     * multi-company user could have wired a cross-tenant partner via the
     * form. Without this filter, replenishing a Company-A product whose
     * primary supplier points at a Company-B contact would stamp the B
     * contact onto an A picking, breaking tenant isolation downstream
     * (audit feed, chatter, supplier lookups). Suppliers whose `partner_id`
     * is null (free-text vendor only) are also excluded when a company is
     * passed, because we can't verify their tenancy.
     */
    public function primarySupplier(?int $companyId = null): ?ProductSupplier
    {
        $query = $this->suppliers()
            ->where('active', true);

        if ($companyId !== null) {
            $query->whereHas('partner', fn ($q) => $q->where('company_id', $companyId));
        }

        return $query
            ->orderBy('delay')
            ->orderBy('min_qty')
            ->orderBy('id')
            ->first();
    }
}
