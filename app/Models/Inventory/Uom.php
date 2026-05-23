<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Uom extends Model
{
    protected $table = 'inventory_uoms';

    use HasChatter;

    public array $chatterTracked = [
        'name'     => 'Name',
        'symbol'   => 'Symbol',
        'ratio'    => 'Ratio',
        'uom_type' => 'Type',
        'active'   => 'Active',
    ];

    public array $sortable = [
        'name'     => 'name',
        'category' => 'uom_category_id',
    ];

    public array $searchable = [
        'name'     => ['label' => 'Name',     'column' => 'name',     'type' => 'string'],
        'symbol'   => ['label' => 'Symbol',   'column' => 'symbol',   'type' => 'string'],
        'active'   => ['label' => 'Active',   'column' => 'active',   'type' => 'boolean'],
    ];

    protected $fillable = [
        'uuid', 'uom_category_id', 'name', 'symbol', 'ratio', 'rounding', 'uom_type', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'ratio'    => 'decimal:6',
        'rounding' => 'decimal:6',
        'active'   => 'boolean',
    ];

    public function category(): BelongsTo { return $this->belongsTo(UomCategory::class, 'uom_category_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo  { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }

    public function isReference(): bool { return $this->uom_type === 'reference'; }

    public function convertQty(float $qty, Uom $to): float
    {
        if ($this->id === $to->id) return $qty;
        $base = $qty / $this->ratio;
        return round($base * $to->ratio, 4);
    }
}
