<?php

namespace App\Models\Inventory;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UomCategory extends Model
{
    protected $table = 'inventory_uom_categories';

    use HasChatter;

    public array $chatterTracked = [
        'name'   => 'Name',
        'active' => 'Active',
    ];

    public array $sortable = [
        'name'       => 'name',
        'created_at' => 'created_at',
    ];

    public array $searchable = [
        'name'       => ['label' => 'Name',       'column' => 'name',       'type' => 'string'],
        'active'     => ['label' => 'Active',      'column' => 'active',     'type' => 'boolean'],
        'created_at' => ['label' => 'Created on',  'column' => 'created_at', 'type' => 'datetime'],
    ];

    protected $fillable = ['uuid', 'name', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public function uoms(): HasMany
    {
        return $this->hasMany(Uom::class, 'uom_category_id');
    }

    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }

    public function scopeActive(Builder $q): Builder { return $q->where('active', true); }
    public function scopeInactive(Builder $q): Builder { return $q->where('active', false); }
}
