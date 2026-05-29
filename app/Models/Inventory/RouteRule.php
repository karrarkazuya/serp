<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class RouteRule extends Model
{
    use SoftDeletes;

    protected $table = 'inventory_route_rules';

    // `group_propagation_option` is intentionally absent from $fillable: the
    // column has a DB default of 'propagate' and no consumer code ever reads
    // it. Listing it in $fillable was an Odoo carry-over for procurement
    // groups, a concept this codebase doesn't model. Column kept in the DB
    // so the schema matches if procurement groups are ever wired in.
    protected $fillable = [
        'company_id', 'route_id', 'operation_type_id', 'location_src_id', 'location_dest_id',
        'name', 'action', 'sequence', 'delay', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    public function route(): BelongsTo         { return $this->belongsTo(Route::class, 'route_id'); }
    public function operationType(): BelongsTo { return $this->belongsTo(OperationType::class, 'operation_type_id'); }
    public function srcLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'location_src_id'); }
    public function destLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'location_dest_id'); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }

    public function scopeActive(Builder $q): Builder { return $q->where('active', true); }

    // R5 / Rule 12: views must not render raw enum DB values via ucfirst.
    public const ACTION_LABELS = [
        'pull'      => 'Pull From',
        'push'      => 'Push To',
        'pull_push' => 'Pull & Push',
    ];

    public function getActionLabelAttribute(): string
    {
        return self::ACTION_LABELS[$this->action] ?? $this->action;
    }
}
