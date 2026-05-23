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

class Picking extends Model
{
    protected $table = 'inventory_pickings';

    use HasChatter, SoftDeletes;

    public const STATE_DRAFT      = 'draft';
    public const STATE_CONFIRMED  = 'confirmed';
    public const STATE_ASSIGNED   = 'assigned';
    public const STATE_DONE       = 'done';
    public const STATE_CANCELLED  = 'cancelled';

    public array $chatterTracked = [
        'state'          => 'State',
        'scheduled_date' => 'Scheduled Date',
        'partner_id'     => ['label' => 'Partner', 'table' => 'contacts', 'column' => 'name'],
    ];

    public array $sortable = [
        'name'           => 'name',
        'state'          => 'state',
        'scheduled_date' => 'scheduled_date',
        'date_done'      => 'date_done',
        'created_at'     => 'created_at',
    ];

    public array $searchable = [
        'name'   => ['label' => 'Reference', 'column' => 'name',   'type' => 'string'],
        'origin' => ['label' => 'Source',    'column' => 'origin', 'type' => 'string'],
        'state'  => ['label' => 'Status',    'column' => 'state',  'type' => 'string'],
        'scheduled_date' => ['label' => 'Scheduled Date', 'column' => 'scheduled_date', 'type' => 'datetime'],
        'created_at'     => ['label' => 'Created on',     'column' => 'created_at',     'type' => 'datetime'],
    ];

    protected $fillable = [
        'company_id', 'operation_type_id', 'partner_id', 'location_src_id', 'location_dest_id',
        'origin_picking_id', 'name', 'origin', 'note', 'state', 'scheduled_date', 'date_done', 'active',
    ];

    protected $casts = [
        'scheduled_date' => 'datetime',
        'date_done'      => 'datetime',
        'active'         => 'boolean',
    ];

    public function company(): BelongsTo       { return $this->belongsTo(Company::class); }
    public function operationType(): BelongsTo { return $this->belongsTo(OperationType::class, 'operation_type_id'); }
    public function partner(): BelongsTo       { return $this->belongsTo(Contact::class, 'partner_id'); }
    public function srcLocation(): BelongsTo   { return $this->belongsTo(Location::class, 'location_src_id'); }
    public function destLocation(): BelongsTo  { return $this->belongsTo(Location::class, 'location_dest_id'); }
    public function originPicking(): BelongsTo { return $this->belongsTo(Picking::class, 'origin_picking_id'); }
    public function moves(): HasMany           { return $this->hasMany(Move::class, 'picking_id')->orderBy('sequence'); }
    public function moveLines(): HasMany       { return $this->hasMany(MoveLine::class, 'picking_id'); }
    public function creator(): BelongsTo       { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo       { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder   { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }
    public function scopeForCompanies(Builder $q, array $ids): Builder
    {
        return empty($ids) ? $q->whereRaw('1=0') : $q->whereIn('company_id', $ids);
    }

    public function isDraft(): bool     { return $this->state === self::STATE_DRAFT; }
    public function isConfirmed(): bool { return $this->state === self::STATE_CONFIRMED; }
    public function isAssigned(): bool  { return $this->state === self::STATE_ASSIGNED; }
    public function isDone(): bool      { return $this->state === self::STATE_DONE; }
    public function isCancelled(): bool { return $this->state === self::STATE_CANCELLED; }
    public function canEdit(): bool     { return in_array($this->state, [self::STATE_DRAFT, self::STATE_CONFIRMED, self::STATE_ASSIGNED]); }
    public function canValidate(): bool { return in_array($this->state, [self::STATE_CONFIRMED, self::STATE_ASSIGNED]); }

    public function getStateColorAttribute(): string
    {
        return match ($this->state) {
            self::STATE_DONE      => 'green',
            self::STATE_ASSIGNED  => 'blue',
            self::STATE_CONFIRMED => 'orange',
            self::STATE_CANCELLED => 'red',
            default               => 'gray',
        };
    }

    public function getStateLabelAttribute(): string
    {
        return match ($this->state) {
            self::STATE_DRAFT      => 'Draft',
            self::STATE_CONFIRMED  => 'Confirmed',
            self::STATE_ASSIGNED   => 'Ready',
            self::STATE_DONE       => 'Done',
            self::STATE_CANCELLED  => 'Cancelled',
            default                => ucfirst($this->state),
        };
    }
}
