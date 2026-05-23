<?php

namespace App\Models\Employees;

use App\Models\User;
use App\Traits\HasChatter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Illuminate\Database\Eloquent\SoftDeletes;

class SkillType extends Model
{
    use HasChatter, SoftDeletes;

    protected $table = 'hr_skill_types';

    protected $fillable = ['uuid', 'name', 'active', 'created_by', 'updated_by'];

    protected $casts = ['active' => 'boolean'];

    public $sortable = ['name', 'active', 'created_at'];

    public $searchable = ['name'];

    public array $chatterTracked = [
        'name'   => 'Name',
        'active' => 'Active',
    ];

    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class, 'skill_type_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(SkillLevel::class, 'skill_type_id')->orderBy('sequence');
    }

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
