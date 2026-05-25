<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class ProductCategory extends Model
{
    protected $table = 'inventory_product_categories';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'name'             => 'Name',
        'removal_strategy' => 'Removal Strategy',
        'costing_method'   => 'Costing Method',
    ];

    public array $sortable = [
        'name'        => 'name',
        'complete_name' => 'complete_name',
        'created_at'  => 'created_at',
    ];

    public array $searchable = [
        'name'          => ['label' => 'Name',       'column' => 'name',          'type' => 'string'],
        'complete_name' => ['label' => 'Full Path',  'column' => 'complete_name', 'type' => 'string'],
        'active'        => ['label' => 'Active',     'column' => 'active',        'type' => 'boolean'],
        'created_at'    => ['label' => 'Created on', 'column' => 'created_at',    'type' => 'datetime'],
    ];

    protected $fillable = [
        'parent_id', 'name', 'complete_name', 'removal_strategy', 'costing_method', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function parent(): BelongsTo  { return $this->belongsTo(ProductCategory::class, 'parent_id'); }
    public function children(): HasMany  { return $this->hasMany(ProductCategory::class, 'parent_id'); }
    public function products(): HasMany  { return $this->hasMany(Product::class, 'category_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }

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

    // R5 / Rule 12: views must not render raw enum DB values. Map each
    // stored value to a human label; views call $cat->removal_strategy_label
    // and $cat->costing_method_label instead of ucwords/str_replace hacks.
    public const REMOVAL_STRATEGIES = [
        'fifo'             => 'FIFO (First In, First Out)',
        'lifo'             => 'LIFO (Last In, First Out)',
        'fefo'             => 'FEFO (First Expired, First Out)',
        'closest_location' => 'Closest Location',
    ];

    public const COSTING_METHODS = [
        'standard_price' => 'Standard Price',
        'average_cost'   => 'Average Cost (AVCO)',
        'fifo'           => 'First In, First Out (FIFO)',
    ];

    public function getRemovalStrategyLabelAttribute(): string
    {
        return self::REMOVAL_STRATEGIES[$this->removal_strategy] ?? $this->removal_strategy;
    }

    public function getCostingMethodLabelAttribute(): string
    {
        return self::COSTING_METHODS[$this->costing_method] ?? $this->costing_method;
    }
}
