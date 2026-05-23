<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReorderRule extends Model
{
    protected $table = 'inventory_reorder_rules';

    use HasChatter;

    public array $chatterTracked = [
        'product_id'   => ['label' => 'Product',  'table' => 'inventory_products',  'column' => 'name'],
        'location_id'  => ['label' => 'Location', 'table' => 'inventory_locations', 'column' => 'complete_name'],
        'qty_min'      => 'Min Qty',
        'qty_max'      => 'Max Qty',
        'qty_multiple' => 'Quantity Multiple',
        'lead_days'    => 'Lead Days',
        'active'       => 'Active',
    ];

    public array $sortable = [
        'product'     => 'product_id',
        'qty_min'     => 'qty_min',
        'qty_max'     => 'qty_max',
        'qty_on_hand' => 'qty_on_hand',
    ];

    public array $searchable = [
        'active'     => ['label' => 'Active',     'column' => 'active',     'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'product_id', 'location_id', 'warehouse_id', 'route_id',
        'qty_min', 'qty_max', 'qty_multiple', 'qty_on_hand', 'qty_forecast', 'lead_days',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'qty_min'      => 'decimal:4',
        'qty_max'      => 'decimal:4',
        'qty_multiple' => 'decimal:4',
        'qty_on_hand'  => 'decimal:4',
        'qty_forecast' => 'decimal:4',
        'active'       => 'boolean',
    ];

    public function company(): BelongsTo   { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class, 'product_id'); }
    public function location(): BelongsTo  { return $this->belongsTo(Location::class, 'location_id'); }
    public function warehouse(): BelongsTo { return $this->belongsTo(Warehouse::class, 'warehouse_id'); }
    public function route(): BelongsTo     { return $this->belongsTo(Route::class, 'route_id'); }
    public function creator(): BelongsTo   { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $q): Builder { return $q->where('active', true); }
    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function needsReplenishment(): bool
    {
        return (float) $this->qty_on_hand <= (float) $this->qty_min;
    }

    public function getReplenishQty(): float
    {
        $needed = (float) $this->qty_max - (float) $this->qty_on_hand;
        if ($this->qty_multiple > 0) {
            $needed = ceil($needed / $this->qty_multiple) * $this->qty_multiple;
        }
        return max(0, $needed);
    }
}
