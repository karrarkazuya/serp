<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductCategory extends Model
{
    protected $table = 'inventory_product_categories';

    use HasChatter;

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
        'uuid', 'parent_id', 'name', 'complete_name', 'removal_strategy', 'costing_method', 'active', 'created_by', 'updated_by',
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
}
