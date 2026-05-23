<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class MoveLine extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_move_lines';

    protected $fillable = [
        'uuid', 'company_id', 'move_id', 'picking_id', 'product_id', 'uom_id',
        'location_id', 'location_dest_id', 'lot_id', 'lot_name',
        'reserved_qty', 'qty_done', 'date', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'reserved_qty' => 'decimal:4',
        'qty_done'     => 'decimal:4',
        'date'         => 'date',
    ];

    public function company(): BelongsTo      { return $this->belongsTo(Company::class); }
    public function move(): BelongsTo         { return $this->belongsTo(Move::class, 'move_id'); }
    public function picking(): BelongsTo      { return $this->belongsTo(Picking::class, 'picking_id'); }
    public function product(): BelongsTo      { return $this->belongsTo(Product::class, 'product_id'); }
    public function uom(): BelongsTo          { return $this->belongsTo(Uom::class, 'uom_id'); }
    public function location(): BelongsTo     { return $this->belongsTo(Location::class, 'location_id'); }
    public function destLocation(): BelongsTo { return $this->belongsTo(Location::class, 'location_dest_id'); }
    public function lot(): BelongsTo          { return $this->belongsTo(Lot::class, 'lot_id'); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }

    public function getQtyDoneAttribute($val): float { return (float) $val; }
}
