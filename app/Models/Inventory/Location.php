<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    protected $table = 'inventory_locations';

    use HasChatter;

    public array $chatterTracked = [
        'name'  => 'Name',
        'usage' => 'Location Type',
    ];

    public array $sortable = [
        'name'          => 'name',
        'complete_name' => 'complete_name',
        'usage'         => 'usage',
        'created_at'    => 'created_at',
    ];

    public array $searchable = [
        'name'          => ['label' => 'Name',          'column' => 'name',          'type' => 'string'],
        'complete_name' => ['label' => 'Full Path',     'column' => 'complete_name', 'type' => 'string'],
        'usage'         => ['label' => 'Location Type', 'column' => 'usage',         'type' => 'string'],
        'active'        => ['label' => 'Active',        'column' => 'active',        'type' => 'boolean'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'warehouse_id', 'parent_id',
        'name', 'complete_name', 'usage', 'removal_strategy',
        'scrap_location', 'return_location', 'barcode', 'notes',
        'posx', 'posy', 'posz', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'scrap_location'  => 'boolean',
        'return_location' => 'boolean',
        'active'          => 'boolean',
    ];

    // Usage labels
    public const USAGE_LABELS = [
        'supplier'  => 'Vendor Location',
        'view'      => 'View',
        'internal'  => 'Internal',
        'customer'  => 'Customer Location',
        'inventory' => 'Inventory Adjustments',
        'production' => 'Production',
        'transit'   => 'Transit',
    ];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function parent(): BelongsTo   { return $this->belongsTo(Location::class, 'parent_id'); }
    public function children(): HasMany   { return $this->hasMany(Location::class, 'parent_id'); }
    public function quants(): HasMany     { return $this->hasMany(Quant::class, 'location_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo  { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }
    public function scopeInternal(Builder $q): Builder { return $q->where('usage', 'internal'); }
    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        if (empty($ids)) return $q->whereNull('company_id');
        return $q->where(fn($sub) => $sub->whereNull('company_id')->orWhereIn('company_id', $ids));
    }

    public function updateCompleteName(): void
    {
        $parts = [$this->name];
        $parent = $this->parent;
        while ($parent) {
            array_unshift($parts, $parent->name);
            $parent = $parent->parent;
        }
        $this->updateQuietly(['complete_name' => implode(' / ', $parts)]);
    }

    public function getUsageLabelAttribute(): string
    {
        return self::USAGE_LABELS[$this->usage] ?? $this->usage;
    }

    public function isInternal(): bool    { return $this->usage === 'internal'; }
    public function isVirtual(): bool     { return in_array($this->usage, ['inventory', 'production', 'transit']); }
    public function isView(): bool        { return $this->usage === 'view'; }
}
