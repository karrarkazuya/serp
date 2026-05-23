<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PutawayRule extends Model
{
    protected $table = 'inventory_putaway_rules';

    use HasChatter;

    public array $chatterTracked = [
        'sequence'            => 'Sequence',
        'product_id'          => ['label' => 'Product',            'table' => 'inventory_products',           'column' => 'name'],
        'product_category_id' => ['label' => 'Product Category',   'table' => 'inventory_product_categories', 'column' => 'name'],
        'location_id'         => ['label' => 'Source Location',    'table' => 'inventory_locations',          'column' => 'complete_name'],
        'fixed_location_id'   => ['label' => 'Fixed Location',     'table' => 'inventory_locations',          'column' => 'complete_name'],
        'active'              => 'Active',
    ];

    public array $sortable = [
        'sequence' => 'sequence',
        'product'  => 'product_id',
    ];

    public array $searchable = [
        'active'     => ['label' => 'Active',     'column' => 'active',     'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'location_id', 'fixed_location_id', 'product_id', 'product_category_id',
        'sequence', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo         { return $this->belongsTo(Company::class); }
    public function location(): BelongsTo        { return $this->belongsTo(Location::class, 'location_id'); }
    public function fixedLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'fixed_location_id'); }
    public function product(): BelongsTo         { return $this->belongsTo(Product::class, 'product_id'); }
    public function productCategory(): BelongsTo { return $this->belongsTo(ProductCategory::class, 'product_category_id'); }
    public function creator(): BelongsTo         { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $q): Builder                   { return $q->where('active', true); }
    public function scopeForCompanies(Builder $q, array $ids): Builder { return $q->whereIn('company_id', $ids); }
}
