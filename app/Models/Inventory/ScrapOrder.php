<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class ScrapOrder extends Model
{
    protected $table = 'inventory_scrap_orders';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'state'             => 'State',
        'scrap_qty'         => 'Quantity',
        'product_id'        => ['label' => 'Product',          'table' => 'inventory_products',  'column' => 'name'],
        'location_id'       => ['label' => 'Source Location',  'table' => 'inventory_locations', 'column' => 'complete_name'],
        'scrap_location_id' => ['label' => 'Scrap Location',   'table' => 'inventory_locations', 'column' => 'complete_name'],
        'lot_id'            => ['label' => 'Lot/Serial',       'table' => 'inventory_lots',      'column' => 'name'],
    ];

    public array $sortable = [
        'name'       => 'name',
        'state'      => 'state',
        'date_done'  => 'date_done',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Reference',  'column' => 'name',       'type' => 'string'],
        'origin'     => ['label' => 'Source',     'column' => 'origin',     'type' => 'string'],
        'state'      => ['label' => 'State',      'column' => 'state',      'options' => self::STATE_LABELS],
        'date_done'  => ['label' => 'Date Done',  'column' => 'date_done',  'type' => 'date'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id', 'product_id', 'uom_id', 'location_id', 'scrap_location_id',
        'lot_id', 'picking_id', 'move_id', 'name', 'scrap_qty', 'state', 'origin', 'date_done',
    ];

    protected $casts = [
        'scrap_qty' => 'decimal:4',
        'date_done' => 'date',
    ];

    public function company(): BelongsTo        { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo        { return $this->belongsTo(Product::class, 'product_id'); }
    public function uom(): BelongsTo            { return $this->belongsTo(Uom::class, 'uom_id'); }
    public function location(): BelongsTo       { return $this->belongsTo(Location::class, 'location_id'); }
    public function scrapLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'scrap_location_id'); }
    public function lot(): BelongsTo            { return $this->belongsTo(Lot::class, 'lot_id'); }
    public function picking(): BelongsTo        { return $this->belongsTo(Picking::class, 'picking_id'); }
    public function move(): BelongsTo           { return $this->belongsTo(Move::class, 'move_id'); }
    public function creator(): BelongsTo        { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo        { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function isDone(): bool  { return $this->state === 'done'; }
    public function isDraft(): bool { return $this->state === 'draft'; }

    public function getStateColorAttribute(): string
    {
        return $this->state === 'done' ? 'green' : 'gray';
    }

    // R5 / Rule 12: views must not render raw state values via ucfirst().
    // Maps the stored state to a proper human label.
    public const STATE_LABELS = [
        'draft' => 'Draft',
        'done'  => 'Done',
    ];

    public function getStateLabelAttribute(): string
    {
        return self::STATE_LABELS[$this->state] ?? ucfirst($this->state);
    }
}
