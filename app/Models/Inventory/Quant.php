<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class Quant extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_quants';

    public array $sortable = [
        'product'   => 'product_id',
        'location'  => 'location_id',
        'quantity'  => 'quantity',
    ];

    public array $searchable = [
        'quantity' => ['label' => 'Quantity', 'column' => 'quantity', 'type' => 'decimal'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'product_id', 'location_id', 'lot_id',
        'quantity', 'reserved_quantity', 'in_date', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'quantity'          => 'decimal:4',
        'reserved_quantity' => 'decimal:4',
        'in_date'           => 'datetime',
    ];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class, 'product_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class, 'location_id'); }
    public function lot(): BelongsTo      { return $this->belongsTo(Lot::class, 'lot_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function getQuantityAttribute($val): float          { return (float) $val; }
    public function getReservedQuantityAttribute($val): float  { return (float) $val; }
    public function getAvailableQty(): float { return max(0, $this->quantity - $this->reserved_quantity); }
}
