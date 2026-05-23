<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class InventoryAdjustmentLine extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_adjustment_lines';

    protected $fillable = [
        'uuid', 'company_id', 'adjustment_id', 'product_id', 'location_id', 'lot_id',
        'inventory_qty', 'theoretical_qty', 'difference_qty', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'inventory_qty'   => 'decimal:4',
        'theoretical_qty' => 'decimal:4',
        'difference_qty'  => 'decimal:4',
    ];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function adjustment(): BelongsTo { return $this->belongsTo(InventoryAdjustment::class, 'adjustment_id'); }
    public function product(): BelongsTo    { return $this->belongsTo(Product::class, 'product_id'); }
    public function location(): BelongsTo   { return $this->belongsTo(Location::class, 'location_id'); }
    public function lot(): BelongsTo        { return $this->belongsTo(Lot::class, 'lot_id'); }
    public function creator(): BelongsTo    { return $this->belongsTo(User::class, 'created_by'); }

    public function getDifferenceQtyAttribute($val): float  { return (float) $val; }
    public function getInventoryQtyAttribute($val): float   { return (float) $val; }
    public function getTheoreticalQtyAttribute($val): float { return (float) $val; }
}
