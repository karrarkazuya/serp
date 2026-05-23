<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteRule extends Model
{
    protected $table = 'inventory_route_rules';

    protected $fillable = [
        'uuid', 'company_id', 'route_id', 'operation_type_id', 'location_src_id', 'location_dest_id',
        'name', 'action', 'sequence', 'delay', 'group_propagation_option', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = ['active' => 'boolean'];

    public function route(): BelongsTo         { return $this->belongsTo(Route::class, 'route_id'); }
    public function operationType(): BelongsTo { return $this->belongsTo(OperationType::class, 'operation_type_id'); }
    public function srcLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'location_src_id'); }
    public function destLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'location_dest_id'); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $q): Builder { return $q->where('active', true); }
}
