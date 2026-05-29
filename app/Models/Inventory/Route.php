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

use Illuminate\Database\Eloquent\SoftDeletes;

class Route extends Model
{
    protected $table = 'inventory_routes';

    use HasChatter, SoftDeletes;

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

    // `supplied_wh_id` / `supplier_wh_id` are intentionally absent from
    // $fillable: the columns exist on the schema (inter-warehouse procurement
    // routing — "Route X stocks WH A from WH B's stock") but no engine queries
    // by them and no form exposes them to users. The chain engine matches
    // RouteRules by location, not by warehouse FK on the parent Route. Same
    // for the `*_selectable` flags: they would gate visibility on the
    // Product / Category / Warehouse forms in Odoo's selector but no S-ERP
    // form reads them either. Listing any of these in $fillable invited
    // dead writes from the RouteController. Columns stay in the DB for a
    // future procurement pipeline.
    protected $fillable = [
        'company_id', 'name', 'sequence', 'active',
    ];

    protected $casts = [
        'active' => 'boolean',
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
