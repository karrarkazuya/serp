<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Move extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_moves';

    public array $sortable   = ['sequence' => 'sequence', 'name' => 'name'];
    public array $searchable = [
        'name'  => ['label' => 'Description', 'column' => 'name',  'type' => 'string'],
        'state' => ['label' => 'State',       'column' => 'state', 'type' => 'string'],
    ];

    protected $fillable = [
        'company_id', 'picking_id', 'product_id', 'uom_id', 'location_src_id', 'location_dest_id',
        'origin_returned_move_id', 'name', 'origin', 'product_qty', 'qty_done', 'reserved_qty',
        'state', 'sequence', 'date',
    ];

    protected $casts = [
        'product_qty'  => 'decimal:4',
        'qty_done'     => 'decimal:4',
        'reserved_qty' => 'decimal:4',
        'date'         => 'date',
    ];

    public function company(): BelongsTo      { return $this->belongsTo(Company::class); }
    public function picking(): BelongsTo      { return $this->belongsTo(Picking::class, 'picking_id'); }
    public function product(): BelongsTo      { return $this->belongsTo(Product::class, 'product_id'); }
    public function uom(): BelongsTo          { return $this->belongsTo(Uom::class, 'uom_id'); }
    public function srcLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'location_src_id'); }
    public function destLocation(): BelongsTo { return $this->belongsTo(Location::class, 'location_dest_id'); }
    public function originMove(): BelongsTo   { return $this->belongsTo(Move::class, 'origin_returned_move_id'); }
    public function moveLines(): HasMany      { return $this->hasMany(MoveLine::class, 'move_id'); }
    public function creator(): BelongsTo      { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function isDone(): bool      { return $this->state === 'done'; }
    public function isCancelled(): bool { return $this->state === 'cancelled'; }
    public function isDraft(): bool     { return $this->state === 'draft'; }

    public function getQtyDoneAttribute($val): float { return (float) $val; }
    public function getProductQtyAttribute($val): float { return (float) $val; }
}
