<?php

namespace App\Models\Inventory;

use App\Models\Settings\Company;
use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryAdjustment extends Model
{
    protected $table = 'inventory_adjustments';

    use HasChatter;

    public array $chatterTracked = [
        'name'  => 'Name',
        'state' => 'State',
        'date'  => 'Date',
        'note'  => 'Note',
    ];

    public array $sortable = [
        'name'       => 'name',
        'state'      => 'state',
        'date'       => 'date',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'   => ['label' => 'Reference', 'column' => 'name',   'type' => 'string'],
        'state'  => ['label' => 'State',     'column' => 'state',  'type' => 'string'],
        'date'   => ['label' => 'Date',      'column' => 'date',   'type' => 'date'],
        'active' => ['label' => 'Active',    'column' => 'active', 'type' => 'boolean'],
    ];

    protected $fillable = [
        'uuid', 'company_id', 'name', 'state', 'exhausted', 'date', 'note', 'active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'date'      => 'date',
        'exhausted' => 'boolean',
        'active'    => 'boolean',
    ];

    public function company(): BelongsTo  { return $this->belongsTo(Company::class); }
    public function lines(): HasMany      { return $this->hasMany(InventoryAdjustmentLine::class, 'adjustment_id'); }
    public function creator(): BelongsTo  { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo  { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }
    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function isDraft(): bool      { return $this->state === 'draft'; }
    public function isInProgress(): bool { return $this->state === 'in_progress'; }
    public function isDone(): bool       { return $this->state === 'done'; }

    public function getStateColorAttribute(): string
    {
        return match ($this->state) {
            'done'        => 'green',
            'in_progress' => 'blue',
            default       => 'gray',
        };
    }

    public function getStateLabelAttribute(): string
    {
        return match ($this->state) {
            'in_progress' => 'In Progress',
            'done'        => 'Done',
            default       => 'Draft',
        };
    }
}
