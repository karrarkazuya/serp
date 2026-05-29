<?php

namespace App\Models\Inventory;

use App\Models\Contacts\Contact;
use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    protected $table = 'inventory_warehouses';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = [
        'name'             => 'Name',
        'short_name'       => 'Short Name',
        'reception_steps'  => 'Reception Steps',
        'delivery_steps'   => 'Delivery Steps',
    ];

    public array $sortable = [
        'name'       => 'name',
        'short_name' => 'short_name',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'             => ['label' => 'Name',             'column' => 'name',             'type' => 'string'],
        'short_name'       => ['label' => 'Short Name',       'column' => 'short_name',       'type' => 'string'],
        'reception_steps'  => ['label' => 'Reception Steps',  'column' => 'reception_steps',  'type' => 'string'],
        'delivery_steps'   => ['label' => 'Delivery Steps',   'column' => 'delivery_steps',   'type' => 'string'],
        'active'           => ['label' => 'Active',           'column' => 'active',           'type' => 'boolean'],
        'created_at'       => ['label' => 'Created on',       'column' => 'created_at',       'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id', 'partner_id', 'lot_stock_id',
        'wh_input_stock_loc_id', 'wh_qc_stock_loc_id', 'wh_output_stock_loc_id', 'wh_pack_stock_loc_id', 'view_location_id',
        'name', 'short_name', 'reception_steps', 'delivery_steps', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function company(): BelongsTo        { return $this->belongsTo(Company::class); }
    public function partner(): BelongsTo        { return $this->belongsTo(Contact::class, 'partner_id'); }
    public function stockLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'lot_stock_id'); }
    public function inputLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'wh_input_stock_loc_id'); }
    public function qcLocation(): BelongsTo     { return $this->belongsTo(Location::class, 'wh_qc_stock_loc_id'); }
    public function outputLocation(): BelongsTo { return $this->belongsTo(Location::class, 'wh_output_stock_loc_id'); }
    public function packLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'wh_pack_stock_loc_id'); }
    public function viewLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'view_location_id'); }
    public function operationTypes(): HasMany    { return $this->hasMany(OperationType::class, 'warehouse_id'); }
    public function locations(): HasMany         { return $this->hasMany(Location::class, 'warehouse_id'); }
    public function creator(): BelongsTo         { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo         { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }

    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

}
