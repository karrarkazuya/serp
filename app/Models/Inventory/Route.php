<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    protected $table = 'inventory_routes';

    use HasChatter;

    public array $chatterTracked = [
        'name'     => 'Name',
        'sequence' => 'Sequence',
    ];

    public array $sortable = ['name' => 'name', 'sequence' => 'sequence'];
    public array $searchable = [
        'name'       => ['label' => 'Name',       'column' => 'name',       'type' => 'string'],
        'active'     => ['label' => 'Active',     'column' => 'active',     'type' => 'boolean'],
        'created_at' => ['label' => 'Created on', 'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'supplied_wh_id', 'supplier_wh_id', 'name', 'sequence',
        'product_category_selectable', 'product_selectable', 'warehouse_selectable', 'active',
        'created_by', 'updated_by',
    ];

    protected $casts = [
        'product_category_selectable' => 'boolean',
        'product_selectable'          => 'boolean',
        'warehouse_selectable'        => 'boolean',
        'active'                      => 'boolean',
    ];

    public function company(): BelongsTo    { return $this->belongsTo(Company::class); }
    public function rules(): HasMany        { return $this->hasMany(RouteRule::class, 'route_id'); }
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'inventory_product_routes', 'route_id', 'product_id');
    }
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ProductCategory::class, 'inventory_category_routes', 'route_id', 'category_id');
    }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder                        { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder                      { return $q->where('active', false); }
    public function scopeForCompanies(Builder $q, array $ids): Builder      { return $q->whereIn('company_id', $ids); }
}
