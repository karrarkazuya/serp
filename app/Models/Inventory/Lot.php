<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lot extends Model
{
    protected $table = 'inventory_lots';

    use HasChatter;

    public array $chatterTracked = [
        'name'            => 'Lot/Serial Number',
        'ref'             => 'Reference',
        'expiration_date' => 'Expiration Date',
        'use_date'        => 'Best Before Date',
    ];

    public array $sortable = [
        'name'            => 'name',
        'product'         => 'product_id',
        'expiration_date' => 'expiration_date',
        'created_at'      => 'created_at',
    ];

    public array $searchable = [
        'name'    => ['label' => 'Lot/Serial',  'column' => 'name',    'type' => 'string'],
        'ref'     => ['label' => 'Reference',   'column' => 'ref',     'type' => 'string'],
        'active'  => ['label' => 'Active',      'column' => 'active',  'type' => 'boolean'],
        'expiration_date' => ['label' => 'Expiration Date', 'column' => 'expiration_date', 'type' => 'date'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'product_id', 'name', 'ref',
        'expiration_date', 'use_date', 'removal_date', 'note', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'expiration_date' => 'date',
        'use_date'        => 'date',
        'removal_date'    => 'date',
        'active'          => 'boolean',
    ];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function product(): BelongsTo  { return $this->belongsTo(Product::class, 'product_id'); }
    public function quants(): HasMany     { return $this->hasMany(Quant::class, 'lot_id'); }
    public function moveLines(): HasMany  { return $this->hasMany(MoveLine::class, 'lot_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo  { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }
    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function isExpired(): bool
    {
        return $this->expiration_date && $this->expiration_date->isPast();
    }

    public function getOnHandQty(): float
    {
        return (float) $this->quants()
            ->whereHas('location', fn($q) => $q->where('usage', 'internal'))
            ->sum('quantity');
    }
}
