<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationType extends Model
{
    protected $table = 'inventory_operation_types';

    use HasChatter;

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

    protected $fillable = [
        'uuid', 'company_id', 'warehouse_id', 'default_location_src_id', 'default_location_dest_id',
        'return_picking_type_id', 'name', 'code', 'use_existing_lots', 'use_create_lots',
        'show_entire_packs', 'sequence_prefix', 'sequence_next_number', 'sequence_padding',
        'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'use_existing_lots' => 'boolean',
        'use_create_lots'   => 'boolean',
        'show_entire_packs' => 'boolean',
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
}
