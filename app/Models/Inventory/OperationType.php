<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class OperationType extends Model
{
    protected $table = 'inventory_operation_types';

    use HasChatter, SoftDeletes;

    public array $chatterTracked = ['name' => 'Name', 'code' => 'Code'];

    public array $sortable = [
        'name'       => 'name',
        'code'       => 'code',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'   => ['label' => 'Name',   'column' => 'name',   'type' => 'string'],
        'code'   => ['label' => 'Code',   'column' => 'code',   'type' => 'string'],
        'active' => ['label' => 'Active', 'column' => 'active', 'type' => 'boolean'],
    ];

    // `show_entire_packs` is intentionally absent from $fillable + $casts:
    // the column exists in the schema but no form, validation, or service
    // code ever sets or reads it (it would gate a "show whole-pack moves"
    // UI feature that wasn't built). The column stays in the DB for
    // forward-compat with that future feature.
    protected $fillable = [
        'company_id', 'warehouse_id', 'default_location_src_id', 'default_location_dest_id',
        'return_picking_type_id', 'name', 'code', 'use_existing_lots', 'use_create_lots',
        'sequence_prefix', 'sequence_next_number', 'sequence_padding',
        'active',
    ];

    protected $casts = [
        'use_existing_lots' => 'boolean',
        'use_create_lots'   => 'boolean',
        'active'            => 'boolean',
    ];

    public const CODE_LABELS = [
        'incoming' => 'Receipt',
        'outgoing' => 'Delivery',
        'internal' => 'Internal Transfer',
    ];

    public function company(): BelongsTo        { return $this->belongsTo(Company::class); }
    public function warehouse(): BelongsTo       { return $this->belongsTo(Warehouse::class); }
    public function defaultSrcLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'default_location_src_id'); }
    public function defaultDestLocation(): BelongsTo { return $this->belongsTo(Location::class, 'default_location_dest_id'); }
    public function returnPickingType(): BelongsTo   { return $this->belongsTo(OperationType::class, 'return_picking_type_id'); }
    public function pickings(): HasMany          { return $this->hasMany(Picking::class, 'operation_type_id'); }
    public function creator(): BelongsTo         { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo         { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }
    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function getCodeLabelAttribute(): string
    {
        return self::CODE_LABELS[$this->code] ?? $this->code;
    }

    public function nextSequenceName(): string
    {
        $num = str_pad($this->sequence_next_number, $this->sequence_padding, '0', STR_PAD_LEFT);
        return $this->sequence_prefix . $num;
    }

    public function incrementSequence(): void
    {
        $this->increment('sequence_next_number');
    }

    /**
     * Atomically reserve the next sequence name and bump the counter in a
     * single locked read-modify-write. Without this, two concurrent picking
     * creates can both read sequence_next_number=N, both produce name "WH/IN/N",
     * and the second INSERT collides with the UNIQUE(company_id, name) index
     * (random user-facing 500s). Caller must already be inside a transaction.
     */
    public function reserveNextSequenceName(): string
    {
        $locked = self::whereKey($this->id)->lockForUpdate()->firstOrFail();
        $padding = max(1, (int) $locked->sequence_padding);
        $num     = str_pad((string) $locked->sequence_next_number, $padding, '0', STR_PAD_LEFT);
        $name    = $locked->sequence_prefix . $num;
        $locked->update(['sequence_next_number' => $locked->sequence_next_number + 1]);
        return $name;
    }
}
