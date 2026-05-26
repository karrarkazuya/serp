<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Illuminate\Database\Eloquent\SoftDeletes;

class Badge extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_badges';

    // Rule 4: uuid / created_by / updated_by are observer-managed, never in $fillable.
    protected $fillable = ['name', 'description', 'active'];

    protected $casts = ['active' => 'boolean'];

    public $sortable = ['name', 'active', 'created_at'];

    public $searchable = ['name', 'description'];

    public array $chatterTracked = [
        'name'        => 'Name',
        'description' => 'Description',
        'active'      => 'Active',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
